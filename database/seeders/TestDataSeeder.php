<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu mẫu test đầy đủ 13 chức năng (3.5 → 4.7).
 * Chạy: php artisan db:seed --class=TestDataSeeder
 *
 * Yêu cầu: đã chạy DatabaseSeeder trước (có restaurant owner@bepso.test).
 */
class TestDataSeeder extends Seeder
{
    public function __construct(private readonly SalaryService $salary) {}

    public function run(): void
    {
        $owner = User::where('email', 'owner@bepso.test')->firstOrFail();
        $restaurant = $owner->restaurant()->firstOrFail();
        $branch = RestaurantBranch::where('restaurant_id', $restaurant->id)->firstOrFail();

        $manager = User::where('email', 'manager@bepso.test')->firstOrFail();
        $cashier = User::where('email', 'cashier@bepso.test')->firstOrFail();
        $kitchen = User::where('email', 'kitchen@bepso.test')->firstOrFail();

        $empOwner = Employee::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('employee_code', 'EMP-001')->firstOrFail();
        $empManager = Employee::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('employee_code', 'EMP-002')->firstOrFail();
        $empCashier = Employee::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('employee_code', 'EMP-003')->firstOrFail();
        $empKitchen = Employee::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('employee_code', 'EMP-004')->firstOrFail();

        // Cập nhật base_salary cho đúng kịch bản test
        $empOwner->updateQuietly(['base_salary' => 15000000]);
        $empManager->updateQuietly(['base_salary' => 12000000]);
        $empCashier->updateQuietly(['base_salary' => 8000000]);
        $empKitchen->updateQuietly(['base_salary' => 9000000]);

        DB::transaction(function () use ($restaurant, $branch, $owner, $manager, $cashier, $kitchen,
            $empOwner, $empManager, $empCashier, $empKitchen) {

            // ── BLOCK 3: Ca làm việc ──────────────────────────────────────────────
            $shiftSang = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CA_SANG_TEST'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Ca Sáng (06:00–14:00)',
                    'start_time' => '06:00:00',
                    'end_time' => '14:00:00',
                    'is_overnight' => false,
                    'status' => 'active',
                ],
            );
            $shiftChieu = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CA_CHIEU_TEST'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Ca Chiều (14:00–22:00)',
                    'start_time' => '14:00:00',
                    'end_time' => '22:00:00',
                    'is_overnight' => false,
                    'status' => 'active',
                ],
            );
            $shiftToi = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CA_TOI_TEST'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Ca Tối (22:00–06:00)',
                    'start_time' => '22:00:00',
                    'end_time' => '06:00:00',
                    'is_overnight' => true,
                    'status' => 'active',
                ],
            );

            // ── BLOCK 4: Lịch phân ca ────────────────────────────────────────────
            $today = Carbon::today()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();
            $tomorrow = Carbon::tomorrow()->toDateString();

            // Manager — Ca Sáng hôm nay — scheduled
            ScheduleAssignment::updateOrCreate(
                ['employee_id' => $empManager->id, 'shift_id' => $shiftSang->id, 'scheduled_date' => $today],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'scheduled',
                ],
            );

            // Cashier — Ca Sáng hôm nay — checked_in
            ScheduleAssignment::updateOrCreate(
                ['employee_id' => $empCashier->id, 'shift_id' => $shiftSang->id, 'scheduled_date' => $today],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'checked_in',
                    'check_in_at' => Carbon::now()->subHours(2),
                ],
            );

            // Kitchen — Ca Sáng hôm nay — completed
            ScheduleAssignment::updateOrCreate(
                ['employee_id' => $empKitchen->id, 'shift_id' => $shiftSang->id, 'scheduled_date' => $today],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'completed',
                    'check_in_at' => Carbon::now()->subHours(8),
                    'check_out_at' => Carbon::now()->subHours(1),
                ],
            );

            // Cashier — Ca Chiều ngày mai — scheduled
            ScheduleAssignment::updateOrCreate(
                ['employee_id' => $empCashier->id, 'shift_id' => $shiftChieu->id, 'scheduled_date' => $tomorrow],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'scheduled',
                ],
            );

            // Manager — Ca Sáng hôm qua — absent
            ScheduleAssignment::updateOrCreate(
                ['employee_id' => $empManager->id, 'shift_id' => $shiftSang->id, 'scheduled_date' => $yesterday],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'absent',
                ],
            );

            // ── BLOCK 5: Leave Requests ───────────────────────────────────────────
            LeaveRequest::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $empKitchen->id,
                    'leave_type' => 'annual',
                    'start_date' => Carbon::now()->addWeek()->startOfWeek()->toDateString(),
                ],
                [
                    'end_date' => Carbon::now()->addWeek()->endOfWeek()->toDateString(),
                    'requested_by' => $kitchen->id,
                    'reason' => 'Xin nghỉ phép năm để về thăm gia đình',
                    'status' => 'pending',
                ],
            );

            LeaveRequest::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $empCashier->id,
                    'leave_type' => 'sick',
                    'start_date' => $yesterday,
                ],
                [
                    'end_date' => $yesterday,
                    'requested_by' => $cashier->id,
                    'approved_by' => $manager->id,
                    'reason' => 'Bị cảm sốt, cần nghỉ dưỡng bệnh',
                    'status' => 'approved',
                ],
            );

            // ── BLOCK 6: Units, Ingredients & Inventory ──────────────────────────
            $unitKg = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'kg'],
                ['name' => 'Kilogram', 'type' => 'mass'],
            );
            $unitLit = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'lít'],
                ['name' => 'Lít', 'type' => 'volume'],
            );
            $unitCai = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'cái'],
                ['name' => 'Cái', 'type' => 'count'],
            );
            $unitGoi = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'gói'],
                ['name' => 'Gói', 'type' => 'count'],
            );

            $supplier = Supplier::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => 'Công ty Thực phẩm Tươi Ngon'],
                [
                    'branch_id' => $branch->id,
                    'contact_name' => 'Nguyễn Phú Cường',
                    'phone' => '0912000001',
                    'status' => 'active',
                ],
            );

            $ingBeef = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-BEEF'],
                [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Thịt bò',
                    'category_name' => 'Thịt',
                    'average_cost' => 280000,
                    'min_stock_level' => 5,
                    'status' => 'active',
                ],
            );
            $ingFlour = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-FLOUR'],
                [
                    'branch_id' => $branch->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Bột mì',
                    'category_name' => 'Khô',
                    'average_cost' => 25000,
                    'min_stock_level' => 10,
                    'status' => 'active',
                ],
            );
            $ingOil = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-OIL'],
                [
                    'branch_id' => $branch->id,
                    'unit_id' => $unitLit->id,
                    'name' => 'Dầu ăn',
                    'category_name' => 'Dầu mỡ',
                    'average_cost' => 45000,
                    'min_stock_level' => 2,
                    'status' => 'active',
                ],
            );
            $ingLettuce = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-LETTUCE'],
                [
                    'branch_id' => $branch->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Rau xà lách',
                    'category_name' => 'Rau củ',
                    'average_cost' => 30000,
                    'min_stock_level' => 1,
                    'status' => 'active',
                ],
            );
            $ingCheese = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-CHEESE'],
                [
                    'branch_id' => $branch->id,
                    'unit_id' => $unitGoi->id,
                    'name' => 'Phô mai',
                    'category_name' => 'Sữa',
                    'average_cost' => 35000,
                    'min_stock_level' => 5,
                    'status' => 'active',
                ],
            );
            $ingBun = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'TEST-BUN'],
                [
                    'branch_id' => $branch->id,
                    'unit_id' => $unitCai->id,
                    'name' => 'Bánh mì burger',
                    'category_name' => 'Bánh',
                    'average_cost' => 5000,
                    'min_stock_level' => 20,
                    'status' => 'active',
                ],
            );

            // Tồn kho ban đầu
            $invBeef = Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingBeef->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 15.5,
                    'theoretical_quantity' => 15.5,
                    'last_cost' => 280000,
                    'last_counted_at' => now(),
                ],
            );
            $invFlour = Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingFlour->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 50.0,
                    'theoretical_quantity' => 50.0,
                    'last_cost' => 25000,
                    'last_counted_at' => now(),
                ],
            );
            $invOil = Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingOil->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 8.0,
                    'theoretical_quantity' => 8.0,
                    'last_cost' => 45000,
                    'last_counted_at' => now(),
                ],
            );
            $invLettuce = Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingLettuce->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 5.0,
                    'theoretical_quantity' => 5.0,
                    'last_cost' => 30000,
                    'last_counted_at' => now(),
                ],
            );
            Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingCheese->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 20.0,
                    'theoretical_quantity' => 20.0,
                    'last_cost' => 35000,
                    'last_counted_at' => now(),
                ],
            );
            Inventory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingBun->id],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => 100.0,
                    'theoretical_quantity' => 100.0,
                    'last_cost' => 5000,
                    'last_counted_at' => now(),
                ],
            );

            // ── BLOCK 7: Products & BOM ───────────────────────────────────────────
            $catBurger = ProductCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'burger-test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Burger',
                    'display_order' => 10,
                    'status' => 'active',
                ],
            );
            $catDrink = ProductCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'nuoc-uong-test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Đồ uống',
                    'display_order' => 11,
                    'status' => 'active',
                ],
            );
            $catSide = ProductCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'khai-vi-test'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Khai vị',
                    'display_order' => 12,
                    'status' => 'active',
                ],
            );

            $pClassic = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CLASSIC-BURGER'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catBurger->id,
                    'name' => 'Classic Burger',
                    'slug' => 'classic-burger-test',
                    'price' => 85000,
                    'cost_price' => 35000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ],
            );
            $pCheese = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CHEESE-BURGER'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catBurger->id,
                    'name' => 'Cheese Burger',
                    'slug' => 'cheese-burger-test',
                    'price' => 95000,
                    'cost_price' => 40000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ],
            );
            $pSalad = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'SALAD-BO'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catSide->id,
                    'name' => 'Salad Bò',
                    'slug' => 'salad-bo-test',
                    'price' => 75000,
                    'cost_price' => 28000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ],
            );
            $pCola = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'COCA-COLA'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catDrink->id,
                    'name' => 'Coca Cola',
                    'slug' => 'coca-cola-test',
                    'price' => 30000,
                    'cost_price' => 12000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => false,
                ],
            );
            $pFries = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'KHOAI-TAY'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catSide->id,
                    'name' => 'Khoai Tây Chiên',
                    'slug' => 'khoai-tay-chien-test',
                    'price' => 45000,
                    'cost_price' => 15000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ],
            );
            $pSoup = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'SOUP-BO'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catSide->id,
                    'name' => 'Soup Bò',
                    'slug' => 'soup-bo-test',
                    'price' => 55000,
                    'cost_price' => 22000,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ],
            );

            // BOM Recipes
            $this->upsertRecipe($restaurant, $pClassic, $ingBeef, $unitKg, 0.2, 5.0);
            $this->upsertRecipe($restaurant, $pClassic, $ingFlour, $unitKg, 0.1, 2.0);
            $this->upsertRecipe($restaurant, $pClassic, $ingBun, $unitCai, 1.0, 0.0);

            $this->upsertRecipe($restaurant, $pCheese, $ingBeef, $unitKg, 0.2, 5.0);
            $this->upsertRecipe($restaurant, $pCheese, $ingCheese, $unitGoi, 1.0, 0.0);
            $this->upsertRecipe($restaurant, $pCheese, $ingBun, $unitCai, 1.0, 0.0);

            $this->upsertRecipe($restaurant, $pSalad, $ingBeef, $unitKg, 0.15, 3.0);
            $this->upsertRecipe($restaurant, $pSalad, $ingLettuce, $unitKg, 0.1, 10.0);

            $this->upsertRecipe($restaurant, $pFries, $ingFlour, $unitKg, 0.15, 5.0);
            $this->upsertRecipe($restaurant, $pFries, $ingOil, $unitLit, 0.05, 0.0);

            $this->upsertRecipe($restaurant, $pSoup, $ingBeef, $unitKg, 0.2, 5.0);
            $this->upsertRecipe($restaurant, $pSoup, $ingOil, $unitLit, 0.02, 0.0);

            // ── BLOCK 8: Khu vực & Bàn ──────────────────────────────────────────
            $areaIn = Area::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'TRONG-NHA'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Khu Trong Nhà',
                    'display_order' => 1,
                    'status' => 'active',
                ],
            );
            $areaOut = Area::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'NGOAI-TROI'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Khu Ngoài Trời',
                    'display_order' => 2,
                    'status' => 'active',
                ],
            );

            $tables = [];
            for ($i = 1; $i <= 5; $i++) {
                $tables["T0{$i}"] = RestaurantTable::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'area_id' => $areaIn->id, 'name' => "T0{$i}"],
                    [
                        'branch_id' => $branch->id,
                        'qr_code' => "QR-TEST-T0{$i}",
                        'capacity' => 4,
                        'status' => 'available',
                    ],
                );
            }
            for ($i = 6; $i <= 8; $i++) {
                $tables["T0{$i}"] = RestaurantTable::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'area_id' => $areaOut->id, 'name' => "T0{$i}"],
                    [
                        'branch_id' => $branch->id,
                        'qr_code' => "QR-TEST-T0{$i}",
                        'capacity' => 2,
                        'status' => 'available',
                    ],
                );
            }
            $tableOccupied = RestaurantTable::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'area_id' => $areaIn->id, 'name' => 'T09'],
                [
                    'branch_id' => $branch->id,
                    'qr_code' => 'QR-TEST-T09',
                    'capacity' => 4,
                    'status' => 'occupied',
                ],
            );

            // ── BLOCK 9: 10 Completed Orders ─────────────────────────────────────
            // [product list, quantities] cho 10 đơn hàng
            $orderSpecs = [
                [[$pClassic, 2], [$pCola, 2]],
                [[$pCheese, 1], [$pFries, 1]],
                [[$pClassic, 1], [$pSalad, 1]],
                [[$pSoup, 2], [$pCola, 1]],
                [[$pClassic, 2], [$pCheese, 1], [$pFries, 2]],
                [[$pSalad, 2], [$pSoup, 1]],
                [[$pClassic, 3]],
                [[$pCheese, 2], [$pCola, 3]],
                [[$pClassic, 1], [$pFries, 2]],
                [[$pSoup, 1], [$pSalad, 1]],
            ];

            $tableKeys = array_keys($tables);
            foreach ($orderSpecs as $idx => $items) {
                $num = str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
                $tableKey = $tableKeys[$idx % count($tableKeys)];
                $table = $tables[$tableKey];
                $daysAgo = $idx < 5 ? 7 : 3;
                $completedAt = Carbon::now()->subDays($daysAgo)->setTime(12 + $idx, 0);

                $subtotal = 0;
                foreach ($items as [$product, $qty]) {
                    $subtotal += $product->price * $qty;
                }

                $order = Order::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'order_number' => "ORD-TEST-{$num}"],
                    [
                        'branch_id' => $branch->id,
                        'table_id' => $table->id,
                        'created_by' => $cashier->id,
                        'cashier_user_id' => $cashier->id,
                        'channel' => 'dine_in',
                        'status' => 'completed',
                        'payment_status' => 'paid',
                        'subtotal' => $subtotal,
                        'discount_amount' => 0,
                        'service_charge' => 0,
                        'tax_amount' => 0,
                        'total_amount' => $subtotal,
                        'confirmed_at' => $completedAt->copy()->subMinutes(20),
                        'completed_at' => $completedAt,
                        'created_at' => $completedAt->copy()->subMinutes(30),
                    ],
                );

                foreach ($items as [$product, $qty]) {
                    OrderItem::updateOrCreate(
                        ['order_id' => $order->id, 'product_id' => $product->id],
                        [
                            'restaurant_id' => $restaurant->id,
                            'quantity' => $qty,
                            'unit_price' => $product->price,
                            'line_total' => $product->price * $qty,
                            'status' => 'served',
                        ],
                    );
                }

                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'restaurant_id' => $restaurant->id,
                        'branch_id' => $branch->id,
                        'processed_by' => $cashier->id,
                        'transaction_code' => "PAY-TEST-{$num}",
                        'payment_method' => 'cash',
                        'status' => 'paid',
                        'amount' => $subtotal,
                        'cash_received' => $subtotal,
                        'change_amount' => 0,
                        'paid_at' => $completedAt,
                    ],
                );
            }

            // Order đang pending (để test áp dụng promotion & split)
            $orderPending = Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-TEST-PENDING'],
                [
                    'branch_id' => $branch->id,
                    'table_id' => $tableOccupied->id,
                    'created_by' => $cashier->id,
                    'cashier_user_id' => $cashier->id,
                    'channel' => 'dine_in',
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'subtotal' => 220000,
                    'discount_amount' => 0,
                    'service_charge' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 220000,
                    'confirmed_at' => Carbon::now()->subMinutes(15),
                ],
            );
            OrderItem::updateOrCreate(
                ['order_id' => $orderPending->id, 'product_id' => $pCheese->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'quantity' => 2,
                    'unit_price' => $pCheese->price,
                    'line_total' => $pCheese->price * 2,
                    'status' => 'preparing',
                ],
            );
            OrderItem::updateOrCreate(
                ['order_id' => $orderPending->id, 'product_id' => $pCola->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'quantity' => 1,
                    'unit_price' => $pCola->price,
                    'line_total' => $pCola->price,
                    'status' => 'served',
                ],
            );

            // ── BLOCK 10: Inventory Transactions ─────────────────────────────────
            // Nhập hàng (7 ngày trước)
            $purchaseAt = Carbon::now()->subDays(7);
            InventoryTransaction::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingBeef->id, 'type' => 'purchase', 'occurred_at' => $purchaseAt],
                [
                    'branch_id' => $branch->id,
                    'inventory_id' => $invBeef->id,
                    'performed_by' => $manager->id,
                    'supplier_id' => $supplier->id,
                    'direction' => 'in',
                    'quantity' => 20,
                    'unit_cost' => 280000,
                    'total_cost' => 5600000,
                    'notes' => 'Nhập thịt bò cho tuần demo',
                ],
            );
            InventoryTransaction::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingFlour->id, 'type' => 'purchase', 'occurred_at' => $purchaseAt],
                [
                    'branch_id' => $branch->id,
                    'inventory_id' => $invFlour->id,
                    'performed_by' => $manager->id,
                    'direction' => 'in',
                    'quantity' => 50,
                    'unit_cost' => 25000,
                    'total_cost' => 1250000,
                    'notes' => 'Nhập bột mì cho tuần demo',
                ],
            );
            InventoryTransaction::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingOil->id, 'type' => 'purchase', 'occurred_at' => $purchaseAt],
                [
                    'branch_id' => $branch->id,
                    'inventory_id' => $invOil->id,
                    'performed_by' => $manager->id,
                    'direction' => 'in',
                    'quantity' => 10,
                    'unit_cost' => 45000,
                    'total_cost' => 450000,
                    'notes' => 'Nhập dầu ăn cho tuần demo',
                ],
            );

            // Ghi hao hụt (3 ngày trước) — do nhân viên kitchen, trong ca CA_SANG
            $wasteAt = Carbon::now()->subDays(3)->setTime(10, 0); // 10:00 sáng = trong ca 06-14
            $wasteTransaction = InventoryTransaction::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'ingredient_id' => $ingLettuce->id, 'type' => 'waste', 'occurred_at' => $wasteAt],
                [
                    'branch_id' => $branch->id,
                    'inventory_id' => $invLettuce->id,
                    'performed_by' => $kitchen->id,
                    'direction' => 'out',
                    'quantity' => 0.5,
                    'unit_cost' => 30000,
                    'total_cost' => 15000,
                    'notes' => 'Rau bị dập nát, không dùng được',
                ],
            );
            // Cập nhật tồn kho sau hao hụt
            $invLettuce->decrement('quantity_on_hand', 0.5);

            // ── BLOCK 11: Shift Closings ──────────────────────────────────────────
            // Ca hôm qua — thiếu quỹ 50k (để test salary deduction)
            $closingShort = ShiftClosing::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'shift_id' => $shiftSang->id,
                    'closing_date' => $yesterday,
                ],
                [
                    'branch_id' => $branch->id,
                    'cashier_user_id' => $cashier->id,
                    'confirmed_by' => $manager->id,
                    'expected_cash' => 815000,
                    'actual_cash' => 765000,
                    'cash_difference' => -50000,
                    'transfer_amount' => 0,
                    'other_expense_amount' => 0,
                    'notes' => 'Ca sáng hôm qua — thiếu quỹ 50,000đ',
                    'status' => 'confirmed',
                    'closed_at' => Carbon::yesterday()->setTime(14, 5),
                ],
            );

            // Ca chiều hôm qua — cân bằng
            ShiftClosing::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'shift_id' => $shiftChieu->id,
                    'closing_date' => $yesterday,
                ],
                [
                    'branch_id' => $branch->id,
                    'cashier_user_id' => $cashier->id,
                    'confirmed_by' => $manager->id,
                    'expected_cash' => 360000,
                    'actual_cash' => 360000,
                    'cash_difference' => 0,
                    'transfer_amount' => 0,
                    'other_expense_amount' => 0,
                    'notes' => 'Ca chiều hôm qua — cân bằng',
                    'status' => 'confirmed',
                    'closed_at' => Carbon::yesterday()->setTime(22, 10),
                ],
            );

            // ── BLOCK 12: Salaries ────────────────────────────────────────────────
            $periodStart = Carbon::now()->startOfMonth()->format('Y-m-d');
            $periodEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

            foreach ([
                [$empOwner,   15000000],
                [$empManager, 12000000],
                [$empCashier, 8000000],
                [$empKitchen, 9000000],
            ] as [$emp, $base]) {
                // Use raw DB to avoid Eloquent date cast mismatch with unique constraint
                $existing = DB::table('salaries')
                    ->where('employee_id', $emp->id)
                    ->where('pay_period_start', $periodStart)
                    ->where('pay_period_end', $periodEnd)
                    ->first();

                if ($existing) {
                    DB::table('salaries')->where('id', $existing->id)->update([
                        'restaurant_id' => $restaurant->id,
                        'base_salary' => $base,
                        'bonus_amount' => 0,
                        'deduction_amount' => 0,
                        'net_salary' => $base,
                        'status' => 'draft',
                        'updated_at' => now(),
                    ]);
                    $sal = Salary::withoutGlobalScopes()->find($existing->id);
                } else {
                    $id = DB::table('salaries')->insertGetId([
                        'restaurant_id' => $restaurant->id,
                        'employee_id' => $emp->id,
                        'pay_period_start' => $periodStart,
                        'pay_period_end' => $periodEnd,
                        'base_salary' => $base,
                        'bonus_amount' => 0,
                        'deduction_amount' => 0,
                        'net_salary' => $base,
                        'status' => 'draft',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $sal = Salary::withoutGlobalScopes()->find($id);
                }

                // Tự sweep để tự động cấn trừ cash_shortage & inventory_loss
                $this->salary->sweepAdjustments($sal, $emp);
            }

            // ── BLOCK 13: Customers ───────────────────────────────────────────────
            Customer::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'phone' => '0912345678'],
                [
                    'branch_id' => $branch->id,
                    'full_name' => 'Nguyễn Minh Hùng',
                    'email' => 'hung@example.com',
                    'loyalty_points' => 150,
                    'last_order_at' => Carbon::now()->subDays(3),
                ],
            );
            Customer::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'phone' => '0987654321'],
                [
                    'branch_id' => $branch->id,
                    'full_name' => 'Trần Thị Lan',
                    'email' => 'lan@example.com',
                    'loyalty_points' => 80,
                    'last_order_at' => Carbon::now()->subWeek(),
                ],
            );
            Customer::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'phone' => '0911222333'],
                [
                    'branch_id' => $branch->id,
                    'full_name' => 'Lê Văn Phong',
                    'loyalty_points' => 0,
                ],
            );

            // ── BLOCK 14: Promotions ──────────────────────────────────────────────
            Promotion::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'WELCOME10'],
                [
                    'name' => 'Chào mừng khách mới — Giảm 10%',
                    'type' => 'percent',
                    'value' => 10,
                    'min_order_amount' => 100000,
                    'max_discount_amount' => 50000,
                    'is_active' => true,
                    'is_approved' => true,
                    'created_by' => $owner->id,
                    'approved_by' => $owner->id,
                ],
            );
            Promotion::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'SAVE50K'],
                [
                    'name' => 'Giảm 50,000đ cho đơn từ 200k',
                    'type' => 'fixed_amount',
                    'value' => 50000,
                    'min_order_amount' => 200000,
                    'max_discount_amount' => 0,
                    'is_active' => true,
                    'is_approved' => true,
                    'created_by' => $owner->id,
                    'approved_by' => $owner->id,
                ],
            );
            Promotion::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'PENDING20'],
                [
                    'name' => 'Giảm 20% cuối tuần (chờ duyệt)',
                    'type' => 'percent',
                    'value' => 20,
                    'min_order_amount' => 0,
                    'max_discount_amount' => 100000,
                    'is_active' => true,
                    'is_approved' => false,
                    'created_by' => $manager->id,
                    'approved_by' => null,
                ],
            );

            // ── BLOCK 15: Customer Feedback ───────────────────────────────────────
            $orderDemo = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('order_number', 'ORD-TEST-001')
                ->first();

            CustomerFeedback::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_id' => $orderDemo?->id, 'rating' => 5],
                [
                    'branch_id' => $branch->id,
                    'order_id' => $orderDemo?->id,
                    'submitted_by_name' => 'Nguyễn Minh Hùng',
                    'submitted_by_phone' => '0912345678',
                    'rating' => 5,
                    'content' => 'Burger ngon tuyệt! Phục vụ nhiệt tình, chắc chắn quay lại.',
                    'is_anonymous' => false,
                    'status' => 'new',
                ],
            );
            CustomerFeedback::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'submitted_by_phone' => null, 'rating' => 2, 'is_anonymous' => true],
                [
                    'branch_id' => $branch->id,
                    'rating' => 2,
                    'content' => 'Phục vụ chậm, thái độ nhân viên không thân thiện. Chờ 30 phút mới có đồ ăn.',
                    'is_anonymous' => true,
                    'status' => 'new',
                ],
            );
            CustomerFeedback::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'submitted_by_name' => 'Khách hàng tức giận', 'rating' => 1],
                [
                    'branch_id' => $branch->id,
                    'submitted_by_name' => 'Khách hàng tức giận',
                    'submitted_by_phone' => '0933111222',
                    'rating' => 1,
                    'content' => 'Thức ăn bị sống, ăn vào đau bụng. Yêu cầu bồi thường!',
                    'is_anonymous' => false,
                    'status' => 'new',
                ],
            );
            CustomerFeedback::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'submitted_by_name' => 'Trần Thị Lan', 'rating' => 4],
                [
                    'branch_id' => $branch->id,
                    'submitted_by_name' => 'Trần Thị Lan',
                    'submitted_by_phone' => '0987654321',
                    'rating' => 4,
                    'content' => 'Ổn, sẽ quay lại lần sau.',
                    'is_anonymous' => false,
                    'status' => 'resolved',
                    'resolution_notes' => 'Đã gọi điện xin lỗi và tặng voucher giảm giá',
                    'compensation_voucher' => 'WELCOME10',
                ],
            );

            // ── BLOCK 16: Violation Reports ───────────────────────────────────────
            $violation1 = ViolationReport::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $empCashier->id,
                    'violation_type' => 'Gian lận tiền thu',
                ],
                [
                    'reported_by' => $manager->id,
                    'is_anonymous' => false,
                    'severity' => 'high',
                    'description' => 'Phát hiện thu thiếu tiền khách hàng ít nhất 2 lần trong tháng, tổng 150,000đ',
                    'penalty_amount' => 500000,
                    'occurred_at' => Carbon::now()->subDays(5),
                    'status' => 'open',
                ],
            );
            ViolationReport::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $empKitchen->id,
                    'violation_type' => 'Không tuân thủ vệ sinh an toàn thực phẩm',
                ],
                [
                    'reported_by' => $cashier->id,
                    'is_anonymous' => true,
                    'severity' => 'medium',
                    'description' => 'Nhân viên không đeo găng tay khi chế biến thực phẩm, không đeo khẩu trang',
                    'penalty_amount' => 0,
                    'occurred_at' => Carbon::now()->subDays(2),
                    'status' => 'open',
                ],
            );
            // Vi phạm đã xử lý — có salary adjustment
            $violationResolved = ViolationReport::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $empCashier->id,
                    'violation_type' => 'Đi trễ thường xuyên',
                ],
                [
                    'reported_by' => $manager->id,
                    'is_anonymous' => false,
                    'severity' => 'low',
                    'description' => 'Đi trễ hơn 15 phút trong 3 ca liên tiếp',
                    'penalty_amount' => 200000,
                    'occurred_at' => Carbon::now()->subDays(10),
                    'status' => 'resolved',
                ],
            );
            // Tạo SalaryAdjustment cho vi phạm đã resolved
            $cashierSalary = Salary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('employee_id', $empCashier->id)
                ->where('pay_period_start', $periodStart)
                ->first();

            if ($cashierSalary) {
                $existsViolation = SalaryAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $empCashier->id)
                    ->where('reference_id', $violationResolved->id)
                    ->where('reference_type', ViolationReport::class)
                    ->exists();

                if (! $existsViolation) {
                    $this->salary->addAdjustment($cashierSalary, [
                        'employee_id' => $empCashier->id,
                        'type' => 'violation',
                        'amount' => 200000,
                        'reason' => 'Phạt đi trễ: '.$violationResolved->violation_type,
                        'reference_id' => $violationResolved->id,
                        'reference_type' => ViolationReport::class,
                    ]);
                }
            }

            // ── BLOCK 17: Approval Requests ───────────────────────────────────────
            $ingBeef->refresh();
            ApprovalRequest::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'requester_id' => $manager->id,
                    'operation_type' => 'inventory_purchase',
                    'status' => 'pending',
                ],
                [
                    'branch_id' => $branch->id,
                    'operation_data' => [
                        'ingredient_id' => $ingBeef->id,
                        'ingredient_name' => 'Thịt bò',
                        'quantity' => 10,
                        'unit_cost' => 285000,
                        'supplier_id' => null,
                        'occurred_at' => now()->toDateTimeString(),
                        'notes' => 'Nhập thêm thịt bò tuần mới',
                    ],
                    'status' => 'pending',
                ],
            );

            if ($cashierSalary) {
                ApprovalRequest::updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'requester_id' => $manager->id,
                        'operation_type' => 'salary_adjustment',
                        'status' => 'pending',
                    ],
                    [
                        'branch_id' => $branch->id,
                        'operation_data' => [
                            'salary_id' => $cashierSalary->id,
                            'type' => 'bonus',
                            'amount' => 500000,
                            'reason' => 'Thưởng tháng — Hoàn thành xuất sắc nhiệm vụ',
                        ],
                        'status' => 'pending',
                    ],
                );
            }

            // ── Audit log tổng kết ────────────────────────────────────────────────
            AuditLog::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'user_id' => $owner->id,
                'user_role' => 'owner',
                'event' => 'created',
                'action' => 'test_data_seeded',
                'subject_type' => Restaurant::class,
                'subject_id' => $restaurant->id,
                'old_values' => null,
                'new_values' => ['seeder' => 'TestDataSeeder', 'blocks' => 17],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'database-seeder',
            ]);
        });
    }

    private function upsertRecipe(Restaurant $restaurant, Product $product, Ingredient $ingredient, Unit $unit, float $qty, float $wasteRate): void
    {
        ProductRecipe::updateOrCreate(
            ['product_id' => $product->id, 'ingredient_id' => $ingredient->id],
            [
                'restaurant_id' => $restaurant->id,
                'unit_id' => $unit->id,
                'quantity' => $qty,
                'waste_rate' => $wasteRate,
            ],
        );
    }
}
