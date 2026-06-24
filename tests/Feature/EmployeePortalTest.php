<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\KpiMetric;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeePortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $employeeUser;
    protected User $colleagueUser;
    protected Employee $employee;
    protected Employee $colleague;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        // Setup Restaurant & Branches
        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro']);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Setup Owner
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        // Setup Employee User & Profile
        $this->employeeUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'waiter@example.com',
            'status' => 'active',
        ]);
        $this->employeeUser->assignRole($waiterRole);
        $this->employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->employeeUser->id,
            'role_id' => $waiterRole->id,
            'employee_code' => 'EMP101',
            'full_name' => 'Nguyen Van Employee',
            'job_title' => 'Waiter',
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 6000000,
        ]);

        // Setup Colleague User & Profile
        $this->colleagueUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'email' => 'colleague@example.com',
            'status' => 'active',
        ]);
        $this->colleagueUser->assignRole($waiterRole);
        $this->colleague = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->colleagueUser->id,
            'role_id' => $waiterRole->id,
            'employee_code' => 'EMP102',
            'full_name' => 'Tran Van Colleague',
            'job_title' => 'Waiter',
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 6000000,
        ]);

        // Setup 3 more waiter employees to pass the 30% department leave quota constraint
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'status' => 'active',
            ]);
            $user->assignRole($waiterRole);
            Employee::create([
                'restaurant_id' => $this->restaurant->id,
                'branch_id' => $this->branch->id,
                'user_id' => $user->id,
                'role_id' => $waiterRole->id,
                'employee_code' => 'EMP10' . ($i + 3),
                'full_name' => 'Waiter ' . ($i + 3),
                'job_title' => 'Waiter',
                'status' => 'active',
                'compensation_type' => 'fixed',
                'base_salary' => 6000000,
            ]);
        }

        // Seed default KPI config metrics for this restaurant
        Artisan::call('db:seed', ['--class' => 'KpiMetricsSeeder']);
    }

    /**
     * Test access is restricted to valid employees.
     */
    public function test_employee_portal_requires_employee_profile()
    {
        // 1. Owner doesn't have an Employee profile, should get 403
        $response = $this->actingAs($this->owner)->get(route('employee-portal.index'));
        $response->assertStatus(403);

        // 2. Waiter has an Employee profile, should get 200
        $response2 = $this->actingAs($this->employeeUser)->get(route('employee-portal.index'));
        $response2->assertStatus(200);
        $response2->assertInertia(fn ($page) => $page->component('employee-portal/Dashboard'));
    }

    /**
     * Test getting dashboard data summary.
     */
    public function test_get_dashboard_data()
    {
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA-SANG',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);

        // Create a completed shift assignment to test hours worked calculation
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->employeeUser)->get(route('employee-portal.data'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'shifts_completed',
                'hours_worked',
                'estimated_earnings',
                'base_salary',
                'kpi_bonus',
                'kpi_commission'
            ],
            'kpis',
            'schedules',
            'notifications',
        ]);

        $data = $response->json();
        $this->assertEquals(1, $data['summary']['shifts_completed']);
        $this->assertEquals(4.0, (float)$data['summary']['hours_worked']);
    }

    /**
     * Test submitting a leave request.
     */
    public function test_submit_leave_request()
    {
        $response = $this->actingAs($this->employeeUser)->post(route('employee-portal.leaves.store'), [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Nghỉ mát gia đình',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee->id,
            'leave_type' => 'annual',
            'status' => 'pending',
            'reason' => 'Nghỉ mát gia đình',
        ]);
    }

    /**
     * Test retrieving salary history list.
     */
    public function test_view_salary_history()
    {
        $period = now()->format('Y-m');
        $salary = Salary::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'pay_period_start' => now()->startOfMonth()->toDateString(),
            'pay_period_end' => now()->endOfMonth()->toDateString(),
            'base_salary' => 6000000,
            'bonus_amount' => 150000,
            'deduction_amount' => 50000,
            'net_salary' => 6100000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->employeeUser)->get(route('employee-portal.salaries'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'salaries' => [
                '*' => [
                    'id',
                    'pay_period',
                    'base_salary',
                    'bonus_amount',
                    'deduction_amount',
                    'net_salary',
                    'status',
                    'adjustments',
                ]
            ]
        ]);
    }

    /**
     * Test shift swap suggestion, requesting swap, and responding.
     */
    public function test_shift_swap_flow()
    {
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA-SANG',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);

        $assign1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->startOfWeek()->toDateString(),
            'status' => 'scheduled',
        ]);

        $assign2 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->colleague->id,
            'shift_id' => $shift->id,
            'scheduled_date' => now()->startOfWeek()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        // 1. Get swaps dashboard structure
        $response = $this->actingAs($this->employeeUser)->get(route('employee-portal.swaps'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'swaps',
            'my_assignments',
            'colleague_assignments'
        ]);

        // 2. Request shift swap
        $response2 = $this->actingAs($this->employeeUser)->post(route('employee-portal.swaps.request'), [
            'requester_assignment_id' => $assign1->id,
            'receiver_assignment_id' => $assign2->id,
            'notes' => 'Đổi hộ mình ca thứ hai nhé',
        ]);
        $response2->assertStatus(200);
        $response2->assertJson(['success' => true]);

        $swap = ShiftSwap::first();
        $this->assertNotNull($swap);
        $this->assertEquals('pending', $swap->status);

        // 3. Colleague responds and accepts the swap
        $response3 = $this->actingAs($this->colleagueUser)->post(route('employee-portal.swaps.respond', $swap->id), [
            'action' => 'accept',
        ]);
        $response3->assertStatus(200);
        $response3->assertJson(['success' => true]);

        $swap->refresh();
        $this->assertEquals('accepted', $swap->status);
    }
}
