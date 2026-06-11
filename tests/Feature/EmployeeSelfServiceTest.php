<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashierUser;

    private User $kitchenUser;

    private Employee $employee;

    private Employee $otherEmployee;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private WorkShift $shift;

    private Role $ownerRole;

    private Role $cashierRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
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

        // Setup employee 1 (cashier)
        $this->cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->cashierUser->assignRole($this->cashierRole);

        $this->employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // Setup employee 2 (kitchen)
        $this->kitchenUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->kitchenUser->assignRole($this->kitchenRole);

        $this->otherEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->kitchenUser->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
        ]);

        // Create active shift
        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);
    }

    public function test_employee_can_view_schedules_with_self_service_props(): void
    {
        // Access schedules page as employee
        $response = $this->actingAs($this->cashierUser)->get('/schedules');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('schedules/Index')
            ->has('shifts')
            ->has('employees')
            ->has('leaveRequests')
            ->where('myEmployeeId', $this->employee->id)
            ->where('isAdmin', false)
        );
    }

    public function test_employee_can_self_register_shift(): void
    {
        $regDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->cashierUser)->post('/schedules/register', [
            'shift_id' => $this->shift->id,
            'scheduled_date' => $regDate,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('schedule_assignments', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => $regDate.' 00:00:00',
            'status' => 'scheduled',
            'notes' => 'Tự đăng ký ca làm việc',
        ]);
    }

    public function test_employee_cannot_double_register_same_shift(): void
    {
        $regDate = Carbon::tomorrow()->toDateString();

        // Register first time
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => $regDate,
            'status' => 'scheduled',
            'notes' => 'Tự đăng ký ca làm việc',
        ]);

        // Try second time
        $response = $this->actingAs($this->cashierUser)->post('/schedules/register', [
            'shift_id' => $this->shift->id,
            'scheduled_date' => $regDate,
        ]);

        $response->assertSessionHasErrors(['scheduled_date']);
    }

    public function test_employee_can_submit_emergency_leave(): void
    {
        $startDate = Carbon::tomorrow()->toDateString();
        $endDate = Carbon::tomorrow()->addDay()->toDateString();

        $response = $this->actingAs($this->cashierUser)->post('/employees/leaves', [
            'employee_id' => $this->employee->id,
            'leave_type' => 'emergency',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Đơn xin nghỉ đột xuất khẩn cấp do việc gia đình',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'leave_type' => 'emergency',
            'start_date' => $startDate.' 00:00:00',
            'end_date' => $endDate.' 00:00:00',
            'status' => 'pending',
            'reason' => 'Đơn xin nghỉ đột xuất khẩn cấp do việc gia đình',
        ]);
    }

    public function test_employee_can_submit_resignation_leave(): void
    {
        $startDate = Carbon::tomorrow()->toDateString();
        $endDate = Carbon::tomorrow()->addDay()->toDateString();

        $response = $this->actingAs($this->cashierUser)->post('/employees/leaves', [
            'employee_id' => $this->employee->id,
            'leave_type' => 'resignation',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Đơn xin thôi việc',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $this->employee->id,
            'leave_type' => 'resignation',
            'start_date' => $startDate.' 00:00:00',
            'end_date' => $endDate.' 00:00:00',
            'status' => 'pending',
            'reason' => 'Đơn xin thôi việc',
        ]);
    }

    public function test_employee_can_submit_complaint_and_reported_by_name_is_always_masked_anonymous(): void
    {
        $occurredDate = Carbon::yesterday()->toDateString();

        // Submit complaint
        $response = $this->actingAs($this->cashierUser)->post('/violations', [
            'employee_id' => $this->otherEmployee->id,
            'violation_type' => 'Bòn rút tiền mặt / Gian lận ngân quỹ',
            'occurred_at' => $occurredDate,
            'description' => 'Có hành vi bất thường tại két thu ngân',
            'is_anonymous' => true,
        ]);

        $response->assertRedirect();

        // Verify violation report is recorded
        $report = ViolationReport::where('employee_id', $this->otherEmployee->id)->first();
        $this->assertNotNull($report);
        $this->assertEquals('Bòn rút tiền mặt / Gian lận ngân quỹ', $report->violation_type);

        // Get violations listing as Owner
        $ownerResponse = $this->actingAs($this->owner)->get('/violations');
        $ownerResponse->assertStatus(200);
        $ownerResponse->assertInertia(fn ($page) => $page
            ->component('violations/Index')
            ->has('reports', 1, fn ($reportPage) => $reportPage
                ->where('reported_by_name', 'Ẩn danh (Bảo mật hệ thống)')
                ->etc()
            )
        );
    }
}
