<?php

namespace Database\Seeders\Restaurant;

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
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RestaurantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('code', 'FNBVIET-DEMO')->firstOrFail();
        $owner = User::where('email', 'owner@bepso.test')->firstOrFail();

        $manager = $this->upsertStaffUser('manager@bepso.test', 'Manager Demo', '0900000002', 'manager', $restaurant);
        $cashier = $this->upsertStaffUser('cashier@bepso.test', 'Cashier Demo', '0900000003', 'cashier', $restaurant);
        $kitchen = $this->upsertStaffUser('kitchen@bepso.test', 'Kitchen Demo', '0900000004', 'kitchen', $restaurant);
        $inventoryStaff = $this->upsertStaffUser('inventory@bepso.test', 'Inventory Demo', '0900000005', 'inventory_staff', $restaurant);

        $branch = RestaurantBranch::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'Q1'],
            [
                'name' => 'Chi nhanh Quan 1',
                'phone' => '02873000002',
                'email' => 'q1@bepso.test',
                'address' => '1 Nguyen Hue, Quan 1, TP.HCM',
                'manager_user_id' => $manager->id,
                'status' => 'active',
            ],
        );

        foreach ([$owner, $manager, $cashier, $kitchen, $inventoryStaff] as $user) {
            $user->forceFill(['branch_id' => $branch->id])->save();
        }

        $employees = [
            'owner' => $this->upsertEmployee($restaurant, $branch, $owner, 'EMP-001', 'Owner'),
            'manager' => $this->upsertEmployee($restaurant, $branch, $manager, 'EMP-002', 'Manager'),
            'cashier' => $this->upsertEmployee($restaurant, $branch, $cashier, 'EMP-003', 'Cashier'),
            'kitchen' => $this->upsertEmployee($restaurant, $branch, $kitchen, 'EMP-004', 'Kitchen'),
            'inventory' => $this->upsertEmployee($restaurant, $branch, $inventoryStaff, 'EMP-005', 'Inventory Staff'),
        ];

        $area = Area::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'A'],
            [
                'restaurant_id' => $restaurant->id,
                'name' => 'Tang tret',
                'display_order' => 1,
                'status' => 'active',
            ],
        );

        $tableA1 = RestaurantTable::updateOrCreate(
            ['area_id' => $area->id, 'name' => 'A1'],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'qr_code' => 'QR-A1',
                'capacity' => 4,
                'status' => 'occupied',
            ],
        );

        RestaurantTable::updateOrCreate(
            ['area_id' => $area->id, 'name' => 'A2'],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'qr_code' => 'QR-A2',
                'capacity' => 4,
                'status' => 'available',
            ],
        );

        $foodCategory = ProductCategory::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'mon-chinh'],
            [
                'branch_id' => $branch->id,
                'name' => 'Mon chinh',
                'description' => 'Danh muc mon an chinh',
                'display_order' => 1,
                'status' => 'active',
            ],
        );

        $massUnit = Unit::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'symbol' => 'g'],
            ['name' => 'Gram', 'type' => 'mass'],
        );
        $countUnit = Unit::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'symbol' => 'ly'],
            ['name' => 'Ly', 'type' => 'count'],
        );

        $supplier = Supplier::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'Nha cung cap Demo'],
            [
                'branch_id' => $branch->id,
                'contact_name' => 'Tran Demo',
                'phone' => '0911111111',
                'status' => 'active',
            ],
        );

        $beef = Ingredient::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'sku' => 'BEEF-001'],
            [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'unit_id' => $massUnit->id,
                'name' => 'Thit bo',
                'category_name' => 'Thit',
                'min_stock_level' => 1000,
                'reorder_level' => 2000,
                'average_cost' => 280,
                'status' => 'active',
            ],
        );

        $noodle = Ingredient::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'sku' => 'NOODLE-001'],
            [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'unit_id' => $massUnit->id,
                'name' => 'Banh pho',
                'category_name' => 'Kho',
                'min_stock_level' => 2000,
                'reorder_level' => 5000,
                'average_cost' => 40,
                'status' => 'active',
            ],
        );

        Inventory::updateOrCreate(
            ['branch_id' => $branch->id, 'ingredient_id' => $beef->id],
            [
                'restaurant_id' => $restaurant->id,
                'quantity_on_hand' => 8000,
                'theoretical_quantity' => 7800,
                'last_counted_at' => now(),
                'last_cost' => 280,
                'updated_by' => $inventoryStaff->id,
            ],
        );

        Inventory::updateOrCreate(
            ['branch_id' => $branch->id, 'ingredient_id' => $noodle->id],
            [
                'restaurant_id' => $restaurant->id,
                'quantity_on_hand' => 15000,
                'theoretical_quantity' => 14900,
                'last_counted_at' => now(),
                'last_cost' => 40,
                'updated_by' => $inventoryStaff->id,
            ],
        );

        $pho = Product::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'PHO-BO'],
            [
                'branch_id' => $branch->id,
                'category_id' => $foodCategory->id,
                'name' => 'Pho bo tai',
                'slug' => 'pho-bo-tai',
                'description' => 'Pho bo tai truyen thong',
                'price' => 65000,
                'cost_price' => 28000,
                'preparation_time_minutes' => 12,
                'is_active' => true,
                'is_available' => true,
                'track_inventory' => true,
            ],
        );

        $tea = Product::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'TRA-DAO'],
            [
                'branch_id' => $branch->id,
                'category_id' => $foodCategory->id,
                'name' => 'Tra dao',
                'slug' => 'tra-dao',
                'description' => 'Tra dao mat lanh',
                'price' => 35000,
                'cost_price' => 12000,
                'preparation_time_minutes' => 5,
                'is_active' => true,
                'is_available' => true,
                'track_inventory' => false,
            ],
        );

        ProductRecipe::updateOrCreate(
            ['product_id' => $pho->id, 'ingredient_id' => $beef->id],
            [
                'restaurant_id' => $restaurant->id,
                'unit_id' => $massUnit->id,
                'quantity' => 120,
                'waste_rate' => 2,
            ],
        );

        ProductRecipe::updateOrCreate(
            ['product_id' => $pho->id, 'ingredient_id' => $noodle->id],
            [
                'restaurant_id' => $restaurant->id,
                'unit_id' => $massUnit->id,
                'quantity' => 180,
                'waste_rate' => 1,
            ],
        );

        $customer = Customer::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'phone' => '0988888888'],
            [
                'branch_id' => $branch->id,
                'full_name' => 'Khach Demo',
                'loyalty_points' => 120,
                'last_order_at' => now(),
            ],
        );

        $order = Order::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'order_number' => 'ORD-DEMO-001'],
            [
                'branch_id' => $branch->id,
                'table_id' => $tableA1->id,
                'customer_id' => $customer->id,
                'created_by' => $cashier->id,
                'cashier_user_id' => $cashier->id,
                'channel' => 'dine_in',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => 100000,
                'discount_amount' => 0,
                'service_charge' => 0,
                'tax_amount' => 0,
                'total_amount' => 100000,
                'confirmed_at' => now()->subMinutes(20),
                'completed_at' => now()->subMinutes(5),
            ],
        );

        OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $pho->id],
            [
                'restaurant_id' => $restaurant->id,
                'quantity' => 1,
                'unit_price' => 65000,
                'line_total' => 65000,
                'status' => 'served',
                'sent_to_kitchen_at' => now()->subMinutes(19),
                'prepared_at' => now()->subMinutes(12),
                'served_at' => now()->subMinutes(7),
            ],
        );

        OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $tea->id],
            [
                'restaurant_id' => $restaurant->id,
                'quantity' => 1,
                'unit_price' => 35000,
                'line_total' => 35000,
                'status' => 'served',
                'sent_to_kitchen_at' => now()->subMinutes(19),
                'prepared_at' => now()->subMinutes(10),
                'served_at' => now()->subMinutes(6),
            ],
        );

        Payment::updateOrCreate(
            ['order_id' => $order->id, 'transaction_code' => 'PAY-DEMO-001'],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'processed_by' => $cashier->id,
                'payment_method' => 'cash',
                'status' => 'paid',
                'amount' => 100000,
                'cash_received' => 100000,
                'change_amount' => 0,
                'paid_at' => now()->subMinutes(5),
                'meta' => ['source' => 'demo-seeder'],
            ],
        );

        $shift = WorkShift::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'CA-SANG'],
            [
                'branch_id' => $branch->id,
                'name' => 'Ca sang',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'is_overnight' => false,
                'status' => 'active',
            ],
        );

        ScheduleAssignment::updateOrCreate(
            [
                'employee_id' => $employees['cashier']->id,
                'shift_id' => $shift->id,
                'scheduled_date' => now()->toDateString(),
            ],
            [
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'status' => 'scheduled',
                'approved_by' => $manager->id,
            ],
        );

        AuditLog::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'user_id' => $cashier->id,
            'user_role' => 'cashier',
            'action' => 'seed_demo_order',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'old_values' => null,
            'new_values' => ['order_number' => $order->order_number, 'total_amount' => $order->total_amount],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'database-seeder',
        ]);
    }

    protected function upsertStaffUser(string $email, string $name, string $phone, string $role, Restaurant $restaurant): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'phone' => $phone,
                'restaurant_id' => $restaurant->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([$role]);

        return $user;
    }

    protected function upsertEmployee(Restaurant $restaurant, RestaurantBranch $branch, User $user, string $code, string $jobTitle): Employee
    {
        return Employee::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'employee_code' => $code],
            [
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'hire_date' => now()->subMonths(3)->toDateString(),
                'employment_type' => 'full_time',
                'job_title' => $jobTitle,
                'base_salary' => 9000000,
                'status' => 'active',
                'citizen_id_number' => '079'.str_pad((string) random_int(1, 99999999), 9, '0', STR_PAD_LEFT),
            ],
        );
    }
}
