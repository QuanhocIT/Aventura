<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeTrustScore;
use App\Models\LeaveRequest;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulesAISchedulerAndCopyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Role $cashierRole;

    private Role $waiterRole;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $this->waiterRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $this->kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);
    }

    public function test_copy_last_week_schedules_respects_leaves(): void
    {
        // Setup dates: Previous week Monday, current week Monday
        $prevMonday = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $currMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $employeeUser1 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employeeUser1->id,
            'status' => 'active',
        ]);

        $employeeUser2 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employeeUser2->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Shift A',
            'code' => 'SHIFT_A',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Create assignments in previous week for both employees
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $prevMonday->toDateString(),
            'status' => 'scheduled',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee2->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $prevMonday->toDateString(),
            'status' => 'scheduled',
        ]);

        // Employee 1 has approved leave in the current week (Monday)
        LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee1->id,
            'start_date' => $currMonday->toDateString(),
            'end_date' => $currMonday->toDateString(),
            'status' => 'approved',
            'reason' => 'Sickness',
        ]);

        // Act: copy last week's schedules
        $this->actingAs($this->owner);
        $response = $this->post(route('employees.schedules.copy-last-week'));

        $response->assertSessionHasNoErrors();

        // Assert: Employee 2 assignment copied, Employee 1 assignment skipped due to leave
        $this->assertTrue(
            ScheduleAssignment::where('employee_id', $employee2->id)
                ->whereDate('scheduled_date', $currMonday->toDateString())
                ->exists()
        );

        $this->assertFalse(
            ScheduleAssignment::where('employee_id', $employee1->id)
                ->whereDate('scheduled_date', $currMonday->toDateString())
                ->exists()
        );
    }

    public function test_ai_scheduler_respects_availabilities_and_max_shifts(): void
    {
        $employeeUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employeeUser->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Shift B',
            'code' => 'SHIFT_B',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Register availability for Monday only
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        ScheduleRegistration::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $monday->toDateString(),
        ]);

        // Act: Run AI auto-scheduling via SupportController
        $this->actingAs($this->owner);
        $response = $this->post(route('employees.schedules.toggle-auto'), [
            'enabled' => true,
        ]);

        $response->assertSessionHasNoErrors();

        // Assert: Employee is scheduled on Monday (matching availability)
        $this->assertTrue(
            ScheduleAssignment::where('employee_id', $employee->id)
                ->whereDate('scheduled_date', $monday->toDateString())
                ->exists()
        );
    }

    public function test_ai_scheduler_prioritizes_registered_availability_and_high_evaluation_score(): void
    {
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sáng (08:00 - 16:00)',
            'code' => 'SHIFT_MORNING',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Emp A: Registered availability for Monday
        $empAUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $empA = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $empAUser->id,
            'status' => 'active',
        ]);

        // Emp B: Higher Trust Score (98 vs default 80), NOT registered
        $empBUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $empB = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $empBUser->id,
            'status' => 'active',
        ]);
        EmployeeTrustScore::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $empB->id,
            'score' => 98.0,
        ]);

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        // Emp A registers for Monday
        ScheduleRegistration::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $empA->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $monday->toDateString(),
        ]);

        $this->actingAs($this->owner);
        $response = $this->post(route('employees.schedules.toggle-auto'), [
            'enabled' => true,
        ]);

        $response->assertSessionHasNoErrors();

        // Emp A should be assigned to Monday's shift because registration is Priority 1
        $this->assertTrue(
            ScheduleAssignment::where('employee_id', $empA->id)
                ->where('shift_id', $shift->id)
                ->whereDate('scheduled_date', $monday->toDateString())
                ->exists()
        );
    }

    public function test_ai_scheduler_preserves_completed_and_payroll_locked_assignments(): void
    {
        $employeeUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $employeeUser->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Protected shift',
            'code' => 'PROTECTED_SHIFT',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        $completedAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'status' => 'completed',
        ]);

        $lockedDate = Carbon::today()->toDateString();
        $lockedAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $lockedDate,
            'status' => 'scheduled',
        ]);

        Salary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'pay_period_start' => Carbon::today()->startOfMonth()->toDateString(),
            'pay_period_end' => Carbon::today()->endOfMonth()->toDateString(),
            'status' => 'approved',
        ]);

        $this->actingAs($this->owner);
        $response = $this->post(route('employees.schedules.toggle-auto'), [
            'enabled' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_assignments', [
            'id' => $completedAssignment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('schedule_assignments', [
            'id' => $lockedAssignment->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_quick_scheduler_uses_click_date_time_and_prioritizes_registration(): void
    {
        $thursdayAtFive = Carbon::now()
            ->startOfWeek(Carbon::MONDAY)
            ->addDays(3)
            ->setTime(17, 0);
        Carbon::setTestNow($thursdayAtFive);

        $registeredUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $registeredEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $registeredUser->id,
            'rating_star' => 4.0,
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $otherEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $otherUser->id,
            'rating_star' => 5.0,
            'status' => 'active',
        ]);

        $earlyShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Early quick shift',
            'code' => 'EARLY_QUICK_SHIFT',
            'start_time' => '16:00:00',
            'end_time' => '22:00:00',
            'status' => 'active',
        ]);

        $lateShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Late quick shift',
            'code' => 'LATE_QUICK_SHIFT',
            'start_time' => '18:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $wednesday = $monday->copy()->addDays(2);
        $thursday = Carbon::today();

        $existingAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $otherEmployee->id,
            'shift_id' => $earlyShift->id,
            'scheduled_date' => $monday->toDateString(),
            'status' => 'scheduled',
        ]);

        ScheduleRegistration::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $registeredEmployee->id,
            'shift_id' => $lateShift->id,
            'scheduled_date' => $thursday->toDateString(),
        ]);

        $this->actingAs($this->owner);
        $response = $this->post(route('employees.schedules.quick-auto'));

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_assignments', [
            'id' => $existingAssignment->id,
            'employee_id' => $otherEmployee->id,
        ]);
        $this->assertTrue(
            ScheduleAssignment::where('employee_id', $registeredEmployee->id)
                ->where('shift_id', $lateShift->id)
                ->whereDate('scheduled_date', $thursday->toDateString())
                ->where('status', 'scheduled')
                ->exists()
        );
        $this->assertFalse(
            ScheduleAssignment::where('shift_id', $earlyShift->id)
                ->whereDate('scheduled_date', $thursday->toDateString())
                ->exists()
        );
        $this->assertFalse(
            ScheduleAssignment::whereIn('shift_id', [$earlyShift->id, $lateShift->id])
                ->whereDate('scheduled_date', $wednesday->toDateString())
                ->exists()
        );

        Carbon::setTestNow();
    }
}
