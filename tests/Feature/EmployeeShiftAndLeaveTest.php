<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantContext;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeShiftAndLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private Role $ownerRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        SetTenantContext::$enforceShiftLockInTests = true;

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

        $this->owner->assignRole($this->ownerRole);
    }

    protected function tearDown(): void
    {
        SetTenantContext::$enforceShiftLockInTests = false;
        parent::tearDown();
    }

    public function test_cashier_blocked_outside_scheduled_shift(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'branch_id' => $this->branch->id,
            'job_title' => 'Cashier',
            'status' => 'active',
        ]);

        // No shift scheduled for today. Accessing should trigger redirect and logout
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(auth()->check());
    }

    public function test_cashier_allowed_during_shift_with_grace_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-29 15:45:00')); // Friday 15:45

        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // Create shift: Friday 16:00 - 23:00
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        // Schedule assignment for today (2026-05-29)
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'scheduled',
        ]);

        // 15:45 is within 30-minute grace period of 16:00. Should allow access!
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertStatus(200);

        Carbon::setTestNow(); // Reset Carbon time
    }

    public function test_cashier_allowed_during_overnight_shift(): void
    {
        // Friday late night: 2026-05-29 23:45:00
        Carbon::setTestNow(Carbon::parse('2026-05-29 23:45:00'));

        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // Overnight shift: 22:00:00 to 06:00:00 next day
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca dem',
            'code' => 'CA_DEM',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'scheduled',
        ]);

        // Allowed on late night Friday
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertStatus(200);

        // Rollover: Saturday morning 03:00 on 2026-05-30
        Carbon::setTestNow(Carbon::parse('2026-05-30 03:00:00'));

        // Still allowed on overnight shift starting yesterday
        $response = $this->actingAs($cashierUser)->get('/dashboard');
        $response->assertStatus(200);

        Carbon::setTestNow(); // Reset
    }

    public function test_emergency_leave_auto_updates_schedules(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        // Schedule shifts for today & tomorrow
        $schedule1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $schedule2 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->copy()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Submit emergency leave request
        $leave = LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'requested_by' => $cashierUser->id,
            'leave_type' => 'emergency',
            'start_date' => today()->toDateString(),
            'end_date' => today()->copy()->addDay()->toDateString(),
            'reason' => 'Emergency leave',
            'status' => 'pending',
        ]);

        // Approve leave request
        $response = $this->actingAs($this->owner)->patch("/employees/leaves/{$leave->id}/approve");
        $response->assertRedirect();

        // Assert schedules updated
        $this->assertSame('leave_approved', $schedule1->fresh()->status);
        $this->assertSame('leave_approved', $schedule2->fresh()->status);
    }

    public function test_resignation_exit_workflow(): void
    {
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);

        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // Submit resignation request
        $leave = LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'requested_by' => $cashierUser->id,
            'leave_type' => 'resignation',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Resignation',
            'status' => 'pending',
        ]);

        // Approve resignation request
        $response = $this->actingAs($this->owner)->patch("/employees/leaves/{$leave->id}/approve");
        $response->assertRedirect();

        // Assert employee status is terminated
        $employeeFresh = Employee::withTrashed()->find($employee->id);
        $this->assertSame('terminated', $employeeFresh->status);

        // Assert user account is inactive
        $this->assertSame('inactive', $cashierUser->fresh()->status);

        // Assert soft deleted
        $this->assertTrue($employeeFresh->trashed());
    }

    public function test_leave_approval_with_replacement_workflow(): void
    {
        // 1. Setup two cashier employees
        $cashierUser1 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser1->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        $cashierUser2 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser2->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ca Sáng',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        // Scheduled shift for employee1
        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee1->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Leave request for employee1
        $leave = LeaveRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee1->id,
            'requested_by' => $cashierUser1->id,
            'leave_type' => 'annual',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Nghỉ phép năm',
            'status' => 'pending',
        ]);

        // 2. Fetch replacement suggestions
        $responseSuggestions = $this->actingAs($this->owner)
            ->get("/employees/leaves/{$leave->id}/replacements");

        $responseSuggestions->assertOk();
        $responseSuggestions->assertJsonFragment([
            'id' => $employee2->id,
            'full_name' => $employee2->full_name,
            'registered_available' => false,
        ]);

        // 3. Approve with replacement
        $responseApprove = $this->actingAs($this->owner)
            ->patch("/employees/leaves/{$leave->id}/approve", [
                'replacements' => [
                    $assignment->id => $employee2->id,
                ],
            ]);

        $responseApprove->assertRedirect();

        // Assertions
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame('leave_approved', $assignment->fresh()->status);

        // Check that a new assignment was created for employee2
        $newAssignment = ScheduleAssignment::where('employee_id', $employee2->id)
            ->where('shift_id', $shift->id)
            ->whereDate('scheduled_date', today())
            ->first();

        $this->assertNotNull($newAssignment);
        $this->assertSame('scheduled', $newAssignment->status);
    }
}
