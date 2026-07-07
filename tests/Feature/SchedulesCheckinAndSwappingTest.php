<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulesCheckinAndSwappingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $employeeUser1;
    private User $employeeUser2;
    private Employee $employee1;
    private Employee $employee2;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $employeeRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'checkin_radius_meters' => 100,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        // Create two employees
        $this->employeeUser1 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->employeeUser1->assignRole($employeeRole);
        $this->employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->employeeUser1->id,
            'status' => 'active',
        ]);

        $this->employeeUser2 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->employeeUser2->assignRole($employeeRole);
        $this->employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->employeeUser2->id,
            'status' => 'active',
        ]);

        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Morning Shift',
            'code' => 'MORNING_SHIFT',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_gps_checkin_success_within_radius(): void
    {
        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Act: Employee 1 checks in close to restaurant (same coordinates)
        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
        ]);

        $response->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertEquals('checked_in', $assignment->status);
        $this->assertNotNull($assignment->check_in_at);
    }

    public function test_gps_checkin_fails_outside_radius(): void
    {
        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Act: Employee 1 checks in far away (Vung Tau coordinates)
        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.4114,
            'longitude' => 107.1362,
        ]);

        $response->assertSessionHasErrors(['email']);
        $assignment->refresh();
        $this->assertEquals('scheduled', $assignment->status);
        $this->assertNull($assignment->check_in_at);
    }

    public function test_qr_code_checkin_success(): void
    {
        // Setup daily QR
        $this->restaurant->update([
            'qr_checkin_code' => 'QR_TEST123',
            'qr_checkin_expires_at' => now()->addHours(1),
        ]);

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'qr_code' => 'QR_TEST123',
        ]);

        $response->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertEquals('checked_in', $assignment->status);
    }

    public function test_qr_code_checkin_fails_invalid_or_expired(): void
    {
        // Setup expired QR
        $this->restaurant->update([
            'qr_checkin_code' => 'QR_EXPIRED',
            'qr_checkin_expires_at' => now()->subMinutes(10),
        ]);

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'qr_code' => 'QR_EXPIRED',
        ]);

        $response->assertSessionHasErrors(['email']);
        $assignment->refresh();
        $this->assertEquals('scheduled', $assignment->status);
    }

    public function test_dynamic_qr_checkin_success(): void
    {
        // Setup a daily QR code so the system expects a QR code verification
        $this->restaurant->update([
            'qr_checkin_code' => 'QR_STATIC_TEST',
            'qr_checkin_expires_at' => now()->addHours(1),
        ]);

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Calculate a valid dynamic QR code token
        $nowTs = now()->timestamp;
        $chunk = floor($nowTs / 20);
        $validToken = 'DYN_' . substr(hash_hmac('sha256', (string)$chunk, (string)$this->restaurant->id . '_checkin_secret_key_123'), 0, 8);

        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'qr_code' => $validToken,
        ]);

        $response->assertSessionHasNoErrors();
        $assignment->refresh();
        $this->assertEquals('checked_in', $assignment->status);
    }

    public function test_dynamic_qr_checkin_fails_invalid_token(): void
    {
        $this->restaurant->update([
            'qr_checkin_code' => 'QR_STATIC_TEST',
            'qr_checkin_expires_at' => now()->addHours(1),
        ]);

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        $invalidToken = 'DYN_INVALID';

        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'qr_code' => $invalidToken,
        ]);

        $response->assertSessionHasErrors(['email']);
        $assignment->refresh();
        $this->assertEquals('scheduled', $assignment->status);
    }

    public function test_shift_swapping_complete_flow(): void
    {
        // Create assignments for employee 1 and employee 2
        $assign1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-06-08', // Monday
            'status' => 'scheduled',
        ]);

        $assign2 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee2->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-06-09', // Tuesday
            'status' => 'scheduled',
        ]);

        // 1. Employee 1 requests swap with Employee 2
        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.swap.request'), [
            'requester_assignment_id' => $assign1->id,
            'receiver_assignment_id' => $assign2->id,
            'notes' => 'Muốn đổi ca thứ Hai lấy thứ Ba',
        ]);

        $response->assertSessionHasNoErrors();
        $swap = ShiftSwap::first();
        $this->assertNotNull($swap);
        $this->assertEquals('pending', $swap->status);

        // 2. Employee 2 accepts the swap
        $this->actingAs($this->employeeUser2);
        $response = $this->post(route('schedules.swap.accept', ['swap' => $swap->id]));
        $response->assertSessionHasNoErrors();
        $swap->refresh();
        $this->assertEquals('accepted', $swap->status);

        // 3. Admin/Owner approves the swap
        $this->actingAs($this->owner);
        $response = $this->patch(route('schedules.swap.approve', ['swap' => $swap->id]));
        $response->assertSessionHasNoErrors();

        $swap->refresh();
        $this->assertEquals('approved', $swap->status);

        // Assert employee IDs are swapped on the assignments
        $assign1->refresh();
        $assign2->refresh();
        $this->assertEquals($this->employee2->id, $assign1->employee_id);
        $this->assertEquals($this->employee1->id, $assign2->employee_id);
    }
}
