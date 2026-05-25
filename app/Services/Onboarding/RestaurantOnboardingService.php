<?php

namespace App\Services\Onboarding;

use App\Models\Area;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\RestaurantTable;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Jobs\SendWelcomeEmail;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RestaurantOnboardingService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}
    /**
     * @param array{name:string,email:string,password:string,phone?:string|null,plan_code?:string|null,restaurant_name?:string|null} $input
     */
    public function onboard(array $input): User
    {
        $user = DB::transaction(function () use ($input): User {
            // Luôn khởi tạo doanh nghiệp ở gói 'free' trước (kể cả khi họ chọn trả phí như Pro)
            // Họ sẽ chỉ được nâng lên gói trả phí sau khi thanh toán thành công qua SePay.
            $plan = SubscriptionPlan::query()
                ->where('code', 'free')
                ->where('status', 'active')
                ->firstOrFail();

            $restaurantName = trim($input['restaurant_name'] ?? $input['name']);
            $userName = trim($input['name']);

            $restaurant = Restaurant::create([
                'plan_id' => $plan->id,
                'owner_user_id' => null,
                'code' => $this->generateRestaurantCode($restaurantName),
                'name' => $restaurantName,
                'slug' => $this->generateRestaurantSlug($restaurantName),
                'phone' => $input['phone'] ?? null,
                'email' => $input['email'],
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'status' => 'active',
                'subscription_started_at' => now()->toDateString(),
                'trial_ends_at' => now()->addDays(14)->toDateString(),
                'subscription_ends_at' => now()->addDays(14)->toDateString(),
            ]);

            $user = User::create([
                'name' => $userName,
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'phone' => $input['phone'] ?? null,
                'restaurant_id' => $restaurant->id,
                'status' => 'active',
                // Thiết kế SaaS Onboarding nhanh: xác minh email tự động ngay khi đăng ký.
                // Chủ nhà hàng cần vào hệ thống NGAY để trải nghiệm bán hàng trong phút đầu tiên.
                // Nếu muốn bắt buộc xác minh email, xóa dòng này và implement MustVerifyEmail trên User model.
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            $restaurant->forceFill(['owner_user_id' => $user->id])->save();
            $user->syncRoles(['owner']);

            RestaurantSubscription::create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'started_at' => now(),
                'ended_at' => now()->addDays(14),
                'renewal_at' => now()->addDays(14),
                'price' => $plan->price,
                'meta' => [
                    'source' => 'self_serve_onboarding',
                    'plan_code' => $plan->code,
                ],
            ]);

            $this->seedDefaults($restaurant, $user);

            return $user;
        });

        dispatch(new SendWelcomeEmail($user->id));

        return $user;
    }


    private function seedDefaults(Restaurant $restaurant, User $owner): void
    {
        $branchId = null;

        $massGram = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'g'], ['name' => 'Gram', 'type' => 'mass']);
        $massKg = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'kg'], ['name' => 'Kilogram', 'type' => 'mass']);
        $volumeMl = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'ml'], ['name' => 'Milliliter', 'type' => 'volume']);
        $volumeL = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'l'], ['name' => 'Liter', 'type' => 'volume']);
        $countEach = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'cai'], ['name' => 'Cai', 'type' => 'count']);
        $countBottle = Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'chai'], ['name' => 'Chai', 'type' => 'count']);

        $supplier = Supplier::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Nha cung cap mac dinh'],
            [
                'branch_id' => $branchId,
                'contact_name' => $owner->name,
                'phone' => $owner->phone ?? $restaurant->phone,
                'email' => $restaurant->email,
                'status' => 'active',
            ]
        );

        $area = Area::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'MAIN'],
            [
                'branch_id' => $branchId,
                'name' => 'Khu mac dinh',
                'display_order' => 1,
                'status' => 'active',
            ]
        );

        $table1 = $this->upsertTable($restaurant, $area, 'T1', 2);
        $table2 = $this->upsertTable($restaurant, $area, 'T2', 4);
        $table3 = $this->upsertTable($restaurant, $area, 'T3', 4);

        $riceCategory = ProductCategory::updateOrCreate(['restaurant_id' => $restaurant->id, 'slug' => 'com'], ['branch_id' => $branchId, 'name' => 'Com', 'description' => 'Danh muc mon com', 'display_order' => 1, 'status' => 'active']);
        $noodleCategory = ProductCategory::updateOrCreate(['restaurant_id' => $restaurant->id, 'slug' => 'my'], ['branch_id' => $branchId, 'name' => 'My', 'description' => 'Danh muc mon my', 'display_order' => 2, 'status' => 'active']);
        $drinkCategory = ProductCategory::updateOrCreate(['restaurant_id' => $restaurant->id, 'slug' => 'do-uong'], ['branch_id' => $branchId, 'name' => 'Do uong', 'description' => 'Danh muc do uong', 'display_order' => 3, 'status' => 'active']);

        $beef = Ingredient::updateOrCreate(['restaurant_id' => $restaurant->id, 'sku' => 'ING-BEEF-001'], ['branch_id' => $branchId, 'supplier_id' => $supplier->id, 'unit_id' => $massGram->id, 'name' => 'Thit bo', 'category_name' => 'Nguyen lieu tuoi', 'description' => 'Mau onboarding', 'min_stock_level' => 1000, 'reorder_level' => 2000, 'average_cost' => 280, 'status' => 'active']);
        $noodle = Ingredient::updateOrCreate(['restaurant_id' => $restaurant->id, 'sku' => 'ING-NOODLE-001'], ['branch_id' => $branchId, 'supplier_id' => $supplier->id, 'unit_id' => $massGram->id, 'name' => 'Banh pho', 'category_name' => 'Nguyen lieu kho', 'description' => 'Mau onboarding', 'min_stock_level' => 2000, 'reorder_level' => 5000, 'average_cost' => 40, 'status' => 'active']);
        $rice = Ingredient::updateOrCreate(['restaurant_id' => $restaurant->id, 'sku' => 'ING-RICE-001'], ['branch_id' => $branchId, 'supplier_id' => $supplier->id, 'unit_id' => $massGram->id, 'name' => 'Gao', 'category_name' => 'Nguyen lieu kho', 'description' => 'Mau onboarding', 'min_stock_level' => 1500, 'reorder_level' => 3000, 'average_cost' => 35, 'status' => 'active']);
        $ice = Ingredient::updateOrCreate(['restaurant_id' => $restaurant->id, 'sku' => 'ING-ICE-001'], ['branch_id' => $branchId, 'supplier_id' => $supplier->id, 'unit_id' => $volumeMl->id, 'name' => 'Da vien', 'category_name' => 'Do uong', 'description' => 'Mau onboarding', 'min_stock_level' => 5000, 'reorder_level' => 10000, 'average_cost' => 3, 'status' => 'active']);

        Inventory::updateOrCreate(['restaurant_id' => $restaurant->id, 'branch_id' => $branchId, 'ingredient_id' => $beef->id], ['quantity_on_hand' => 8000, 'theoretical_quantity' => 8000, 'last_counted_at' => now(), 'last_cost' => 280, 'updated_by' => $owner->id]);
        Inventory::updateOrCreate(['restaurant_id' => $restaurant->id, 'branch_id' => $branchId, 'ingredient_id' => $noodle->id], ['quantity_on_hand' => 15000, 'theoretical_quantity' => 15000, 'last_counted_at' => now(), 'last_cost' => 40, 'updated_by' => $owner->id]);
        Inventory::updateOrCreate(['restaurant_id' => $restaurant->id, 'branch_id' => $branchId, 'ingredient_id' => $rice->id], ['quantity_on_hand' => 12000, 'theoretical_quantity' => 12000, 'last_counted_at' => now(), 'last_cost' => 35, 'updated_by' => $owner->id]);
        Inventory::updateOrCreate(['restaurant_id' => $restaurant->id, 'branch_id' => $branchId, 'ingredient_id' => $ice->id], ['quantity_on_hand' => 30000, 'theoretical_quantity' => 30000, 'last_counted_at' => now(), 'last_cost' => 3, 'updated_by' => $owner->id]);

        $pho = Product::updateOrCreate(['restaurant_id' => $restaurant->id, 'code' => 'PHO-BO'], ['branch_id' => $branchId, 'category_id' => $noodleCategory->id, 'name' => 'Pho bo', 'slug' => 'pho-bo', 'description' => 'Mon demo de test ban hang', 'price' => 65000, 'cost_price' => 28000, 'preparation_time_minutes' => 12, 'is_active' => true, 'is_available' => true, 'is_featured' => true, 'track_inventory' => true]);
        $com = Product::updateOrCreate(['restaurant_id' => $restaurant->id, 'code' => 'COM-SUON'], ['branch_id' => $branchId, 'category_id' => $riceCategory->id, 'name' => 'Com suon', 'slug' => 'com-suon', 'description' => 'Mon demo de test ban hang', 'price' => 55000, 'cost_price' => 22000, 'preparation_time_minutes' => 10, 'is_active' => true, 'is_available' => true, 'is_featured' => false, 'track_inventory' => true]);
        $tra = Product::updateOrCreate(['restaurant_id' => $restaurant->id, 'code' => 'TRA-CHANH'], ['branch_id' => $branchId, 'category_id' => $drinkCategory->id, 'name' => 'Tra chanh', 'slug' => 'tra-chanh', 'description' => 'Mon demo de test ban hang', 'price' => 25000, 'cost_price' => 8000, 'preparation_time_minutes' => 3, 'is_active' => true, 'is_available' => true, 'is_featured' => false, 'track_inventory' => false]);

        ProductRecipe::updateOrCreate(['product_id' => $pho->id, 'ingredient_id' => $beef->id], ['restaurant_id' => $restaurant->id, 'unit_id' => $massGram->id, 'quantity' => 120, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $pho->id, 'ingredient_id' => $noodle->id], ['restaurant_id' => $restaurant->id, 'unit_id' => $massGram->id, 'quantity' => 180, 'waste_rate' => 1]);
        ProductRecipe::updateOrCreate(['product_id' => $com->id, 'ingredient_id' => $rice->id], ['restaurant_id' => $restaurant->id, 'unit_id' => $massGram->id, 'quantity' => 150, 'waste_rate' => 1]);
        ProductRecipe::updateOrCreate(['product_id' => $tra->id, 'ingredient_id' => $ice->id], ['restaurant_id' => $restaurant->id, 'unit_id' => $volumeMl->id, 'quantity' => 300, 'waste_rate' => 0]);

        $this->attachQrCode($restaurant, $table1);
        $this->attachQrCode($restaurant, $table2);
        $this->attachQrCode($restaurant, $table3);
    }

    private function upsertTable(Restaurant $restaurant, Area $area, string $name, int $capacity): RestaurantTable
    {
        return RestaurantTable::updateOrCreate([
            'area_id' => $area->id,
            'name' => $name,
        ], [
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'capacity' => $capacity,
            'status' => 'available',
        ]);
    }

    /**
     * Sinh QR token, URL đặt món, ảnh SVG và lưu đồng thời vào cả 3 cột.
     * qr_token  – token ngắn để build URL (không thay đổi sau khi tạo)
     * qr_code   – chuỗi URL đầy đủ được mã hoá trong ảnh QR
     * qr_code_path – đường dẫn file SVG trong Storage::disk('public')
     */
    private function attachQrCode(Restaurant $restaurant, RestaurantTable $table): void
    {
        $token = Str::lower(Str::random(12));
        $orderUrl = $this->qrCodeService->buildOrderUrl($restaurant->id, $token);
        $filename = $this->qrCodeService->buildFilename($restaurant->id, $table->name);

        try {
            $path = $this->qrCodeService->generateAndStore($orderUrl, $filename);
        } catch (\Throwable $e) {
            // Nếu storage không khả dụng (ví dụ: môi trường CI), bỏ qua việc lưu file;
            // qr_token vẫn được ghi để URL hoạt động.
            Log::warning('QrCodeService: không thể sinh file SVG', ['error' => $e->getMessage(), 'table_id' => $table->id]);
            $path = null;
        }

        $table->forceFill([
            'qr_token'     => $token,
            'qr_code'      => $orderUrl, // lưu URL đầy đủ để backward compatible
            'qr_code_path' => $path,
        ])->save();
    }

    private function generateRestaurantCode(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9]+/', '', Str::ascii($name)) ?: 'RST';
        return strtoupper(Str::limit($base, 10, '')) . '-' . strtoupper(Str::random(6));
    }

    private function generateRestaurantSlug(string $name): string
    {
        return Str::slug($name) . '-' . Str::lower(Str::random(4));
    }
}

