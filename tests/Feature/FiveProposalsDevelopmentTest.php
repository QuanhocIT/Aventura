<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\InventoryTransaction;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\SalaryService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiveProposalsDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Role $ownerRole;
    private Role $cashierRole;
    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
            'grace_period_minutes' => 10,
            'ot_multiplier' => 2.0,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $this->kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        $this->owner->assignRole($this->ownerRole);
    }

    public function test_proposal_1_smart_shift_allocation_for_waste(): void
    {
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Kitchen Shift',
            'code' => 'KITCHEN_SHIFT',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        $chef1User = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $chef1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chef1User->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 10000000,
        ]);

        $chef2User = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $chef2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chef2User->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 10000000,
        ]);

        // Scenario A: chef1 is shift leader, chef2 is not
        $assign1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-20',
            'status' => 'scheduled',
            'is_shift_leader' => true,
        ]);

        $assign2 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef2->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-20',
            'status' => 'scheduled',
            'is_shift_leader' => false,
        ]);

        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Salmon',
            'sku' => 'SALMON',
            'average_cost' => 100000,
            'allowed_waste_ratio' => 0.00,
        ]);

        // Waste event happens at 10:30:00 (during their shift)
        $waste = InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 2.0,
            'unit_cost' => 100000,
            'total_cost' => 200000,
            'occurred_at' => '2026-05-20 10:30:00',
        ]);

        $salaryService = app(SalaryService::class);
        $salaryService->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        // Chef 1 (Leader) should get the full 200,000 penalty
        $salary1 = Salary::where('employee_id', $chef1->id)->first();
        $this->assertEquals(200000, (float) $salary1->deduction_amount);

        // Chef 2 (non-leader) should get 0 penalty
        $salary2 = Salary::where('employee_id', $chef2->id)->first();
        $this->assertEquals(0, (float) $salary2->deduction_amount);

        // Scenario B: Neither is shift leader. The cost should be split 50/50.
        $assign1->update(['is_shift_leader' => false]);
        SalaryAdjustment::truncate();
        Salary::truncate();

        $salaryService->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        // Both chef1 and chef2 get 100,000 penalty
        $salary1 = Salary::where('employee_id', $chef1->id)->first();
        $this->assertEquals(100000, (float) $salary1->deduction_amount);

        $salary2 = Salary::where('employee_id', $chef2->id)->first();
        $this->assertEquals(100000, (float) $salary2->deduction_amount);
    }

    public function test_proposal_2_timekeeping_overtime_multipliers_and_grace_periods(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
            'compensation_type' => 'hourly',
            'pay_rate' => 50000, // 50,000/hr
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Hourly Shift',
            'code' => 'HOURLY_SHIFT',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00', // 4 hours scheduled
            'status' => 'active',
        ]);

        // Case A: check-in late by 5m (within 10m grace period) -> paid 4 hours (200,000 VND)
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-10',
            'check_in_at' => '2026-05-10 08:05:00',
            'check_out_at' => '2026-05-10 12:00:00',
            'status' => 'completed',
        ]);

        $salaryService = app(SalaryService::class);
        $salaryService->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $salary = Salary::where('employee_id', $employee->id)->first();
        $this->assertEquals(200000, (float) $salary->base_salary);

        // Case B: check-out late by 1 hour (overtime) -> paid 4 hours regular + 1 hour OT at 2.0x = 6 hours total (300,000 VND)
        Salary::truncate();
        ScheduleAssignment::truncate();

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-10',
            'check_in_at' => '2026-05-10 08:00:00',
            'check_out_at' => '2026-05-10 13:00:00',
            'status' => 'completed',
        ]);

        $salaryService->generateMonthlyDrafts($this->restaurant->id, '2026-05');
        $salary = Salary::where('employee_id', $employee->id)->first();
        $this->assertEquals(300000, (float) $salary->base_salary);
    }

    public function test_proposal_3_real_time_fraud_prevention(): void
    {
        $cashierUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $cashierUser->assignRole($this->cashierRole);

        $product = Product::factory()->create(['restaurant_id' => $this->restaurant->id, 'price' => 500000]);
        
        $promotion = \App\Models\Promotion::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Promo',
            'code' => 'TESTVOUCHER',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'is_approved' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-TEST-FRAUD',
            'subtotal' => 500000,
            'total_amount' => 500000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 500000,
            'line_total' => 500000,
        ]);

        $this->actingAs($cashierUser);

        // Add 3 discount_applied audit logs in the last 5 minutes to trigger cashier fraud alert
        for ($i = 0; $i < 3; $i++) {
            \App\Models\AuditLog::log('discount_applied', 'updated', $order, null, ['discount_amount' => 50000]);
        }

        // Attempting to apply without bypass code should fail
        $response = $this->postJson(route('promotions.apply'), [
            'order_id' => $order->id,
            'code' => 'TESTVOUCHER',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'requires_bypass'
        ]);

        // Attempting to apply with bypass code should succeed
        $response = $this->postJson(route('promotions.apply'), [
            'order_id' => $order->id,
            'code' => 'TESTVOUCHER',
            'bypass_code' => 'MANAGER123',
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(50000, (float) $order->discount_amount);
    }

    public function test_proposal_4_loyalty_points_redemption_and_accumulation(): void
    {
        $customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'full_name' => 'Gia Long',
            'loyalty_points' => 50,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-LOYALTY-TEST',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ]);

        $orderService = app(OrderService::class);
        
        // Pay order, redeem 10 points
        $orderService->payOrder($order, [
            'payment_method' => 'cash',
            'cash_received' => 200000,
            'change_amount' => 0,
            'redeem_points' => 10, // 10 points = 1,000 VND discount
        ], $this->owner);

        $order->refresh();
        $customer->refresh();

        // Check discount applied: 1,000 VND
        $this->assertEquals(1000, (float) $order->discount_amount);
        $this->assertEquals(199000, (float) $order->total_amount);

        // Customer points:
        // Start: 50
        // Redempted: -10
        // Earned: floor(199,000 / 10,000) = +19 points
        // Final: 50 - 10 + 19 = 59 points
        $this->assertEquals(59, $customer->loyalty_points);
        $this->assertNotNull($customer->last_order_at);
    }

    public function test_proposal_5_event_driven_salary_recalculation(): void
    {
        $chefUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $chefUser->assignRole($this->kitchenRole);

        $chef = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chefUser->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 10000000,
        ]);

        // Creating a ViolationReport should trigger auto recalculation
        $violation = ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'reported_by' => $this->owner->id,
            'violation_type' => 'Di Tre',
            'severity' => 'low',
            'description' => 'Di tre',
            'penalty_amount' => 50000,
            'occurred_at' => now()->toDateString() . ' 08:00:00',
            'status' => 'open',
        ]);

        // A draft salary should have been automatically created and updated with the penalty
        $salary = Salary::where('employee_id', $chef->id)->first();
        $this->assertNotNull($salary);
        $this->assertEquals(50000, (float) $salary->deduction_amount);

        // Deleting the violation report should trigger auto recalculation and clear the deduction
        $violation->delete();

        $salary->refresh();
        $this->assertEquals(0, (float) $salary->deduction_amount);
    }
}
