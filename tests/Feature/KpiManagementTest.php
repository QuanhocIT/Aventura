<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\KpiMetric;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\CustomerFeedback;
use App\Models\ShiftClosing;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KpiManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Employee $waiterEmployee;
    protected Employee $chefEmployee;
    protected Employee $cashierEmployee;
    protected User $waiterUser;
    protected User $chefUser;
    protected User $cashierUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Spatie Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // 2. Create Restaurant & Owner
        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro']);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ])->save();

        // 3. Create Waiter Employee & User
        $this->waiterUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nguyen Van Waiter',
            'email' => 'waiter@example.com',
            'status' => 'active',
        ]);
        $this->waiterUser->assignRole($waiterRole);
        $this->waiterEmployee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->waiterUser->id,
            'role_id' => $waiterRole->id,
            'employee_code' => 'EMP001',
            'full_name' => 'Nguyen Van Waiter',
            'job_title' => 'Waiter',
            'status' => 'active',
        ]);

        // 4. Create Chef Employee & User
        $this->chefUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Tran Van Chef',
            'email' => 'chef@example.com',
            'status' => 'active',
        ]);
        $this->chefUser->assignRole($kitchenRole);
        $this->chefEmployee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->chefUser->id,
            'role_id' => $kitchenRole->id,
            'employee_code' => 'EMP002',
            'full_name' => 'Tran Van Chef',
            'job_title' => 'Chef',
            'status' => 'active',
        ]);

        // 5. Create Cashier Employee & User
        $this->cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Le Thi Cashier',
            'email' => 'cashier@example.com',
            'status' => 'active',
        ]);
        $this->cashierUser->assignRole($cashierRole);
        $this->cashierEmployee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashierUser->id,
            'role_id' => $cashierRole->id,
            'employee_code' => 'EMP003',
            'full_name' => 'Le Thi Cashier',
            'job_title' => 'Cashier',
            'status' => 'active',
        ]);

        // 6. Seed default KPI config metrics for this restaurant
        Artisan::call('db:seed', ['--class' => 'KpiMetricsSeeder']);
    }

    /**
     * Test Waiter KPI calculation.
     */
    public function test_kpi_calculation_for_waiter()
    {
        $period = now()->format('Y-m');

        // Create shift
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA-SANG',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);

        // 1 completed assignment = 1 shift worked
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->waiterEmployee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        // Create 2 orders created by this waiter
        $order1 = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-W1-1',
            'created_by' => $this->waiterUser->id,
            'status' => 'completed',
            'total_amount' => 150000,
        ]);
        $order2 = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-W1-2',
            'created_by' => $this->waiterUser->id,
            'status' => 'completed',
            'total_amount' => 200000,
        ]);

        // Create feedback for order1 (5 stars)
        CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order1->id,
            'rating' => 5,
        ]);

        // Create a waiter order item to measure service speed (prepared to served)
        $product = \App\Models\Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Mock Dish',
            'code' => 'MOCK-DISH',
            'price' => 100000,
            'slug' => 'mock-dish',
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order1->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'prepared_at' => now()->subMinutes(10),
            'served_at' => now()->subMinutes(6), // difference = 4 minutes (target is 5)
            'status' => 'served',
        ]);

        // Trigger recalculation route
        $response = $this->actingAs($this->owner)->post(route('kpis.recalculate'), [
            'period' => $period,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Check if draft KPI was created
        $kpi = EmployeeKpi::where('employee_id', $this->waiterEmployee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($kpi);
        $this->assertEquals('draft', $kpi->status);

        // Check detailed metrics
        $servedMetric = $kpi->metrics()->where('metric_code', 'waiter_orders_served')->first();
        $this->assertNotNull($servedMetric);
        // 2 orders / 1 shift = 2 orders/shift
        $this->assertEquals(2.0, (float)$servedMetric->actual_value);

        $ratingMetric = $kpi->metrics()->where('metric_code', 'waiter_customer_rating')->first();
        $this->assertNotNull($ratingMetric);
        $this->assertEquals(5.0, (float)$ratingMetric->actual_value);

        $speedMetric = $kpi->metrics()->where('metric_code', 'waiter_service_speed')->first();
        $this->assertNotNull($speedMetric);
        $this->assertEquals(4.0, (float)$speedMetric->actual_value);
        $this->assertTrue((bool)$speedMetric->is_achieved); // 4 minutes <= 5 target
    }

    /**
     * Test Cashier KPI calculation.
     */
    public function test_kpi_calculation_for_cashier()
    {
        $period = now()->format('Y-m');

        // Cashier revenue: create a completed payment processed by cashier
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-CS-1',
            'status' => 'completed',
            'total_amount' => 50000000,
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'processed_by' => $this->cashierUser->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 50000000,
            'paid_at' => now(),
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA-SANG',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);

        // Cashier error rate: 1 shift closing with 0 cash difference
        ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'cashier_user_id' => $this->cashierUser->id,
            'closing_date' => now()->toDateString(),
            'actual_cash' => 100000,
            'expected_cash' => 100000,
            'cash_difference' => 0,
            'status' => 'confirmed',
        ]);

        // Trigger calculation
        $response = $this->actingAs($this->owner)->post(route('kpis.recalculate'), [
            'period' => $period,
        ]);

        $response->assertRedirect();

        $kpi = EmployeeKpi::where('employee_id', $this->cashierEmployee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($kpi);
        $revenueMetric = $kpi->metrics()->where('metric_code', 'cashier_processed_revenue')->first();
        $this->assertEquals(50000000, (float)$revenueMetric->actual_value);
        $this->assertTrue((bool)$revenueMetric->is_achieved); // Target 50,000,000 achieved
        // Commission = 50,000,000 * 0.2% = 100,000
        $this->assertEquals(100000, (float)$revenueMetric->commission_earned);

        $errorMetric = $kpi->metrics()->where('metric_code', 'cashier_error_rate')->first();
        $this->assertEquals(0, (float)$errorMetric->actual_value);
        $this->assertTrue((bool)$errorMetric->is_achieved); // Target 0 error achieved
    }

    /**
     * Test Chef/Kitchen KPI calculation.
     */
    public function test_kpi_calculation_for_chef()
    {
        $period = now()->format('Y-m');

        // Create shift
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu Bep',
            'code' => 'CA-CHIEU-BEP',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'status' => 'active',
        ]);

        $dateStr = now()->toDateString();

        // Chef shift assignment completed
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->chefEmployee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $dateStr,
            'check_in_at' => $dateStr . ' 13:00:00',
            'check_out_at' => $dateStr . ' 17:00:00',
            'status' => 'completed',
        ]);

        // Create an order
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-CHEF-1',
            'status' => 'completed',
            'total_amount' => 300000,
        ]);

        $product = \App\Models\Product::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Steak',
            'code' => 'STEAK',
            'price' => 150000,
            'slug' => 'steak',
        ]);

        // Order item prepared within shift: sent at 13:10, prepared at 13:20 (prep_time = 10 minutes, target is 15)
        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'sent_to_kitchen_at' => $dateStr . ' 13:10:00',
            'prepared_at' => $dateStr . ' 13:20:00',
            'created_at' => $dateStr . ' 13:10:00',
            'status' => 'served',
        ]);

        // Order item cancelled within shift (for rejection rate check)
        $order2 = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-CHEF-2',
            'status' => 'completed',
            'total_amount' => 150000,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order2->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'created_at' => $dateStr . ' 14:00:00',
            'status' => 'cancelled',
        ]);

        // Rating feedback for order
        CustomerFeedback::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'rating' => 5.0,
        ]);

        // Trigger calculation
        $response = $this->actingAs($this->owner)->post(route('kpis.recalculate'), [
            'period' => $period,
        ]);

        $response->assertRedirect();

        $kpi = EmployeeKpi::where('employee_id', $this->chefEmployee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($kpi);

        $prepTimeMetric = $kpi->metrics()->where('metric_code', 'chef_prep_time')->first();
        $this->assertNotNull($prepTimeMetric);
        $this->assertEquals(10.0, (float)$prepTimeMetric->actual_value); // 10 minutes
        $this->assertTrue((bool)$prepTimeMetric->is_achieved); // Target 15 achieved (10 <= 15)

        $rejectionMetric = $kpi->metrics()->where('metric_code', 'chef_rejection_rate')->first();
        $this->assertNotNull($rejectionMetric);
        // Total items in shifts = 2 (Steak prepared + Steak cancelled)
        // Rejection rate = 1/2 * 100 = 50%
        $this->assertEquals(50.0, (float)$rejectionMetric->actual_value);
        $this->assertFalse((bool)$rejectionMetric->is_achieved); // Target 2% not achieved (50 > 2)

        $foodRatingMetric = $kpi->metrics()->where('metric_code', 'chef_food_rating')->first();
        $this->assertNotNull($foodRatingMetric);
        $this->assertEquals(5.0, (float)$foodRatingMetric->actual_value);
        $this->assertTrue((bool)$foodRatingMetric->is_achieved); // Target 4.5 achieved
    }

    /**
     * Test KPI finalization and sync integration into draft salaries.
     */
    public function test_kpi_finalization_integrates_with_salary()
    {
        $period = now()->format('Y-m');

        // Create a fake draft KPI
        $kpi = EmployeeKpi::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->cashierEmployee->id,
            'period' => $period,
            'total_score' => 95.0,
            'total_bonus' => 150000, // achieved target bonus
            'total_commission' => 200000, // commission earned
            'status' => 'draft',
        ]);

        // 1. Finalize the KPI score
        $response = $this->actingAs($this->owner)->post(route('kpis.finalize', $kpi->id));
        $response->assertRedirect();
        $kpi->refresh();
        $this->assertEquals('finalized', $kpi->status);

        // 2. Generate monthly draft salaries using the salary:generate route
        // Compensation fields required on Employee
        $this->cashierEmployee->update([
            'compensation_type' => 'fixed',
            'base_salary' => 8000000,
        ]);

        $response = $this->actingAs($this->owner)->post(route('salaries.generate'), [
            'period' => $period,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 3. Verify salary draft includes two adjustments corresponding to KPI bonus and commission
        $salary = Salary::withoutGlobalScopes()
            ->where('employee_id', $this->cashierEmployee->id)
            ->first();

        $this->assertNotNull($salary);
        $this->assertEquals(8350000, (float)$salary->net_salary); // base(8M) + bonus(150k) + commission(200k)

        $adjustments = SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->get();

        $this->assertEquals(2, $adjustments->count());

        $bonusAdjustment = SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->where('reason', 'like', 'Thưởng vượt chỉ tiêu%')
            ->first();
        $this->assertNotNull($bonusAdjustment);
        $this->assertEquals(150000, (float)$bonusAdjustment->amount);

        $commissionAdjustment = SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->where('reason', 'like', 'Thưởng hoa hồng%')
            ->first();
        $this->assertNotNull($commissionAdjustment);
        $this->assertEquals(200000, (float)$commissionAdjustment->amount);
    }

    /**
     * Test 360-degree review submission.
     */
    public function test_performance_review_submission()
    {
        $period = now()->format('Y-m');

        $response = $this->actingAs($this->owner)->post(route('kpis.reviews.store'), [
            'employee_id' => $this->waiterEmployee->id,
            'reviewer_type' => 'manager',
            'period' => $period,
            'ratings' => [
                'communication' => 4,
                'teamwork' => 5,
                'reliability' => 4,
                'skills' => 5,
            ],
            'comments' => 'Rất tích cực làm việc, kỹ năng nghiệp vụ giỏi.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $review = \App\Models\PerformanceReview::where('employee_id', $this->waiterEmployee->id)
            ->where('period', $period)
            ->first();

        $this->assertNotNull($review);
        $this->assertEquals('manager', $review->reviewer_type);
        $this->assertEquals(4.5, (float)$review->average_score); // (4+5+4+5)/4 = 4.5
        $this->assertEquals('Rất tích cực làm việc, kỹ năng nghiệp vụ giỏi.', $review->comments);
    }
}
