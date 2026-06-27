<?php

namespace App\Services\Onboarding;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\QrCodeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoDataSeederService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * Nạp dữ liệu mẫu theo template được chọn.
     *
     * @param Restaurant $restaurant
     * @param string $template  'bbq' | 'cafe' | 'bubble_tea'
     */
    public function seed(Restaurant $restaurant, string $template): void
    {
        DB::transaction(function () use ($restaurant, $template) {
            [$products, $categories] = match ($template) {
                'cafe'        => $this->seedCafe($restaurant),
                'bubble_tea'  => $this->seedBubbleTea($restaurant),
                default       => $this->seedBBQ($restaurant),
            };

            $employees = $this->seedEmployees($restaurant);
            $shifts    = $this->seedWorkShifts($restaurant);
            $this->seedScheduleAssignments($restaurant, $employees, $shifts);
            $this->seedHistoricalOrders($restaurant, $products, $employees);

            $restaurant->forceFill([
                'sandbox_mode'       => true,
                'sandbox_template'   => $template,
                'sandbox_seeded_at'  => now(),
            ])->save();
        });
    }

    /**
     * Xóa toàn bộ dữ liệu mẫu (force delete, không qua SoftDelete).
     */
    public function reset(Restaurant $restaurant): void
    {
        DB::transaction(function () use ($restaurant) {
            $rid = $restaurant->id;

            // Xóa revenue summaries
            RestaurantRevenueSummary::where('restaurant_id', $rid)->delete();

            // Xóa payments → order_items → orders
            $orderIds = Order::withTrashed()->where('restaurant_id', $rid)->pluck('id');
            Payment::withTrashed()->whereIn('order_id', $orderIds)->forceDelete();
            OrderItem::withTrashed()->whereIn('order_id', $orderIds)->forceDelete();
            Order::withTrashed()->where('restaurant_id', $rid)->forceDelete();

            // Xóa schedule assignments
            ScheduleAssignment::where('restaurant_id', $rid)->delete();

            // Xóa work shifts (demo)
            WorkShift::withTrashed()
                ->where('restaurant_id', $rid)
                ->where('code', 'like', 'DEMO-%')
                ->forceDelete();

            // Xóa employees mẫu và user accounts của họ (nhận diện qua email @demo.local)
            $employees = Employee::withTrashed()->where('restaurant_id', $rid)
                ->where('email', 'like', '%@demo.local')
                ->get();
            $userIds = $employees->pluck('user_id')->filter();
            Employee::withTrashed()->where('restaurant_id', $rid)->where('email', 'like', '%@demo.local')->forceDelete();
            if ($userIds->isNotEmpty()) {
                User::whereIn('id', $userIds)->delete();
            }

            // Xóa recipes → products → categories
            $productIds = Product::withTrashed()->where('restaurant_id', $rid)->pluck('id');
            ProductRecipe::whereIn('product_id', $productIds)->delete();
            Product::withTrashed()->where('restaurant_id', $rid)->forceDelete();
            ProductCategory::withTrashed()->where('restaurant_id', $rid)->forceDelete();

            // Xóa inventory → ingredients
            Inventory::where('restaurant_id', $rid)->delete();
            Ingredient::withTrashed()->where('restaurant_id', $rid)->forceDelete();

            // Xóa tables → areas
            RestaurantTable::withTrashed()->where('restaurant_id', $rid)->forceDelete();
            Area::withTrashed()->where('restaurant_id', $rid)->forceDelete();

            // Reset sandbox flags
            $restaurant->forceFill([
                'sandbox_mode'      => false,
                'sandbox_template'  => null,
                'sandbox_seeded_at' => null,
            ])->save();
        });
    }

    // ─────────────────────────────────────────
    //  TEMPLATE: Nhà hàng Nướng
    // ─────────────────────────────────────────
    private function seedBBQ(Restaurant $restaurant): array
    {
        $branchId = null;
        $rid = $restaurant->id;
        $units = $this->ensureUnits($restaurant);

        // Khu vực + bàn
        $areaIn  = $this->ensureArea($restaurant, 'IN',  'Phòng trong',   1);
        $areaOut = $this->ensureArea($restaurant, 'OUT', 'Sân ngoài trời', 2);
        $this->ensureTablesForArea($restaurant, $areaIn,  ['B01','B02','B03','B04','B05'], 4);
        $this->ensureTablesForArea($restaurant, $areaOut, ['S01','S02','S03','S04'],        6);

        // Categories
        $catCombo    = $this->cat($rid, $branchId, 'combo-nuong',  'Combo Nướng',         1);
        $catBBQ      = $this->cat($rid, $branchId, 'nuong-le',     'Món Nướng Lẻ',        2);
        $catRice     = $this->cat($rid, $branchId, 'com-cuon',     'Cơm & Cuốn',          3);
        $catSoup     = $this->cat($rid, $branchId, 'canh-sup',     'Canh & Lẩu',          4);
        $catDrink    = $this->cat($rid, $branchId, 'do-uong-bbq',  'Đồ Uống',             5);

        // Ingredients
        $beef    = $this->ingredient($rid, $branchId, $units['g'],   'ING-BBQ-BEEF',   'Thịt bò nướng',    'Nguyên liệu tươi', 500,  1000, 320);
        $pork    = $this->ingredient($rid, $branchId, $units['g'],   'ING-BBQ-PORK',   'Thịt heo nướng',   'Nguyên liệu tươi', 500,  1000, 180);
        $chicken = $this->ingredient($rid, $branchId, $units['g'],   'ING-BBQ-CHICK',  'Gà nướng muối ớt', 'Nguyên liệu tươi', 300,   700, 150);
        $rice    = $this->ingredient($rid, $branchId, $units['g'],   'ING-BBQ-RICE',   'Gạo',              'Nguyên liệu khô',  2000,  5000, 35);
        $charcoal= $this->ingredient($rid, $branchId, $units['kg'],  'ING-BBQ-COAL',   'Than hoa',         'Dụng cụ bếp',      10,    25,   45000);
        $beer    = $this->ingredient($rid, $branchId, $units['chai'],'ING-BBQ-BEER',   'Bia lon',          'Đồ uống',          48,    100,  12000);
        $water   = $this->ingredient($rid, $branchId, $units['chai'],'ING-BBQ-WATER',  'Nước suối',        'Đồ uống',          50,    100,  4000);

        $this->inventory($rid, $branchId, $beef,    5000,  320);
        $this->inventory($rid, $branchId, $pork,    8000,  180);
        $this->inventory($rid, $branchId, $chicken, 4000,  150);
        $this->inventory($rid, $branchId, $rice,    20000,  35);
        $this->inventory($rid, $branchId, $charcoal,50,    45000);
        $this->inventory($rid, $branchId, $beer,    120,   12000);
        $this->inventory($rid, $branchId, $water,   200,    4000);

        // Products
        $products = collect([
            $p1 = $this->product($rid, $branchId, $catCombo->id,  'COMBO-BOI-2', 'Combo Nướng 2 người',   249000, 95000,  15),
            $p2 = $this->product($rid, $branchId, $catCombo->id,  'COMBO-BOI-4', 'Combo Nướng 4 người',   449000, 180000, 20),
            $p3 = $this->product($rid, $branchId, $catBBQ->id,    'BO-MI-CAO',   'Bò mỹ cao cấp (200g)',  145000, 55000,  10),
            $p4 = $this->product($rid, $branchId, $catBBQ->id,    'HEO-CUON-LA', 'Heo cuốn lá lốt (200g)',89000,  32000,   8),
            $p5 = $this->product($rid, $branchId, $catBBQ->id,    'GA-MUOI-OT',  'Gà muối ớt (1/4 con)',  95000,  38000,  12),
            $p6 = $this->product($rid, $branchId, $catBBQ->id,    'TOM-NUONG-SO','Tôm nướng sả (8 con)',  120000, 48000,  10),
            $p7 = $this->product($rid, $branchId, $catRice->id,   'COM-TRANG',   'Cơm trắng',              15000,   3000,   3),
            $p8 = $this->product($rid, $branchId, $catRice->id,   'BANH-TRANG',  'Bánh tráng cuốn',        25000,   6000,   2),
            $p9 = $this->product($rid, $branchId, $catSoup->id,   'LAU-TOM',     'Lẩu tôm chua cay',      199000, 80000,  25),
            $p10= $this->product($rid, $branchId, $catDrink->id,  'BIA-LARUE',   'Bia LaRue lon',           25000, 12000,   1),
            $p11= $this->product($rid, $branchId, $catDrink->id,  'NUOC-SUOI',   'Nước suối',               10000,  4000,   1),
            $p12= $this->product($rid, $branchId, $catDrink->id,  'NUOC-NGOT',   'Nước ngọt lon',           20000,  8000,   1),
        ]);

        ProductRecipe::updateOrCreate(['product_id' => $p1->id, 'ingredient_id' => $beef->id],    ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 200, 'waste_rate' => 5]);
        ProductRecipe::updateOrCreate(['product_id' => $p1->id, 'ingredient_id' => $pork->id],    ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 150, 'waste_rate' => 3]);
        ProductRecipe::updateOrCreate(['product_id' => $p3->id, 'ingredient_id' => $beef->id],    ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 200, 'waste_rate' => 5]);
        ProductRecipe::updateOrCreate(['product_id' => $p4->id, 'ingredient_id' => $pork->id],    ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 200, 'waste_rate' => 3]);
        ProductRecipe::updateOrCreate(['product_id' => $p5->id, 'ingredient_id' => $chicken->id], ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 250, 'waste_rate' => 5]);
        ProductRecipe::updateOrCreate(['product_id' => $p7->id, 'ingredient_id' => $rice->id],    ['restaurant_id' => $rid, 'unit_id' => $units['g'],    'quantity' => 150, 'waste_rate' => 1]);
        ProductRecipe::updateOrCreate(['product_id' => $p10->id,'ingredient_id' => $beer->id],    ['restaurant_id' => $rid, 'unit_id' => $units['chai'], 'quantity' => 1,   'waste_rate' => 0]);
        ProductRecipe::updateOrCreate(['product_id' => $p11->id,'ingredient_id' => $water->id],   ['restaurant_id' => $rid, 'unit_id' => $units['chai'], 'quantity' => 1,   'waste_rate' => 0]);

        return [$products, collect([$catCombo, $catBBQ, $catRice, $catSoup, $catDrink])];
    }

    // ─────────────────────────────────────────
    //  TEMPLATE: Quán Cafe
    // ─────────────────────────────────────────
    private function seedCafe(Restaurant $restaurant): array
    {
        $branchId = null;
        $rid = $restaurant->id;
        $units = $this->ensureUnits($restaurant);

        $areaMain = $this->ensureArea($restaurant, 'CAFE-IN',  'Phòng máy lạnh', 1);
        $areaBal  = $this->ensureArea($restaurant, 'CAFE-OUT', 'Ban công',        2);
        $this->ensureTablesForArea($restaurant, $areaMain, ['C01','C02','C03','C04','C05','C06'], 2);
        $this->ensureTablesForArea($restaurant, $areaBal,  ['BC01','BC02','BC03'],                2);

        $catCoffee = $this->cat($rid, $branchId, 'ca-phe',    'Cà Phê',           1);
        $catMilk   = $this->cat($rid, $branchId, 'sua-tua',   'Sữa Tươi & Matcha',2);
        $catJuice  = $this->cat($rid, $branchId, 'nuoc-ep',   'Nước Ép & Sinh Tố', 3);
        $catCake   = $this->cat($rid, $branchId, 'banh-ngot', 'Bánh Ngọt',         4);
        $catToast  = $this->cat($rid, $branchId, 'toast',     'Toast & Ăn Nhẹ',    5);

        $coffeeBeans = $this->ingredient($rid, $branchId, $units['g'],   'ING-CF-BEAN',   'Cà phê hạt Robusta', 'Nguyên liệu',   500,  1200,  180);
        $milk        = $this->ingredient($rid, $branchId, $units['ml'],  'ING-CF-MILK',   'Sữa tươi',           'Nguyên liệu',   2000, 5000,  22);
        $condensed   = $this->ingredient($rid, $branchId, $units['ml'],  'ING-CF-COND',   'Sữa đặc',            'Nguyên liệu',   500,  1500,  35);
        $matchaPwd   = $this->ingredient($rid, $branchId, $units['g'],   'ING-CF-MATCHA', 'Bột matcha',         'Nguyên liệu',   200,  500,   450);
        $sugar       = $this->ingredient($rid, $branchId, $units['g'],   'ING-CF-SUGAR',  'Đường cát',          'Nguyên liệu',   1000, 3000,  20);
        $bread       = $this->ingredient($rid, $branchId, $units['cai'], 'ING-CF-BREAD',  'Bánh mì ổ',          'Nguyên liệu',   20,   50,    8000);

        $this->inventory($rid, $branchId, $coffeeBeans, 3000,  180);
        $this->inventory($rid, $branchId, $milk,        10000,  22);
        $this->inventory($rid, $branchId, $condensed,   3000,   35);
        $this->inventory($rid, $branchId, $matchaPwd,   1000,  450);
        $this->inventory($rid, $branchId, $sugar,       5000,   20);
        $this->inventory($rid, $branchId, $bread,       60,   8000);

        $products = collect([
            $p1 = $this->product($rid, $branchId, $catCoffee->id, 'CF-DEN-DA',     'Cà phê đen đá',           35000, 8000,  3),
            $p2 = $this->product($rid, $branchId, $catCoffee->id, 'CF-SUA-DA',     'Cà phê sữa đá',           39000, 10000, 3),
            $p3 = $this->product($rid, $branchId, $catCoffee->id, 'CF-CAPPU',      'Cappuccino',               55000, 18000, 5),
            $p4 = $this->product($rid, $branchId, $catCoffee->id, 'CF-LATTE',      'Latte',                    59000, 20000, 5),
            $p5 = $this->product($rid, $branchId, $catCoffee->id, 'CF-AMERICANO',  'Americano',                45000, 12000, 4),
            $p6 = $this->product($rid, $branchId, $catMilk->id,   'MATCHA-LATTE',  'Matcha Latte',             65000, 22000, 5),
            $p7 = $this->product($rid, $branchId, $catMilk->id,   'SUA-TUOI-TRAN', 'Sữa tươi trân châu',      49000, 16000, 4),
            $p8 = $this->product($rid, $branchId, $catJuice->id,  'NUOC-EP-CAM',   'Nước ép cam',             49000, 15000, 5),
            $p9 = $this->product($rid, $branchId, $catJuice->id,  'SINH-TO-BOC',   'Sinh tố bơ',              65000, 25000, 6),
            $p10= $this->product($rid, $branchId, $catCake->id,   'BANH-CROISSANT','Bánh croissant',           45000, 18000, 2),
            $p11= $this->product($rid, $branchId, $catCake->id,   'BANH-TIRAMI',   'Tiramisu',                 65000, 28000, 3),
            $p12= $this->product($rid, $branchId, $catCake->id,   'BANH-CHEESECAKE','Cheesecake',              75000, 32000, 3),
            $p13= $this->product($rid, $branchId, $catToast->id,  'TOAST-TRUNG',   'Toast trứng bơ',           39000, 12000, 5),
            $p14= $this->product($rid, $branchId, $catToast->id,  'TOAST-NUTELLA', 'Toast Nutella',            45000, 15000, 4),
            $p15= $this->product($rid, $branchId, $catToast->id,  'BANH-MY-TRUNG', 'Bánh mì trứng ốp la',     35000, 10000, 5),
        ]);

        ProductRecipe::updateOrCreate(['product_id' => $p1->id, 'ingredient_id' => $coffeeBeans->id], ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 18, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $p2->id, 'ingredient_id' => $coffeeBeans->id], ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 18, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $p2->id, 'ingredient_id' => $condensed->id],   ['restaurant_id' => $rid, 'unit_id' => $units['ml'], 'quantity' => 30, 'waste_rate' => 0]);
        ProductRecipe::updateOrCreate(['product_id' => $p3->id, 'ingredient_id' => $coffeeBeans->id], ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 18, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $p3->id, 'ingredient_id' => $milk->id],        ['restaurant_id' => $rid, 'unit_id' => $units['ml'], 'quantity' => 150,'waste_rate' => 0]);
        ProductRecipe::updateOrCreate(['product_id' => $p6->id, 'ingredient_id' => $matchaPwd->id],   ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 10, 'waste_rate' => 1]);
        ProductRecipe::updateOrCreate(['product_id' => $p6->id, 'ingredient_id' => $milk->id],        ['restaurant_id' => $rid, 'unit_id' => $units['ml'], 'quantity' => 200,'waste_rate' => 0]);
        ProductRecipe::updateOrCreate(['product_id' => $p15->id,'ingredient_id' => $bread->id],       ['restaurant_id' => $rid, 'unit_id' => $units['cai'],'quantity' => 1,  'waste_rate' => 0]);

        return [$products, collect([$catCoffee, $catMilk, $catJuice, $catCake, $catToast])];
    }

    // ─────────────────────────────────────────
    //  TEMPLATE: Tiệm Trà Sữa
    // ─────────────────────────────────────────
    private function seedBubbleTea(Restaurant $restaurant): array
    {
        $branchId = null;
        $rid = $restaurant->id;
        $units = $this->ensureUnits($restaurant);

        $areaMain = $this->ensureArea($restaurant, 'TS-MAIN', 'Khu ngồi chính', 1);
        $this->ensureTablesForArea($restaurant, $areaMain, ['A01','A02','A03','A04','A05','A06','A07','A08'], 2);

        $catTra     = $this->cat($rid, $branchId, 'tra-sua',     'Trà Sữa',          1);
        $catFruTea  = $this->cat($rid, $branchId, 'tra-trai-cay','Trà Trái Cây',      2);
        $catTopping = $this->cat($rid, $branchId, 'topping',     'Topping',           3);
        $catSnack   = $this->cat($rid, $branchId, 'do-an-nhe',   'Đồ Ăn Nhẹ',        4);
        $catCombo   = $this->cat($rid, $branchId, 'combo-tra',   'Combo Ưu Đãi',      5);

        $teaPwd    = $this->ingredient($rid, $branchId, $units['g'],   'ING-TS-TEA',    'Bột trà đen', 'Nguyên liệu', 300,  800,   220);
        $oatMilk   = $this->ingredient($rid, $branchId, $units['ml'],  'ING-TS-OATMILK','Sữa yến mạch','Nguyên liệu', 2000, 5000,  28);
        $tapioca   = $this->ingredient($rid, $branchId, $units['g'],   'ING-TS-TAPIOCA','Trân châu',   'Nguyên liệu', 1000, 2500,  65);
        $jellyPwd  = $this->ingredient($rid, $branchId, $units['g'],   'ING-TS-JELLY',  'Bột thạch',   'Nguyên liệu', 500,  1200,  45);
        $strawberry= $this->ingredient($rid, $branchId, $units['g'],   'ING-TS-STRAW',  'Dâu tây',     'Nguyên liệu', 500,  1500,  120);
        $sugar2    = $this->ingredient($rid, $branchId, $units['ml'],  'ING-TS-SYRUP',  'Syrup đường', 'Nguyên liệu', 1000, 3000,  35);

        $this->inventory($rid, $branchId, $teaPwd,    2000,  220);
        $this->inventory($rid, $branchId, $oatMilk,   10000,  28);
        $this->inventory($rid, $branchId, $tapioca,   5000,   65);
        $this->inventory($rid, $branchId, $jellyPwd,  2500,   45);
        $this->inventory($rid, $branchId, $strawberry,2000,  120);
        $this->inventory($rid, $branchId, $sugar2,    5000,   35);

        $products = collect([
            $p1 = $this->product($rid, $branchId, $catTra->id,     'TS-THAI-S',     'Trà sữa Thái (S)',           35000, 10000, 3),
            $p2 = $this->product($rid, $branchId, $catTra->id,     'TS-THAI-L',     'Trà sữa Thái (L)',           45000, 13000, 3),
            $p3 = $this->product($rid, $branchId, $catTra->id,     'TS-OOLONG',     'Trà sữa Oolong',             49000, 15000, 3),
            $p4 = $this->product($rid, $branchId, $catTra->id,     'TS-MATCHA',     'Trà sữa Matcha',             55000, 18000, 4),
            $p5 = $this->product($rid, $branchId, $catTra->id,     'TS-HONG-TRA',   'Trà sữa Hồng Trà',           45000, 14000, 3),
            $p6 = $this->product($rid, $branchId, $catFruTea->id,  'TTC-DAU',       'Trà dâu',                    49000, 16000, 4),
            $p7 = $this->product($rid, $branchId, $catFruTea->id,  'TTC-CHANH-GUNG','Trà chanh gừng',             39000, 10000, 3),
            $p8 = $this->product($rid, $branchId, $catFruTea->id,  'TTC-VAI',       'Trà vải',                    45000, 14000, 4),
            $p9 = $this->product($rid, $branchId, $catFruTea->id,  'TTC-XOAI',      'Trà xoài đào',               45000, 14000, 4),
            $p10= $this->product($rid, $branchId, $catTopping->id, 'TOPPING-TC',    'Trân châu đen',               8000,  3000, 2, false),
            $p11= $this->product($rid, $branchId, $catTopping->id, 'TOPPING-THACH', 'Thạch trà xanh',              8000,  3000, 2, false),
            $p12= $this->product($rid, $branchId, $catTopping->id, 'TOPPING-KEM',   'Kem cheese mặn',             10000,  4000, 2, false),
            $p13= $this->product($rid, $branchId, $catSnack->id,   'BANH-GONG-EE',  'Bánh gòng ée Đài Loan',      39000, 12000, 8),
            $p14= $this->product($rid, $branchId, $catSnack->id,   'BANH-TARO',     'Bánh khoai môn nướng',        35000, 10000, 7),
            $p15= $this->product($rid, $branchId, $catSnack->id,   'KHOAI-CHIEN',   'Khoai tây chiên',             29000,  8000, 6),
            $p16= $this->product($rid, $branchId, $catSnack->id,   'XUC-XIC',       'Xúc xích chiên',              29000,  8000, 6),
            $p17= $this->product($rid, $branchId, $catCombo->id,   'COMBO-DOI',     'Combo đôi (2 ly + 2 topping)',85000, 28000, 5),
            $p18= $this->product($rid, $branchId, $catCombo->id,   'COMBO-NHOM',    'Combo nhóm (4 ly)',          149000, 50000, 5),
        ]);

        ProductRecipe::updateOrCreate(['product_id' => $p1->id,  'ingredient_id' => $teaPwd->id],   ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 12, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $p1->id,  'ingredient_id' => $oatMilk->id],  ['restaurant_id' => $rid, 'unit_id' => $units['ml'], 'quantity' => 150,'waste_rate' => 0]);
        ProductRecipe::updateOrCreate(['product_id' => $p1->id,  'ingredient_id' => $tapioca->id],  ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 40, 'waste_rate' => 3]);
        ProductRecipe::updateOrCreate(['product_id' => $p10->id, 'ingredient_id' => $tapioca->id],  ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 50, 'waste_rate' => 3]);
        ProductRecipe::updateOrCreate(['product_id' => $p11->id, 'ingredient_id' => $jellyPwd->id], ['restaurant_id' => $rid, 'unit_id' => $units['g'],  'quantity' => 20, 'waste_rate' => 2]);
        ProductRecipe::updateOrCreate(['product_id' => $p6->id,  'ingredient_id' => $strawberry->id],['restaurant_id' => $rid, 'unit_id' => $units['g'], 'quantity' => 60, 'waste_rate' => 5]);

        return [$products, collect([$catTra, $catFruTea, $catTopping, $catSnack, $catCombo])];
    }

    // ─────────────────────────────────────────
    //  Seed Employees (5 nhân viên mẫu)
    // ─────────────────────────────────────────
    private function seedEmployees(Restaurant $restaurant): Collection
    {
        $ownerRole = Role::where('name', 'owner')->first();
        $staffRole = Role::where('name', 'staff')->first();
        $managerRole = Role::where('name', 'manager')->first();

        $staffRoleId = $staffRole?->id;
        $managerRoleId = $managerRole?->id;

        $employees = collect();

        $demoStaff = [
            ['Nguyễn Văn Minh',  'minh.demo',   'manager', $managerRoleId, 85000,  'male'],
            ['Trần Thị Hoa',     'hoa.demo',    'staff',   $staffRoleId,   65000,  'female'],
            ['Lê Quang Hưng',    'hung.demo',   'staff',   $staffRoleId,   65000,  'male'],
            ['Phạm Thu Thảo',    'thao.demo',   'staff',   $staffRoleId,   65000,  'female'],
            ['Đỗ Bảo Long',      'long.demo',   'staff',   $staffRoleId,   65000,  'male'],
        ];

        foreach ($demoStaff as [$name, $emailPrefix, $roleName, $roleId, $payRate, $gender]) {
            $email = $emailPrefix . '_' . $restaurant->id . '@demo.local';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'          => $name,
                    'password'      => Hash::make('Demo@1234'),
                    'restaurant_id' => $restaurant->id,
                    'status'        => 'active',
                ]
            );

            if ($roleName && $roleId) {
                try { $user->assignRole($roleName); } catch (\Exception) {}
            }

            $emp = Employee::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'user_id'       => $user->id,
                ],
                [
                    'branch_id'      => null,
                    'role_id'        => $roleId,
                    'employee_code'  => 'EMP-' . strtoupper(Str::random(6)),
                    'full_name'      => $name,
                    'email'          => $email,
                    'phone'          => '09' . rand(10000000, 99999999),
                    'gender'         => $gender,
                    'hire_date'      => now()->subMonths(rand(1, 12))->toDateString(),
                    'compensation_type' => 'hourly',
                    'pay_rate'       => $payRate,
                    'status'         => 'active',
                ]
            );

            $employees->push($emp);
        }

        return $employees;
    }

    // ─────────────────────────────────────────
    //  Seed Work Shifts (3 ca)
    // ─────────────────────────────────────────
    private function seedWorkShifts(Restaurant $restaurant): Collection
    {
        $shifts = collect();
        $shiftDefs = [
            ['DEMO-CA-SANG',    'Ca Sáng',    '07:00:00', '12:00:00', false],
            ['DEMO-CA-CHIEU',   'Ca Chiều',   '12:00:00', '18:00:00', false],
            ['DEMO-CA-TOI',     'Ca Tối',     '18:00:00', '23:00:00', false],
        ];

        foreach ($shiftDefs as [$code, $name, $start, $end, $overnight]) {
            $shift = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => $code],
                [
                    'branch_id'  => null,
                    'name'       => $name,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'is_overnight' => $overnight,
                    'status'     => 'active',
                ]
            );
            $shifts->push($shift);
        }

        return $shifts;
    }

    // ─────────────────────────────────────────
    //  Seed Schedule Assignments (30 ngày)
    // ─────────────────────────────────────────
    private function seedScheduleAssignments(Restaurant $restaurant, Collection $employees, Collection $shifts): void
    {
        $startDate = now()->subDays(30)->startOfDay();
        $endDate   = now()->subDay()->endOfDay();

        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            foreach ($employees as $idx => $employee) {
                // Mỗi nhân viên làm ~5 ngày/tuần (bỏ random 2 ngày)
                if (rand(0, 6) < 2) continue;

                $shift = $shifts->get($idx % $shifts->count());
                $dateStr = $d->toDateString();

                // Tránh tạo duplicate
                $exists = ScheduleAssignment::where('restaurant_id', $restaurant->id)
                    ->where('employee_id', $employee->id)
                    ->where('scheduled_date', $dateStr)
                    ->exists();

                if (!$exists) {
                    ScheduleAssignment::create([
                        'restaurant_id' => $restaurant->id,
                        'employee_id'   => $employee->id,
                        'shift_id'      => $shift->id,
                        'scheduled_date'=> $dateStr,
                        'status'        => 'completed',
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────
    //  Seed 100 đơn hàng lịch sử
    // ─────────────────────────────────────────
    private function seedHistoricalOrders(Restaurant $restaurant, Collection $products, Collection $employees): void
    {
        $tables = RestaurantTable::where('restaurant_id', $restaurant->id)->get();
        if ($tables->isEmpty() || $products->isEmpty()) return;

        $rid = $restaurant->id;
        $creator = $employees->first();
        $creatorUserId = $creator?->user_id;

        $revenueSummaries = [];

        for ($i = 0; $i < 100; $i++) {
            // Random ngày trong 30 ngày qua
            $daysAgo   = rand(1, 30);
            $orderHour = rand(9, 22);
            $orderDate = now()->subDays($daysAgo)->setHour($orderHour)->setMinute(rand(0, 59))->setSecond(0);
            $dateStr   = $orderDate->toDateString();

            $table     = $tables->random();
            $numItems  = rand(1, 5);
            $totalAmount = 0;
            $orderItems  = [];

            $selectedProducts = $products->random(min($numItems, $products->count()));

            foreach ($selectedProducts as $product) {
                $qty    = rand(1, 3);
                $price  = $product->price;
                $amount = $qty * $price;
                $totalAmount += $amount;

                $orderItems[] = [
                    'restaurant_id' => $rid,
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'quantity'      => $qty,
                    'unit_price'    => $price,
                    'subtotal'      => $amount,
                    'status'        => 'served',
                    'sent_to_kitchen_at' => $orderDate->copy()->addMinutes(2),
                    'prepared_at'        => $orderDate->copy()->addMinutes($product->preparation_time_minutes ?? 10),
                    'served_at'          => $orderDate->copy()->addMinutes(($product->preparation_time_minutes ?? 10) + 2),
                    'created_at'         => $orderDate,
                    'updated_at'         => $orderDate,
                ];
            }

            $orderNumber = 'DEMO-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);

            $order = Order::create([
                'restaurant_id'  => $rid,
                'table_id'       => $table->id,
                'order_number'   => $orderNumber,
                'status'         => 'completed',
                'payment_status' => 'paid',
                'channel'        => ['dine_in', 'takeaway'][rand(0, 1)],
                'total_amount'   => $totalAmount,
                'created_by'     => $creatorUserId,
                'confirmed_at'   => $orderDate->copy()->addMinutes(2),
                'completed_at'   => $orderDate->copy()->addMinutes(30),
                'created_at'     => $orderDate,
                'updated_at'     => $orderDate,
            ]);

            // Order Items
            foreach ($orderItems as &$item) {
                $item['order_id'] = $order->id;
            }
            OrderItem::insert($orderItems);

            // Payment
            Payment::create([
                'restaurant_id' => $rid,
                'order_id'      => $order->id,
                'amount'        => $totalAmount,
                'method'        => ['cash', 'bank_transfer', 'momo'][rand(0, 2)],
                'status'        => 'success',
                'paid_at'       => $orderDate->copy()->addMinutes(31),
                'created_at'    => $orderDate,
                'updated_at'    => $orderDate,
            ]);

            // Tổng hợp revenue theo ngày
            if (!isset($revenueSummaries[$dateStr])) {
                $revenueSummaries[$dateStr] = [
                    'total_revenue' => 0,
                    'order_count'   => 0,
                    'first_order_at'=> $orderDate,
                    'last_order_at' => $orderDate,
                ];
            }
            $revenueSummaries[$dateStr]['total_revenue'] += $totalAmount;
            $revenueSummaries[$dateStr]['order_count']++;
            if ($orderDate < $revenueSummaries[$dateStr]['first_order_at']) {
                $revenueSummaries[$dateStr]['first_order_at'] = $orderDate;
            }
            if ($orderDate > $revenueSummaries[$dateStr]['last_order_at']) {
                $revenueSummaries[$dateStr]['last_order_at'] = $orderDate;
            }
        }

        // Ghi RestaurantRevenueSummary
        foreach ($revenueSummaries as $dateStr => $summary) {
            RestaurantRevenueSummary::updateOrCreate(
                ['restaurant_id' => $rid, 'branch_id' => null, 'summary_date' => $dateStr],
                [
                    'total_revenue'     => $summary['total_revenue'],
                    'total_orders'      => $summary['order_count'],
                    'paid_orders'       => $summary['order_count'],
                    'first_order_at'    => $summary['first_order_at'],
                    'last_order_at'     => $summary['last_order_at'],
                    'calculated_at'     => now(),
                ]
            );
        }
    }

    // ─────────────────────────────────────────
    //  Helper: Đảm bảo Units tồn tại
    // ─────────────────────────────────────────
    private function ensureUnits(Restaurant $restaurant): array
    {
        return [
            'g'    => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'g'],    ['name' => 'Gram',       'type' => 'mass'])->id,
            'kg'   => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'kg'],   ['name' => 'Kilogram',   'type' => 'mass'])->id,
            'ml'   => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'ml'],   ['name' => 'Milliliter', 'type' => 'volume'])->id,
            'l'    => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'l'],    ['name' => 'Liter',      'type' => 'volume'])->id,
            'cai'  => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'cai'],  ['name' => 'Cái',        'type' => 'count'])->id,
            'chai' => Unit::updateOrCreate(['restaurant_id' => $restaurant->id, 'symbol' => 'chai'], ['name' => 'Chai',       'type' => 'count'])->id,
        ];
    }

    private function ensureArea(Restaurant $restaurant, string $code, string $name, int $order): Area
    {
        return Area::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => $code],
            ['branch_id' => null, 'name' => $name, 'display_order' => $order, 'status' => 'active']
        );
    }

    private function ensureTablesForArea(Restaurant $restaurant, Area $area, array $names, int $capacity): void
    {
        foreach ($names as $name) {
            $table = RestaurantTable::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'area_id' => $area->id, 'name' => $name],
                ['branch_id' => null, 'capacity' => $capacity, 'status' => 'available']
            );

            // Nếu chưa có QR token, gán luôn
            if (empty($table->qr_token)) {
                $token = Str::lower(Str::random(12));
                try {
                    $orderUrl = $this->qrCodeService->buildOrderUrl($restaurant->id, $token);
                    $filename = $this->qrCodeService->buildFilename($restaurant->id, $name);
                    $path = $this->qrCodeService->generateAndStore($orderUrl, $filename);
                } catch (\Throwable $e) {
                    Log::warning('DemoDataSeeder: QR generation failed', ['table' => $name, 'error' => $e->getMessage()]);
                    $orderUrl = '';
                    $path = null;
                }
                $table->forceFill(['qr_token' => $token, 'qr_code' => $orderUrl, 'qr_code_path' => $path])->save();
            }
        }
    }

    private function cat(int $rid, ?int $branchId, string $slug, string $name, int $order): ProductCategory
    {
        return ProductCategory::updateOrCreate(
            ['restaurant_id' => $rid, 'slug' => $slug],
            ['branch_id' => $branchId, 'name' => $name, 'display_order' => $order, 'status' => 'active']
        );
    }

    private function ingredient(int $rid, ?int $branchId, int $unitId, string $sku, string $name, string $catName, int $minStock, int $reorder, float $cost): Ingredient
    {
        return Ingredient::updateOrCreate(
            ['restaurant_id' => $rid, 'sku' => $sku],
            ['branch_id' => $branchId, 'unit_id' => $unitId, 'name' => $name, 'category_name' => $catName, 'min_stock_level' => $minStock, 'reorder_level' => $reorder, 'average_cost' => $cost, 'status' => 'active']
        );
    }

    private function inventory(int $rid, ?int $branchId, Ingredient $ing, float $qty, float $cost): void
    {
        Inventory::updateOrCreate(
            ['restaurant_id' => $rid, 'branch_id' => $branchId, 'ingredient_id' => $ing->id],
            ['quantity_on_hand' => $qty, 'theoretical_quantity' => $qty, 'last_counted_at' => now(), 'last_cost' => $cost, 'updated_by' => null]
        );
    }

    private function product(int $rid, ?int $branchId, int $catId, string $code, string $name, int $price, int $cost, int $prepTime, bool $featured = true): Product
    {
        $slug = Str::slug($name) . '-' . strtolower(Str::random(4));

        return Product::updateOrCreate(
            ['restaurant_id' => $rid, 'code' => $code],
            [
                'branch_id'                  => $branchId,
                'category_id'                => $catId,
                'name'                       => $name,
                'slug'                       => $slug,
                'description'                => 'Sản phẩm mẫu để trải nghiệm hệ thống.',
                'price'                      => $price,
                'cost_price'                 => $cost,
                'preparation_time_minutes'   => $prepTime,
                'is_active'                  => true,
                'is_available'               => true,
                'is_featured'                => $featured,
                'track_inventory'            => true,
            ]
        );
    }
}
