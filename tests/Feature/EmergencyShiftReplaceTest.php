<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\EmergencyShiftReplacedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Thay ca khẩn cấp: quản lý xếp người thay khi nhân viên nghỉ đột xuất; ca gốc thành
 * 'absent', ca thay liên kết ngược; báo Chủ. Guardrail: quản lý KHÔNG tự xếp mình.
 */
class EmergencyShiftReplaceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $managerUser;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private WorkShift $shift;

    private Employee $absent;

    private Employee $replacement;

    private Employee $managerEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->managerUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id, 'status' => 'active']);
        $this->managerUser->assignRole('manager');

        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'code' => 'TOI', 'name' => 'Ca tối', 'start_time' => '17:00', 'end_time' => '23:00',
        ]);

        $mk = fn (string $name, ?int $uid = null) => Employee::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'user_id' => $uid, 'employee_code' => 'E-'.strtoupper(substr(md5($name), 0, 5)),
            'full_name' => $name, 'phone' => '090'.random_int(1000000, 9999999),
            'citizen_id_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'HN', 'hire_date' => now()->subMonths(2)->toDateString(),
            'compensation_type' => 'shift', 'pay_rate' => 200000, 'base_salary' => 0,
            'job_title' => 'NV', 'status' => 'active',
        ]);
        $this->absent = $mk('Người Nghỉ');
        $this->replacement = $mk('Người Thay');
        $this->managerEmployee = $mk('Quản Lý', $this->managerUser->id);
    }

    private function makeAssignment(Employee $e): ScheduleAssignment
    {
        return ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'employee_id' => $e->id, 'shift_id' => $this->shift->id,
            'scheduled_date' => Carbon::now()->toDateString(), 'status' => 'confirmed',
        ]);
    }

    public function test_manager_can_replace_absent_employee(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment($this->absent);

        $this->actingAs($this->managerUser)->post('/employees/schedules/emergency-replace', [
            'assignment_id' => $assignment->id,
            'replacement_employee_id' => $this->replacement->id,
            'reason' => 'Nghỉ ốm đột xuất không báo trước',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('absent', $assignment->refresh()->status);
        $new = ScheduleAssignment::where('replaced_assignment_id', $assignment->id)->first();
        $this->assertNotNull($new);
        $this->assertEquals($this->replacement->id, $new->employee_id);
        $this->assertEquals('confirmed', $new->status);

        Notification::assertSentTo($this->owner, EmergencyShiftReplacedNotification::class);
    }

    public function test_manager_cannot_assign_self_as_replacement(): void
    {
        $assignment = $this->makeAssignment($this->absent);

        $this->actingAs($this->managerUser)->from('/employees')->post('/employees/schedules/emergency-replace', [
            'assignment_id' => $assignment->id,
            'replacement_employee_id' => $this->managerEmployee->id, // chính mình
            'reason' => 'Tự xếp mình tăng ca',
        ])->assertSessionHasErrors(['replacement_employee_id']);

        $this->assertEquals('confirmed', $assignment->refresh()->status);
        $this->assertDatabaseMissing('schedule_assignments', ['replaced_assignment_id' => $assignment->id]);
    }

    public function test_owner_may_assign_self(): void
    {
        Notification::fake();
        $assignment = $this->makeAssignment($this->absent);
        // Chủ cũng là một nhân viên có hồ sơ.
        $ownerEmployee = Employee::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id, 'employee_code' => 'E-OWNER',
            'full_name' => 'Chủ Quán', 'phone' => '0900000000',
            'citizen_id_number' => '111122223333', 'address' => 'HN',
            'hire_date' => now()->subYear()->toDateString(),
            'compensation_type' => 'fixed', 'pay_rate' => 0, 'base_salary' => 0,
            'job_title' => 'Chủ', 'status' => 'active',
        ]);

        $this->actingAs($this->owner)->post('/employees/schedules/emergency-replace', [
            'assignment_id' => $assignment->id,
            'replacement_employee_id' => $ownerEmployee->id,
            'reason' => 'Chủ vào thay ca trực tiếp',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_assignments', [
            'replaced_assignment_id' => $assignment->id,
            'employee_id' => $ownerEmployee->id,
        ]);
    }

    public function test_other_restaurant_cannot_replace(): void
    {
        $assignment = $this->makeAssignment($this->absent);
        $otherRestaurant = Restaurant::factory()->create();
        $otherManager = User::factory()->create(['restaurant_id' => $otherRestaurant->id, 'status' => 'active']);
        $otherManager->assignRole('manager');

        $response = $this->actingAs($otherManager)->post('/employees/schedules/emergency-replace', [
            'assignment_id' => $assignment->id,
            'replacement_employee_id' => $this->replacement->id,
            'reason' => 'Xâm phạm nhà hàng khác',
        ]);
        // TenantRule::exists chặn assignment_id/employee_id không thuộc nhà hàng → 422.
        $this->assertContains($response->status(), [403, 404, 302]);
        $this->assertEquals('confirmed', $assignment->refresh()->status);
    }
}
