<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Customer;
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
use App\Models\RestaurantBranch;
use App\Models\RestaurantRevenueSummary;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedRestaurantDemoData extends Command
{
    protected $signature = 'seed:restaurant-demo {--email=tamh77573@gmail.com : The email of the restaurant owner}';

    protected $description = 'Seed rich, highly-realistic sample data for a specific restaurant to populate the dashboard charts, forecast, shifts, tables, and AI alerts.';

    public function handle()
    {
        $email = $this->option('email');
        $this->info("Starting demo seeding for restaurant owner: {$email}");

        $owner = User::where('email', $email)->first();
        if (! $owner) {
            $this->error("User with email {$email} not found!");

            return Command::FAILURE;
        }

        $restaurant = $owner->restaurant;
        if (! $restaurant) {
            $this->error("No restaurant found for user {$owner->name} ({$email})!");

            return Command::FAILURE;
        }

        $this->info("Found Restaurant: {$restaurant->name} (ID: {$restaurant->id})");

        DB::transaction(function () use ($owner, $restaurant) {
            // 1. Setup Branch
            $branch = RestaurantBranch::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'email' => $owner->email],
                [
                    'code' => 'MAIN-CN',
                    'name' => 'Chi nhánh Trung tâm',
                    'phone' => $owner->phone ?? '0987654321',
                    'address' => '123 Đường Lê Lợi, Quận 1, TP. Hồ Chí Minh',
                    'manager_user_id' => $owner->id,
                    'status' => 'active',
                ]
            );
            $this->info("Branch created/loaded: {$branch->name} (ID: {$branch->id})");

            $owner->forceFill(['branch_id' => $branch->id])->save();

            // Ensure roles exist
            $roles = ['manager', 'cashier', 'kitchen', 'inventory_staff'];
            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }

            // 2. Setup Staff Users
            $managerUser = $this->upsertStaffUser('manager_vq@aventura.local', 'Lê Văn Quản', '0912345678', 'manager', $restaurant, $branch);
            $cashierUser = $this->upsertStaffUser('cashier_vq@aventura.local', 'Nguyễn Thu Ngân', '0922345678', 'cashier', $restaurant, $branch);
            $kitchenUser = $this->upsertStaffUser('kitchen_vq@aventura.local', 'Trần Đầu Bếp', '0932345678', 'kitchen', $restaurant, $branch);
            $inventoryUser = $this->upsertStaffUser('inventory_vq@aventura.local', 'Phạm Thủ Kho', '0942345678', 'inventory_staff', $restaurant, $branch);
            $this->info('Staff Users created/loaded.');

            // 3. Setup Employee Records
            $employees = [
                'owner' => $this->upsertEmployee($restaurant, $branch, $owner, 'EMP-VQ-001', 'Chủ nhà hàng', 15000000, 2),
                'manager' => $this->upsertEmployee($restaurant, $branch, $managerUser, 'EMP-VQ-002', 'Quản lý', 12000000, 3),
                'cashier' => $this->upsertEmployee($restaurant, $branch, $cashierUser, 'EMP-VQ-003', 'Thu ngân', 8000000, 4),
                'kitchen' => $this->upsertEmployee($restaurant, $branch, $kitchenUser, 'EMP-VQ-004', 'Đầu bếp chính', 9500000, 5),
                'inventory' => $this->upsertEmployee($restaurant, $branch, $inventoryUser, 'EMP-VQ-005', 'Thủ kho', 8500000, 6),
            ];
            $this->info('Employee records updated.');

            // 4. Setup Layout (Areas & Tables)
            $areaMain = Area::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'SANH-A'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Khu Vực Sảnh A',
                    'display_order' => 1,
                    'status' => 'active',
                ]
            );
            $areaGarden = Area::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'GARDEN-B'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Khu Sân Vườn B',
                    'display_order' => 2,
                    'status' => 'active',
                ]
            );

            // Re-link or create Tables
            $tablesData = [
                ['name' => 'T1', 'capacity' => 2, 'area' => $areaMain, 'status' => 'available'],
                ['name' => 'T2', 'capacity' => 4, 'area' => $areaMain, 'status' => 'occupied'],
                ['name' => 'T3', 'capacity' => 4, 'area' => $areaMain, 'status' => 'available'],
                ['name' => 'T4', 'capacity' => 6, 'area' => $areaGarden, 'status' => 'available'],
                ['name' => 'T5', 'capacity' => 8, 'area' => $areaGarden, 'status' => 'occupied'],
            ];

            $tables = [];
            foreach ($tablesData as $idx => $t) {
                $tables[$t['name']] = RestaurantTable::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $t['name']],
                    [
                        'branch_id' => $branch->id,
                        'area_id' => $t['area']->id,
                        'qr_code' => 'QR-'.$t['name'].'-'.$restaurant->id,
                        'capacity' => $t['capacity'],
                        'status' => $t['status'],
                    ]
                );
            }
            $this->info('Areas & Tables set up.');

            // 5. Units, Ingredients, Inventory Levels
            $unitKg = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'kg'],
                ['name' => 'Kilogram', 'type' => 'mass']
            );
            $unitLit = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'lít'],
                ['name' => 'Lít', 'type' => 'volume']
            );
            $unitCai = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'cái'],
                ['name' => 'Cái', 'type' => 'count']
            );
            $unitLy = Unit::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'symbol' => 'ly'],
                ['name' => 'Ly', 'type' => 'count']
            );

            $supplier = Supplier::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => 'Công ty Thực phẩm Đồng Xanh'],
                [
                    'branch_id' => $branch->id,
                    'contact_name' => 'Trần Văn Sạch',
                    'phone' => '0901239876',
                    'status' => 'active',
                ]
            );

            $ingBeef = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'ING-VQ-BEEF'],
                [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Thịt Bò Mỹ',
                    'category_name' => 'Thịt tươi',
                    'average_cost' => 290000,
                    'min_stock_level' => 15,
                    'status' => 'active',
                ]
            );

            $ingRice = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'ING-VQ-RICE'],
                [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Gạo Thơm ST25',
                    'category_name' => 'Lương thực',
                    'average_cost' => 28000,
                    'min_stock_level' => 30,
                    'status' => 'active',
                ]
            );

            $ingTea = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'ING-VQ-TEA'],
                [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Hồng Trà Thượng Hạng',
                    'category_name' => 'Trà & Café',
                    'average_cost' => 120000,
                    'min_stock_level' => 5,
                    'status' => 'active',
                ]
            );

            $ingLime = Ingredient::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sku' => 'ING-VQ-LIME'],
                [
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'unit_id' => $unitKg->id,
                    'name' => 'Chanh tươi',
                    'category_name' => 'Rau củ',
                    'average_cost' => 15000,
                    'min_stock_level' => 10,
                    'status' => 'active',
                ]
            );

            // Seed stock quantities. NOTE: Beef and Lime will be seeded BELOW min stock level to trigger the Dashboard low stock alerts!
            $inventories = [
                ['ing' => $ingBeef, 'qty' => 8.5, 'cost' => 290000],  // min_stock_level is 15 => Trigger Warning!
                ['ing' => $ingRice, 'qty' => 45.0, 'cost' => 28000],
                ['ing' => $ingTea, 'qty' => 12.0, 'cost' => 120000],
                ['ing' => $ingLime, 'qty' => 4.2, 'cost' => 15000],   // min_stock_level is 10 => Trigger Warning!
            ];

            foreach ($inventories as $inv) {
                Inventory::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'ingredient_id' => $inv['ing']->id],
                    [
                        'branch_id' => $branch->id,
                        'quantity_on_hand' => $inv['qty'],
                        'theoretical_quantity' => $inv['qty'],
                        'last_cost' => $inv['cost'],
                        'last_counted_at' => now(),
                        'updated_by' => $inventoryUser->id,
                    ]
                );
            }
            $this->info('Ingredients and Inventory level loaded (Alerts triggered purposefully).');

            // 6. Categories and Products setup
            $catMain = ProductCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'mon-chinh-demo'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Món chính',
                    'display_order' => 1,
                    'status' => 'active',
                ]
            );
            $catDrink = ProductCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'do-uong-demo'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Đồ uống',
                    'display_order' => 2,
                    'status' => 'active',
                ]
            );

            // Map existing products of restaurant 12 to categories and branch
            $existingProducts = Product::where('restaurant_id', $restaurant->id)->get();
            foreach ($existingProducts as $p) {
                if ($p->code == 'PHO-BO' || str_contains(strtolower($p->name), 'pho') || str_contains(strtolower($p->name), 'suon')) {
                    $p->update([
                        'category_id' => $catMain->id,
                        'branch_id' => $branch->id,
                        'is_active' => true,
                        'is_available' => true,
                        'track_inventory' => true,
                    ]);
                } else {
                    $p->update([
                        'category_id' => $catDrink->id,
                        'branch_id' => $branch->id,
                        'is_active' => true,
                        'is_available' => true,
                        'track_inventory' => true,
                    ]);
                }
            }

            // Ensure our default 3 exist with nice Vietnamese titles
            $phoBo = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'PHO-BO-VIP'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catMain->id,
                    'name' => 'Phở Bò Đặc Biệt',
                    'slug' => 'pho-bo-dac-biet-demo',
                    'price' => 65000,
                    'cost_price' => 25000,
                    'preparation_time_minutes' => 10,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ]
            );

            $comSuon = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'COM-SUON-VIP'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catMain->id,
                    'name' => 'Cơm Sườn Nướng Lu',
                    'slug' => 'com-suon-nuong-lu-demo',
                    'price' => 55000,
                    'cost_price' => 20000,
                    'preparation_time_minutes' => 12,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ]
            );

            $traChanh = Product::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'TRA-CHANH-VIP'],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $catDrink->id,
                    'name' => 'Trà Chanh Sả Mật Ong',
                    'slug' => 'tra-chanh-sa-mat-ong-demo',
                    'price' => 25000,
                    'cost_price' => 8000,
                    'preparation_time_minutes' => 5,
                    'is_active' => true,
                    'is_available' => true,
                    'track_inventory' => true,
                ]
            );

            // BOM
            ProductRecipe::updateOrCreate(
                ['product_id' => $phoBo->id, 'ingredient_id' => $ingBeef->id],
                ['restaurant_id' => $restaurant->id, 'unit_id' => $unitKg->id, 'quantity' => 0.15, 'waste_rate' => 2.0]
            );
            ProductRecipe::updateOrCreate(
                ['product_id' => $comSuon->id, 'ingredient_id' => $ingRice->id],
                ['restaurant_id' => $restaurant->id, 'unit_id' => $unitKg->id, 'quantity' => 0.2, 'waste_rate' => 1.0]
            );
            ProductRecipe::updateOrCreate(
                ['product_id' => $traChanh->id, 'ingredient_id' => $ingTea->id],
                ['restaurant_id' => $restaurant->id, 'unit_id' => $unitKg->id, 'quantity' => 0.01, 'waste_rate' => 0.0]
            );
            ProductRecipe::updateOrCreate(
                ['product_id' => $traChanh->id, 'ingredient_id' => $ingLime->id],
                ['restaurant_id' => $restaurant->id, 'unit_id' => $unitKg->id, 'quantity' => 0.03, 'waste_rate' => 5.0]
            );
            $this->info('Products catalog & BOM recipes configured.');

            // 7. Work shifts
            $shiftSang = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CA-SANG-VQ'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Ca Sáng (06:00 - 14:00)',
                    'start_time' => '06:00:00',
                    'end_time' => '14:00:00',
                    'is_overnight' => false,
                    'status' => 'active',
                ]
            );
            $shiftChieu = WorkShift::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'code' => 'CA-CHIEU-VQ'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Ca Chiều (14:00 - 22:00)',
                    'start_time' => '14:00:00',
                    'end_time' => '22:00:00',
                    'is_overnight' => false,
                    'status' => 'active',
                ]
            );
            $this->info('Work Shifts created.');

            // 8. Customers
            $customers = [];
            $customerNames = ['Đặng Văn Lâm', 'Lê Công Vinh', 'Nguyễn Tiến Linh', 'Bùi Tiến Dũng', 'Quang Hải'];
            $customerPhones = ['0988776655', '0977665544', '0966554433', '0955443322', '0944332211'];

            foreach ($customerNames as $k => $cName) {
                $customers[] = Customer::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'phone' => $customerPhones[$k]],
                    [
                        'branch_id' => $branch->id,
                        'full_name' => $cName,
                        'loyalty_points' => rand(50, 300),
                        'last_order_at' => now()->subDays(rand(0, 5)),
                    ]
                );
            }

            // 9. Historical Sales - 28 Days
            $this->info('Seeding 28 days of orders and daily revenue summaries...');
            $orderCounter = 1;

            for ($d = 28; $d >= 0; $d--) {
                $targetDate = now()->subDays($d);
                $dateStr = $targetDate->toDateString();

                // Skip future dates if timezone goes ahead
                if ($targetDate->isAfter(now())) {
                    continue;
                }

                // Skip creating actual orders for today, we'll create active real-time orders below
                if ($d == 0) {
                    continue;
                }

                $orderCount = rand(4, 8);
                $dailyRevenue = 0;
                $dailyCogs = 0;
                $cashRevenue = 0;
                $bankRevenue = 0;

                for ($oIdx = 0; $oIdx < $orderCount; $oIdx++) {
                    $orderNum = 'ORD-VQ-'.str_pad($orderCounter++, 5, '0', STR_PAD_LEFT);
                    $table = $tables[array_rand($tables)];
                    $customer = $customers[array_rand($customers)];

                    // Pick random items
                    $itemsSpec = [];
                    if (rand(0, 1) == 0) {
                        $itemsSpec[] = ['p' => $phoBo, 'qty' => rand(1, 3)];
                    }
                    if (rand(0, 1) == 0) {
                        $itemsSpec[] = ['p' => $comSuon, 'qty' => rand(1, 2)];
                    }
                    if (empty($itemsSpec) || rand(0, 1) == 0) {
                        $itemsSpec[] = ['p' => $traChanh, 'qty' => rand(1, 4)];
                    }

                    $subtotal = 0;
                    $cogs = 0;
                    foreach ($itemsSpec as $spec) {
                        $subtotal += $spec['p']->price * $spec['qty'];
                        $cogs += $spec['p']->cost_price * $spec['qty'];
                    }

                    $orderTime = $targetDate->copy()->setTime(rand(11, 21), rand(0, 59));

                    $order = Order::updateOrCreate(
                        ['restaurant_id' => $restaurant->id, 'order_number' => $orderNum],
                        [
                            'branch_id' => $branch->id,
                            'table_id' => $table->id,
                            'customer_id' => $customer->id,
                            'created_by' => $cashierUser->id,
                            'cashier_user_id' => $cashierUser->id,
                            'channel' => array_rand(['dine_in' => 0, 'takeaway' => 0, 'delivery' => 0, 'qr' => 0]),
                            'status' => 'completed',
                            'payment_status' => 'paid',
                            'subtotal' => $subtotal,
                            'discount_amount' => 0,
                            'service_charge' => 0,
                            'tax_amount' => 0,
                            'total_amount' => $subtotal,
                            'confirmed_at' => $orderTime->copy()->subMinutes(15),
                            'completed_at' => $orderTime,
                            'created_at' => $orderTime->copy()->subMinutes(20),
                        ]
                    );

                    foreach ($itemsSpec as $spec) {
                        OrderItem::updateOrCreate(
                            ['order_id' => $order->id, 'product_id' => $spec['p']->id],
                            [
                                'restaurant_id' => $restaurant->id,
                                'quantity' => $spec['qty'],
                                'unit_price' => $spec['p']->price,
                                'line_total' => $spec['p']->price * $spec['qty'],
                                'status' => 'served',
                                'sent_to_kitchen_at' => $orderTime->copy()->subMinutes(14),
                                'prepared_at' => $orderTime->copy()->subMinutes(5),
                                'served_at' => $orderTime->copy()->subMinutes(2),
                            ]
                        );
                    }

                    $method = rand(0, 2) > 0 ? 'bank_transfer' : 'cash';
                    Payment::updateOrCreate(
                        ['order_id' => $order->id],
                        [
                            'restaurant_id' => $restaurant->id,
                            'branch_id' => $branch->id,
                            'processed_by' => $cashierUser->id,
                            'transaction_code' => 'TXN-'.$order->order_number,
                            'payment_method' => $method,
                            'status' => 'paid',
                            'amount' => $subtotal,
                            'cash_received' => $subtotal,
                            'change_amount' => 0,
                            'paid_at' => $orderTime,
                        ]
                    );

                    $dailyRevenue += $subtotal;
                    $dailyCogs += $cogs;
                    if ($method == 'cash') {
                        $cashRevenue += $subtotal;
                    } else {
                        $bankRevenue += $subtotal;
                    }
                }

                // Save RestaurantRevenueSummary for this day
                $grossProfit = $dailyRevenue - $dailyCogs;
                RestaurantRevenueSummary::updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'branch_id' => $branch->id,
                        'summary_type' => 'daily',
                        'summary_date' => $dateStr,
                    ],
                    [
                        'scope_key' => 'branch_'.$branch->id,
                        'order_count' => $orderCount,
                        'completed_order_count' => $orderCount,
                        'cancelled_order_count' => 0,
                        'gross_revenue' => $dailyRevenue,
                        'discount_total' => 0,
                        'service_charge_total' => 0,
                        'tax_total' => 0,
                        'refund_total' => 0,
                        'net_revenue' => $dailyRevenue,
                        'cash_revenue' => $cashRevenue,
                        'bank_transfer_revenue' => $bankRevenue,
                        'card_revenue' => 0,
                        'ewallet_revenue' => 0,
                        'mixed_revenue' => 0,
                        'cogs_amount' => $dailyCogs,
                        'gross_profit' => $grossProfit,
                        'average_order_value' => $orderCount > 0 ? ($dailyRevenue / $orderCount) : 0,
                        'calculated_at' => now(),
                        'source' => 'system',
                    ]
                );
            }
            $this->info('Completed seeding 28 days of historical data!');

            // 10. REAL-TIME TODAY'S DATA (Pending, Preparing, Completed, Cancelled orders)
            $this->info("Adding today's live orders to trigger alerts...");

            // Completed Order 1 (Today morning)
            $today = Carbon::today();
            $ordToday1 = Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-VQ-TODAY-001'],
                [
                    'branch_id' => $branch->id,
                    'table_id' => $tables['T1']->id,
                    'created_by' => $cashierUser->id,
                    'cashier_user_id' => $cashierUser->id,
                    'channel' => 'dine_in',
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'subtotal' => 145000,
                    'discount_amount' => 0,
                    'service_charge' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 145000,
                    'confirmed_at' => Carbon::now()->subHours(4),
                    'completed_at' => Carbon::now()->subHours(3)->subMinutes(30),
                    'created_at' => Carbon::now()->subHours(4)->subMinutes(10),
                ]
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordToday1->id, 'product_id' => $phoBo->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 2, 'unit_price' => $phoBo->price, 'line_total' => $phoBo->price * 2, 'status' => 'served']
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordToday1->id, 'product_id' => $traChanh->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 1, 'unit_price' => $traChanh->price, 'line_total' => $traChanh->price, 'status' => 'served']
            );
            Payment::updateOrCreate(
                ['order_id' => $ordToday1->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'processed_by' => $cashierUser->id,
                    'transaction_code' => 'TXN-TODAY-001',
                    'payment_method' => 'bank_transfer',
                    'status' => 'paid',
                    'amount' => 145000,
                    'cash_received' => 145000,
                    'paid_at' => Carbon::now()->subHours(3)->subMinutes(30),
                ]
            );

            // Completed Order 2 (Today mid-day)
            $ordToday2 = Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-VQ-TODAY-002'],
                [
                    'branch_id' => $branch->id,
                    'table_id' => $tables['T3']->id,
                    'created_by' => $cashierUser->id,
                    'cashier_user_id' => $cashierUser->id,
                    'channel' => 'qr',
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'subtotal' => 180000,
                    'discount_amount' => 0,
                    'service_charge' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 180000,
                    'confirmed_at' => Carbon::now()->subHours(2),
                    'completed_at' => Carbon::now()->subHour(),
                    'created_at' => Carbon::now()->subHours(2)->subMinutes(15),
                ]
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordToday2->id, 'product_id' => $comSuon->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 2, 'unit_price' => $comSuon->price, 'line_total' => $comSuon->price * 2, 'status' => 'served']
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordToday2->id, 'product_id' => $traChanh->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 2, 'unit_price' => $traChanh->price, 'line_total' => $traChanh->price * 2, 'status' => 'served']
            );
            Payment::updateOrCreate(
                ['order_id' => $ordToday2->id],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'processed_by' => $cashierUser->id,
                    'transaction_code' => 'TXN-TODAY-002',
                    'payment_method' => 'cash',
                    'status' => 'paid',
                    'amount' => 180000,
                    'cash_received' => 200000,
                    'change_amount' => 20000,
                    'paid_at' => Carbon::now()->subHour(),
                ]
            );

            // STUCK ORDER 1: Pending for over 45 minutes (Trigger Dashboard Alert 1: stuck pending)
            Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-VQ-STUCK-PENDING'],
                [
                    'branch_id' => $branch->id,
                    'table_id' => $tables['T2']->id,
                    'created_by' => $cashierUser->id,
                    'channel' => 'dine_in',
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'subtotal' => 90000,
                    'total_amount' => 90000,
                    'created_at' => Carbon::now()->subMinutes(45),
                    'updated_at' => Carbon::now()->subMinutes(45),
                ]
            );

            // STUCK ORDER 2: Preparing for over 1.5 hours (Trigger Dashboard Alert 3: stuck preparing)
            $ordPreparing = Order::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-VQ-STUCK-PREP'],
                [
                    'branch_id' => $branch->id,
                    'table_id' => $tables['T5']->id,
                    'created_by' => $cashierUser->id,
                    'channel' => 'dine_in',
                    'status' => 'preparing',
                    'payment_status' => 'unpaid',
                    'subtotal' => 120000,
                    'total_amount' => 120000,
                    'confirmed_at' => Carbon::now()->subHours(2),
                    'created_at' => Carbon::now()->subHours(2)->subMinutes(10),
                    'updated_at' => Carbon::now()->subHours(2),
                ]
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordPreparing->id, 'product_id' => $phoBo->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 1, 'unit_price' => $phoBo->price, 'line_total' => $phoBo->price, 'status' => 'preparing']
            );
            OrderItem::updateOrCreate(
                ['order_id' => $ordPreparing->id, 'product_id' => $comSuon->id],
                ['restaurant_id' => $restaurant->id, 'quantity' => 1, 'unit_price' => $comSuon->price, 'line_total' => $comSuon->price, 'status' => 'preparing']
            );

            // Save Today's Revenue Summary so far
            $todayRevenue = 145000 + 180000;
            $todayCogs = (25000 * 2 + 8000 * 1) + (20000 * 2 + 8000 * 2);
            $todayGrossProfit = $todayRevenue - $todayCogs;

            RestaurantRevenueSummary::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'summary_type' => 'daily',
                    'summary_date' => $today->toDateString(),
                ],
                [
                    'scope_key' => 'branch_'.$branch->id,
                    'order_count' => 4, // 2 completed + 2 in-progress/stuck
                    'completed_order_count' => 2,
                    'cancelled_order_count' => 0,
                    'gross_revenue' => $todayRevenue,
                    'discount_total' => 0,
                    'service_charge_total' => 0,
                    'tax_total' => 0,
                    'refund_total' => 0,
                    'net_revenue' => $todayRevenue,
                    'cash_revenue' => 180000,
                    'bank_transfer_revenue' => 145000,
                    'card_revenue' => 0,
                    'ewallet_revenue' => 0,
                    'mixed_revenue' => 0,
                    'cogs_amount' => $todayCogs,
                    'gross_profit' => $todayGrossProfit,
                    'average_order_value' => $todayRevenue / 2,
                    'calculated_at' => now(),
                    'source' => 'system',
                ]
            );

            // 11. Today's Schedules (checked_in & scheduled)
            $this->info("Creating today's shift schedules and check-ins...");

            // Cashier - Ca Sáng hôm nay - checked_in (Trigger Shift Feed Alert!)
            ScheduleAssignment::updateOrCreate(
                [
                    'employee_id' => $employees['cashier']->id,
                    'shift_id' => $shiftSang->id,
                    'scheduled_date' => $today->toDateString(),
                ],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'checked_in',
                    'check_in_at' => Carbon::now()->subHours(5),
                ]
            );

            // Kitchen - Ca Sáng hôm nay - checked_in
            ScheduleAssignment::updateOrCreate(
                [
                    'employee_id' => $employees['kitchen']->id,
                    'shift_id' => $shiftSang->id,
                    'scheduled_date' => $today->toDateString(),
                ],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'checked_in',
                    'check_in_at' => Carbon::now()->subHours(4)->subMinutes(55),
                ]
            );

            // Manager - Ca Chiều hôm nay - scheduled (Trigger AI Warning: Overdue checkin if start_time pasts, else active)
            // Let's set the scheduled status
            ScheduleAssignment::updateOrCreate(
                [
                    'employee_id' => $employees['manager']->id,
                    'shift_id' => $shiftChieu->id,
                    'scheduled_date' => $today->toDateString(),
                ],
                [
                    'restaurant_id' => $restaurant->id,
                    'branch_id' => $branch->id,
                    'status' => 'scheduled',
                ]
            );

            // 12. Audit Logs and Feed Activities
            AuditLog::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'user_id' => $owner->id,
                'user_role' => 'owner',
                'event' => 'created',
                'action' => 'setup_branch_demo',
                'subject_type' => RestaurantBranch::class,
                'subject_id' => $branch->id,
                'old_values' => null,
                'new_values' => ['name' => $branch->name],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Aventura Demo Seeder Command',
            ]);

            $this->info('Audit log logged.');
        });

        $this->info("SUCCESS! Demo data seeded successfully for restaurant owner: {$email}");

        return Command::SUCCESS;
    }

    protected function upsertStaffUser(string $email, string $name, string $phone, string $role, Restaurant $restaurant, RestaurantBranch $branch): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password123'),
                'phone' => $phone,
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([$role]);

        return $user;
    }

    protected function upsertEmployee(Restaurant $restaurant, RestaurantBranch $branch, User $user, string $code, string $jobTitle, float $baseSalary, int $roleId): Employee
    {
        return Employee::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'employee_code' => $code],
            [
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'hire_date' => now()->subMonths(6)->toDateString(),
                'employment_type' => 'full_time',
                'job_title' => $jobTitle,
                'base_salary' => $baseSalary,
                'status' => 'active',
                'role_id' => $roleId,
                'citizen_id_number' => '079'.str_pad((string) random_int(1, 99999999), 9, '0', STR_PAD_LEFT),
            ]
        );
    }
}
