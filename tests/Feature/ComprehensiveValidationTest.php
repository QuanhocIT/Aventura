<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprehensiveValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $cashier;
    protected Employee $employee1;
    protected Employee $employee2;
    protected Restaurant $restaurant;
    protected Role $ownerRole;
    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles and permissions
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // Enable permissions
        $this->ownerRole->givePermissionTo(Role::firstOrCreate(['name' => 'approve_requests', 'guard_name' => 'web']));
        $this->ownerRole->givePermissionTo(Role::firstOrCreate(['name' => 'manage_orders', 'guard_name' => 'web']));
        $this->ownerRole->givePermissionTo(Role::firstOrCreate(['name' => 'create_orders', 'guard_name' => 'web']));
        
        $this->cashierRole->givePermissionTo(Role::firstOrCreate(['name' => 'create_orders', 'guard_name' => 'web']));
        $this->cashierRole->givePermissionTo(Role::firstOrCreate(['name' => 'process_payments', 'guard_name' => 'web']));

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Aventura Test Restaurant',
            'code' => 'TESTREST',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'owner@test.com',
            'status' => 'active',
        ]);
        $this->owner->assignRole($this->ownerRole);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'cashier@test.com',
            'status' => 'active',
        ]);
        $this->cashier->assignRole($this->cashierRole);

        $this->employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'full_name' => 'Nguyen Van A',
        ]);

        $this->employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'full_name' => 'Tran Van B',
        ]);

        // Create 3 more cashier employees so we have 5 total (allowing 1 to go on leave: 1/5 = 20% <= 30%)
        for ($i = 0; $i < 3; $i++) {
            Employee::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'role_id' => $this->cashierRole->id,
                'status' => 'active',
            ]);
        }
    }

    public function test_leave_request_overlapping_validation(): void
    {
        $this->actingAs($this->owner);

        // Pre-create approved leave request
        LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'requested_by' => $this->owner->id,
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'approved',
        ]);

        // Attempt overlapping leave request creation
        $response = $this->post(route('employees.leaves.store'), [
            'employee_id' => $this->employee1->id,
            'leave_type' => 'sick',
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_leave_request_quota_validation(): void
    {
        $this->actingAs($this->owner);

        // Employee 1 is on leave
        LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'requested_by' => $this->owner->id,
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
        ]);

        // Trying to put Employee 2 on leave on the same day (2/5 = 40% > 30%)
        $response = $this->post(route('employees.leaves.store'), [
            'employee_id' => $this->employee2->id,
            'leave_type' => 'sick',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_leave_request_schedule_conflict_warning(): void
    {
        $this->actingAs($this->owner);

        $shift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create assignment for employee 1
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->addDays(10)->toDateString(),
            'status' => 'scheduled',
        ]);

        // Request leave
        $response = $this->post(route('employees.leaves.store'), [
            'employee_id' => $this->employee1->id,
            'leave_type' => 'unpaid',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Lưu ý: Nhân viên đã có ca trực được xếp', session('success'));
    }

    public function test_promotion_combo_price_validation(): void
    {
        $this->actingAs($this->owner);

        $productA = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 50000,
        ]);
        $productB = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 30000,
        ]);

        // Total price = 80,000. Try to create combo with price 90,000 (invalid)
        $response = $this->post(route('promotions.combos.store'), [
            'name' => 'Combo test',
            'item_a_id' => $productA->id,
            'item_b_id' => $productB->id,
            'combo_price' => 90000,
        ]);

        $response->assertSessionHasErrors(['combo_price']);
    }

    public function test_promotion_voucher_value_validation(): void
    {
        $this->actingAs($this->owner);

        // Percent voucher > 100%
        $response1 = $this->post(route('promotions.store'), [
            'name' => 'Voucher 150%',
            'type' => 'percent',
            'value' => 150,
        ]);
        $response1->assertSessionHasErrors(['value']);

        // Fixed voucher > min_order_amount
        $response2 = $this->post(route('promotions.store'), [
            'name' => 'Voucher fixed',
            'type' => 'fixed_amount',
            'value' => 50000,
            'min_order_amount' => 30000,
        ]);
        $response2->assertSessionHasErrors(['value']);
    }

    public function test_order_table_capacity_validation(): void
    {
        $this->actingAs($this->cashier);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'capacity' => 2,
            'status' => 'available',
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 50000,
            'is_active' => true,
            'is_available' => true,
        ]);

        // Attempt creating order with 4 guests
        $response = $this->post(route('orders.store'), [
            'table_id' => $table->id,
            'guests_count' => 4,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1]
            ]
        ]);

        $response->assertSessionHasErrors(['guests_count']);
    }

    public function test_order_price_override_manager_bypass(): void
    {
        $this->actingAs($this->cashier);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'price' => 50000,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);

        // Try to reduce price without bypass code
        $response = $this->patch(route('orders.update', $order->id), [
            'items' => [
                ['id' => $orderItem->id, 'product_id' => $product->id, 'unit_price' => 20000, 'quantity' => 1]
            ]
        ]);
        $response->assertSessionHasErrors(['items']);

        // Try with correct bypass code
        $response2 = $this->patch(route('orders.update', $order->id), [
            'bypass_code' => 'MANAGER123',
            'items' => [
                ['id' => $orderItem->id, 'product_id' => $product->id, 'unit_price' => 20000, 'quantity' => 1]
            ]
        ]);
        $response2->assertSessionHasNoErrors();
    }

    public function test_order_cancellation_manager_bypass(): void
    {
        $this->actingAs($this->cashier);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
        ]);

        // Try to cancel without bypass code
        $response = $this->patch(route('orders.update-status', $order->id), [
            'status' => 'cancelled',
        ]);
        $response->assertSessionHasErrors(['status']);

        // Try with bypass code
        $response2 = $this->patch(route('orders.update-status', $order->id), [
            'status' => 'cancelled',
            'bypass_code' => 'MANAGER123',
        ]);
        $response2->assertSessionHasNoErrors();
    }

    public function test_order_cancellation_manager_pin_bypass(): void
    {
        $this->actingAs($this->cashier);

        // Assign a pin code to the manager (who has manager/owner role)
        $managerUser = \App\Models\User::role('manager')->where('restaurant_id', $this->restaurant->id)->first();
        if (!$managerUser) {
            $managerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
            $managerUser = \App\Models\User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
            $managerUser->assignRole($managerRole);
        }
        $managerUser->update(['pin_code' => '9999']);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
        ]);

        // Try with manager PIN code
        $response = $this->patch(route('orders.update-status', $order->id), [
            'status' => 'cancelled',
            'bypass_code' => '9999',
        ]);
        $response->assertSessionHasNoErrors();
    }

    public function test_order_cancellation_manager_email_password_bypass(): void
    {
        $this->actingAs($this->cashier);

        $managerUser = \App\Models\User::role('manager')->where('restaurant_id', $this->restaurant->id)->first();
        if (!$managerUser) {
            $managerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
            $managerUser = \App\Models\User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active', 'password' => 'secret123']);
            $managerUser->assignRole($managerRole);
        } else {
            $managerUser->update(['password' => 'secret123']);
        }

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
        ]);

        // Try with manager email & password bypass_code formatted as email:password
        $response = $this->patch(route('orders.update-status', $order->id), [
            'status' => 'cancelled',
            'bypass_code' => $managerUser->email . ':secret123',
        ]);
        $response->assertSessionHasNoErrors();
    }

    public function test_order_cancellation_lockout_after_3_failed_attempts(): void
    {
        $this->actingAs($this->cashier);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'pending',
        ]);

        // Submit incorrect bypass code 2 times
        for ($i = 0; $i < 2; $i++) {
            $response = $this->patch(route('orders.update-status', $order->id), [
                'status' => 'cancelled',
                'bypass_code' => 'WRONG_CODE_' . $i,
            ]);
            $response->assertSessionHasErrors(['status']);
        }

        // The 3rd attempt should hit the lockout validation exception and return the lockout message
        $response = $this->patch(route('orders.update-status', $order->id), [
            'status' => 'cancelled',
            'bypass_code' => 'WRONG_CODE_3',
        ]);
        
        $response->assertSessionHasErrors(['bypass_code']);
    }

    public function test_schedule_11_hour_rest_rule_validation(): void
    {
        Carbon::setTestNow('2026-06-08 00:00:00');
        $this->actingAs($this->owner);

        // Monday is 2026-06-08, Tuesday is 2026-06-09
        $shiftA = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Shift A',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        $shiftB = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Shift B',
            'start_time' => '18:00:00',
            'end_time' => '02:00:00',
            'is_overnight' => true,
        ]);

        // Create assignment for Shift B on Monday (ends Tuesday 02:00)
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shiftB->id,
            'scheduled_date' => '2026-06-08',
            'status' => 'scheduled',
        ]);

        // Try to assign Shift A on Tuesday (starts 08:00 - rest is 6 hours < 11 hours)
        $response = $this->post(route('employees.schedules.store'), [
            'day' => 'Tuesday',
            'employee_name' => $this->employee1->full_name,
            'shift_name' => $shiftA->name,
        ]);

        $response->assertSessionHasErrors(['shift_name']);
        $this->assertStringContainsString('nghỉ 11h', strtolower(session()->get('errors')->get('shift_name')[0]));
        Carbon::setTestNow();
    }

    public function test_inventory_profit_margin_and_expiry_validation(): void
    {
        $this->actingAs($this->owner);

        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ingredient Test',
            'average_cost' => 10000,
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Product Test',
            'price' => 50000,
        ]);

        // ProductRecipe uses 0.1 of ingredient
        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $ingredient->unit_id,
            'quantity' => 0.1,
            'waste_rate' => 0,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('invoice.png');

        // Cost per unit = 0.1 * 600,000 = 60,000 >= selling price 50,000 (invalid)
        $response = $this->post(route('inventory.purchases.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'unit_cost' => 600000,
            'invoice_file' => $file,
        ]);
        $response->assertSessionHasErrors(['unit_cost']);

        // Try importing with bad expiration date (e.g. today or tomorrow, when occurred_at is today)
        $response2 = $this->post(route('inventory.purchases.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'unit_cost' => 10000,
            'invoice_file' => $file,
            'expiry_date' => now()->addDays(1)->toDateString(),
        ]);
        $response2->assertSessionHasErrors(['expiry_date']);
    }

    public function test_self_approval_prevention_on_approval_requests(): void
    {
        $approval = \App\Models\ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'requester_id' => $this->owner->id,
            'operation_type' => 'inventory_purchase',
            'operation_data' => ['ingredient_id' => 1, 'quantity' => 5, 'unit_cost' => 100],
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('approvals.approve', $approval->id));
        $response->assertStatus(403);
    }

    public function test_self_approval_prevention_on_leave_requests(): void
    {
        // Link employee1 user to owner for self check
        $this->employee1->update(['user_id' => $this->owner->id]);

        $leave = LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'requested_by' => $this->owner->id,
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('employees.leaves.approve', $leave->id));
        $response->assertStatus(403);
    }

    public function test_self_approval_prevention_on_shift_swaps(): void
    {
        // Link employee1 to owner
        $this->employee1->update(['user_id' => $this->owner->id]);

        $shift = WorkShift::factory()->create(['restaurant_id' => $this->restaurant->id]);
        
        $assign1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-06-08',
            'status' => 'scheduled',
        ]);

        $assign2 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee2->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-06-09',
            'status' => 'scheduled',
        ]);

        $swap = \App\Models\ShiftSwap::create([
            'restaurant_id' => $this->restaurant->id,
            'requester_assignment_id' => $assign1->id,
            'receiver_assignment_id' => $assign2->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($this->owner);
        $response = $this->patch(route('schedules.swap.approve', $swap->id));
        $response->assertStatus(403);
    }

    public function test_salary_advance_limit_validation(): void
    {
        $this->ownerRole->givePermissionTo(Role::firstOrCreate(['name' => 'manage_salary', 'guard_name' => 'web']));
        $this->actingAs($this->owner);

        // Fixed salary employee with base 30,000,000
        $this->employee1->update([
            'compensation_type' => 'fixed',
            'base_salary' => 30000000,
        ]);

        $periodStart = today()->startOfMonth()->toDateString();
        $periodEnd = today()->endOfMonth()->toDateString();

        $salary = \App\Models\Salary::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'base_salary' => 30000000,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_salary' => 30000000,
            'status' => 'draft',
        ]);

        // Today is elapsed days, let's dynamically calculate
        $carbonDate = today();
        $daysInMonth = $carbonDate->daysInMonth;
        $elapsedDays = $carbonDate->day;
        $earnedWages = (30000000 / $daysInMonth) * $elapsedDays;
        $limit = $earnedWages * 0.50;

        $response = $this->post(route('salaries.adjustments.store', $salary->id), [
            'type' => 'advance',
            'amount' => $limit + 10000, // exceeds limit by 10k
            'reason' => 'Tạm ứng mua xe',
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    public function test_supplier_price_variance_owner_check(): void
    {
        $this->ownerRole->givePermissionTo(Role::firstOrCreate(['name' => 'supplier_portal', 'guard_name' => 'web']));
        $this->actingAs($this->owner);

        $supplier = \App\Models\Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Supplier Test',
            'status' => 'active',
        ]);

        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $supplier->id,
            'average_cost' => 100000, // list price
        ]);

        $po = \App\Models\PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-123',
            'status' => 'approved',
            'total_amount' => 100000,
            'created_by' => $this->owner->id,
            'payment_status' => 'escrow_locked',
        ]);

        $po->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity_ordered' => 1,
            'price_per_unit' => 100000,
            'total_cost' => 100000,
        ]);

        // Verify order with 120,000 price (20% variance)
        $responseVerify = $this->post(route('suppliers.orders.verify', $po->id), [
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity_received' => 1,
                    'invoice_price' => 120000, // 20% higher
                ]
            ],
            'rating' => 5,
        ]);

        $responseVerify->assertSessionHas('warning'); // failed match and frozen
        
        $po->refresh();
        $this->assertTrue($po->is_frozen);
        $this->assertTrue($po->is_discrepant);

        // Try to release escrow as cashier/manager (not owner)
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->assignRole($managerRole);
        
        $responseReleaseManager = $this->actingAs($manager)->post(route('suppliers.orders.release-escrow', $po->id));
        $responseReleaseManager->assertSessionHasErrors(['error']);

        // Try as owner
        $responseReleaseOwner = $this->actingAs($this->owner)->post(route('suppliers.orders.release-escrow', $po->id));
        $responseReleaseOwner->assertSessionHas('success');
    }

    public function test_gps_checkin_checkout_validation(): void
    {
        // Configure restaurant coordinates and radius
        $this->restaurant->update([
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'checkin_radius_meters' => 100,
        ]);

        $shift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'start_time' => now()->subMinutes(10)->format('H:i:s'),
            'end_time' => now()->addMinutes(4)->format('H:i:s'), // ends in 4 mins, <= 5 min grace checkout
            'status' => 'active',
        ]);

        // Link employee1 to cashier user
        $this->employee1->update(['user_id' => $this->cashier->id]);
        
        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->cashier);

        // 1. Try mock GPS
        $responseMock = $this->post(route('schedules.check-in'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'is_mock' => true,
        ]);
        $responseMock->assertSessionHasErrors();

        // 2. Try poor accuracy
        $responseAccuracy = $this->post(route('schedules.check-in'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'accuracy' => 150,
        ]);
        $responseAccuracy->assertSessionHasErrors();

        // 3. Try far distance
        $responseDistance = $this->post(route('schedules.check-in'), [
            'latitude' => 11.0,
            'longitude' => 107.0,
            'accuracy' => 10,
        ]);
        $responseDistance->assertSessionHasErrors();

        // 4. Successful check-in
        $responseSuccess = $this->post(route('schedules.check-in'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'accuracy' => 10,
        ]);
        $responseSuccess->assertSessionHasNoErrors();
        $responseSuccess->assertSessionHas('success');

        // 5. Try mock GPS checkout
        $responseCheckoutMock = $this->post(route('schedules.check-out'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'is_mock' => true,
        ]);
        $responseCheckoutMock->assertSessionHasErrors();

        // 6. Try poor accuracy checkout
        $responseCheckoutAccuracy = $this->post(route('schedules.check-out'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'accuracy' => 150,
        ]);
        $responseCheckoutAccuracy->assertSessionHasErrors();

        // 7. Try far distance checkout
        $responseCheckoutDistance = $this->post(route('schedules.check-out'), [
            'latitude' => 11.0,
            'longitude' => 107.0,
            'accuracy' => 10,
        ]);
        $responseCheckoutDistance->assertSessionHasErrors();

        // 8. Successful check-out
        $responseCheckoutSuccess = $this->post(route('schedules.check-out'), [
            'latitude' => 10.762622,
            'longitude' => 106.660172,
            'accuracy' => 10,
        ]);
        $responseCheckoutSuccess->assertSessionHasNoErrors();
        $responseCheckoutSuccess->assertSessionHas('success');
    }

    public function test_duplicate_checkin_is_blocked(): void
    {
        // Configure restaurant without GPS/QR so we can test pure check-in logic
        $this->restaurant->update([
            'latitude'        => null,
            'longitude'       => null,
            'qr_checkin_code' => null,
        ]);

        $shift = WorkShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'start_time'    => now()->subMinutes(10)->format('H:i:s'),
            'end_time'      => now()->addHours(4)->format('H:i:s'),
            'status'        => 'active',
        ]);

        // Link employee1 to cashier via user_id
        $this->employee1->update(['user_id' => $this->cashier->id]);

        // Create a scheduled assignment for employee1
        $assignment = ScheduleAssignment::factory()->create([
            'restaurant_id'  => $this->restaurant->id,
            'employee_id'    => $this->employee1->id,
            'shift_id'       => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'status'         => 'scheduled',
        ]);

        $this->actingAs($this->cashier);

        // First check-in should succeed
        $resp1 = $this->post(route('schedules.check-in'), []);
        $resp1->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertEquals('checked_in', $assignment->status);
        $this->assertNotNull($assignment->check_in_at);
        $originalCheckInAt = $assignment->check_in_at->toDateTimeString();

        // Second check-in: no more 'scheduled' slot found. Backend should return
        // the idempotent-success since employee is already checked-in today.
        $resp2 = $this->post(route('schedules.check-in'), []);
        // Either returns early with success (idempotent) OR returns an error about no slot.
        // The critical assertion is that assignment check_in_at was NOT overwritten.
        $assignment->refresh();
        $this->assertEquals('checked_in', $assignment->status);
        $this->assertEquals($originalCheckInAt, $assignment->check_in_at->toDateTimeString());
    }

    public function test_violation_report_created_successfully(): void
    {
        // Give owner the needed permissions
        $permView   = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_violations',   'guard_name' => 'web']);
        $permReport = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'report_violations', 'guard_name' => 'web']);
        $permManage = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_violations', 'guard_name' => 'web']);
        $this->ownerRole->givePermissionTo([$permView, $permReport, $permManage]);

        $this->actingAs($this->owner);

        // Enable hr_full feature for testing (bypass quota gate) using actual schema columns
        $plan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['code' => 'pro_test'],
            [
                'name'           => 'Chuyên Nghiệp',
                'price'          => 0,
                'billing_cycle'  => 'monthly',
                'max_branches'   => 10,
                'max_tables'     => 100,
                'max_users'      => 50,
                'max_dishes'     => 500,
                'features'       => json_encode(['hr_full' => true]),
                'status'         => 'active',
            ]
        );
        $this->restaurant->update(['plan_id' => $plan->id]);

        $response = $this->post(route('violations.store'), [
            'employee_id'    => $this->employee1->id,
            'violation_type' => 'Đi trễ',
            'description'    => 'Nhân viên đến trễ hơn 30 phút không có lý do chính đáng.',
            'is_anonymous'   => false,
            'occurred_at'    => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        // Verify violation was stored in DB
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id'  => $this->restaurant->id,
            'employee_id'    => $this->employee1->id,
            'violation_type' => 'Đi trễ',
            'status'         => 'open',
        ]);
    }

    public function test_internal_transfer_duplicate_blocked_by_insufficient_stock(): void
    {
        // Test that InternalTransfer correctly blocks when stock is insufficient (prevents over-transfer)
        $managerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status'        => 'active',
        ]);
        $manager->assignRole($managerRole);

        $branch1 = \App\Models\RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $branch2 = \App\Models\RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $unit       = \App\Models\Unit::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $unit->id,
            'status'        => 'active',
        ]);

        // Create inventory with only 5kg on hand
        Inventory::factory()->create([
            'restaurant_id'    => $this->restaurant->id,
            'branch_id'        => $branch1->id,
            'ingredient_id'    => $ingredient->id,
            'quantity_on_hand' => 5.0,
        ]);

        $this->actingAs($manager);

        // Try to transfer 10kg (more than available 5kg)
        $response = $this->post(route('inventory.internal-transfers'), [
            'from_branch_id' => $branch1->id,
            'to_branch_id'   => $branch2->id,
            'ingredient_id'  => $ingredient->id,
            'quantity'       => 10.0,
            'notes'          => 'Test over-transfer',
        ]);

        $response->assertSessionHas('error');

        // Verify stock was NOT changed
        $this->assertDatabaseHas('inventories', [
            'branch_id'        => $branch1->id,
            'ingredient_id'    => $ingredient->id,
            'quantity_on_hand'  => 5.0,
        ]);
    }
}
