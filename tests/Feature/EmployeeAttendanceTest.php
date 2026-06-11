<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $cashierUser;

    private Employee $employee;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private WorkShift $shift;

    private Role $ownerRole;

    private Role $cashierRole;

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

        $this->owner->assignRole($this->ownerRole);

        // Setup cashier and employee
        $this->cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
        $this->cashierUser->assignRole($this->cashierRole);

        $this->employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // Create active shift: 16:00 to 23:00
        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);
    }

    public function test_staff_cannot_check_in_if_no_shift_scheduled_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-29 15:45:00')); // Friday 15:45

        // No schedule assignment created

        $response = $this->actingAs($this->cashierUser)->post('/schedules/check-in');
        $response->assertSessionHasErrors(['email']);

        Carbon::setTestNow();
    }

    public function test_staff_can_check_in_during_grace_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-29 15:45:00')); // Friday 15:45 (within 30 mins before 16:00)

        // Schedule shift for today (2026-05-29)
        $schedule = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->cashierUser)->post('/schedules/check-in');
        $response->assertRedirect();

        $this->assertSame('checked_in', $schedule->fresh()->status);
        $this->assertNotNull($schedule->fresh()->check_in_at);

        Carbon::setTestNow();
    }

    public function test_staff_can_check_out(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-29 23:15:00'));

        $schedule = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'checked_in',
            'check_in_at' => '2026-05-29 15:55:00',
        ]);

        $response = $this->actingAs($this->cashierUser)->post('/schedules/check-out');
        $response->assertRedirect();

        $this->assertSame('completed', $schedule->fresh()->status);
        $this->assertNotNull($schedule->fresh()->check_out_at);

        Carbon::setTestNow();
    }

    public function test_admin_can_manually_check_in_employee(): void
    {
        $schedule = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->owner)->post('/schedules/check-in-employee', [
            'assignment_id' => $schedule->id,
            'notes' => 'Quên bấm giờ checkin',
        ]);

        $response->assertRedirect();
        $this->assertSame('checked_in', $schedule->fresh()->status);
        $this->assertNotNull($schedule->fresh()->check_in_at);
        $this->assertSame('Quên bấm giờ checkin', $schedule->fresh()->notes);
    }

    public function test_admin_can_manually_check_out_employee(): void
    {
        $schedule = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'checked_in',
            'check_in_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post('/schedules/check-out-employee', [
            'assignment_id' => $schedule->id,
            'notes' => 'Quên checkout',
        ]);

        $response->assertRedirect();
        $this->assertSame('completed', $schedule->fresh()->status);
        $this->assertNotNull($schedule->fresh()->check_out_at);
        $this->assertSame('Quên checkout', $schedule->fresh()->notes);
    }

    public function test_admin_can_mark_employee_absent(): void
    {
        $schedule = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-05-29',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->owner)->post('/schedules/absent-employee', [
            'assignment_id' => $schedule->id,
            'notes' => 'Nghỉ không lý do',
        ]);

        $response->assertRedirect();
        $this->assertSame('absent', $schedule->fresh()->status);
        $this->assertSame('Nghỉ không lý do', $schedule->fresh()->notes);
    }
}
