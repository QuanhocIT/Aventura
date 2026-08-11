<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Models\ViolationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kháng cáo biên bản sai phạm: nhân viên bị lập biên bản có phạt tiền được kháng cáo;
 * Chủ xét — chấp nhận thì WAIVE khoản cấn trừ lương (giữ audit), bác thì giữ phạt.
 * Kiểm tra cả tenant-scoping (IDOR) và quyền: chỉ chính chủ được kháng, chỉ Chủ được xét.
 */
class ViolationAppealTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private User $staffUser;
    private Employee $offender;

    protected function setUp(): void
    {
        parent::setUp();

        $manage = Permission::firstOrCreate(['name' => 'manage_violations', 'guard_name' => 'web']);
        $view = Permission::firstOrCreate(['name' => 'view_violations', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'report_violations', 'guard_name' => 'web']);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo([$manage, $view]);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->staffUser = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->staffUser->assignRole('cashier');
        $this->offender = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->staffUser->id,
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 10000000,
        ]);
    }

    /** Lập biên bản đã xử lý + phạt tiền qua endpoint resolve (tạo cấn trừ lương thật). */
    private function resolvedReportWithPenalty(float $penalty = 500000): ViolationReport
    {
        $report = ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->offender->id,
            'reported_by' => $this->owner->id,
            'is_anonymous' => false,
            'violation_type' => 'Đi trễ',
            'severity' => 'low',
            'description' => 'Đi trễ nhiều lần trong tháng.',
            'penalty_amount' => 0,
            'occurred_at' => Carbon::now(),
            'status' => 'open',
        ]);

        $this->actingAs($this->owner)->post("/violations/{$report->id}/resolve", [
            'severity' => 'high',
            'penalty_amount' => $penalty,
            'status' => 'resolved',
            'resolution_notes' => 'Xử lý theo nội quy.',
        ])->assertRedirect();

        return $report->refresh();
    }

    public function test_resolve_creates_salary_deduction(): void
    {
        $report = $this->resolvedReportWithPenalty(500000);

        $this->assertEquals('resolved', $report->status);
        $this->assertEqualsWithDelta(500000, (float) $report->penalty_amount, 0.01);
        $this->assertDatabaseHas('salary_adjustments', [
            'reference_type' => ViolationReport::class,
            'reference_id' => $report->id,
            'type' => 'violation',
            'status' => 'applied',
        ]);
    }

    public function test_offender_can_appeal(): void
    {
        $report = $this->resolvedReportWithPenalty();

        $this->actingAs($this->staffUser)->post("/violations/{$report->id}/appeal", [
            'appeal_reason' => 'Hôm đó tôi kẹt xe do tai nạn giao thông, có ảnh chứng minh.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('pending', $report->refresh()->appeal_status);
    }

    public function test_non_offender_cannot_appeal(): void
    {
        $report = $this->resolvedReportWithPenalty();
        $other = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $other->assignRole('cashier');

        $this->actingAs($other)->post("/violations/{$report->id}/appeal", [
            'appeal_reason' => 'Tôi không liên quan nhưng vẫn thử kháng cáo hộ.',
        ])->assertForbidden();

        $this->assertEquals('none', $report->refresh()->appeal_status);
    }

    public function test_accepted_appeal_waives_salary_deduction(): void
    {
        $report = $this->resolvedReportWithPenalty(500000);
        $this->actingAs($this->staffUser)->post("/violations/{$report->id}/appeal", [
            'appeal_reason' => 'Tôi có lý do chính đáng và bằng chứng kèm theo đây.',
        ])->assertRedirect();

        $this->actingAs($this->owner)->post("/violations/{$report->id}/appeal/review", [
            'decision' => 'accepted',
            'appeal_review_note' => 'Lý do hợp lý, hoàn lại tiền phạt.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertEquals('accepted', $report->appeal_status);
        $this->assertEquals('dismissed', $report->status);
        // Khoản cấn trừ bị waive → không còn tính vào lương.
        $this->assertDatabaseHas('salary_adjustments', [
            'reference_type' => ViolationReport::class,
            'reference_id' => $report->id,
            'type' => 'violation',
            'status' => 'waived',
        ]);
        // Lương của nhân viên không còn khoản khấu trừ vi phạm này.
        $adj = SalaryAdjustment::withoutGlobalScopes()
            ->where('reference_id', $report->id)->where('type', 'violation')->first();
        $salary = \App\Models\Salary::withoutGlobalScopes()->find($adj->salary_id);
        $this->assertEqualsWithDelta(0, (float) $salary->deduction_amount, 0.01);
    }

    public function test_rejected_appeal_keeps_penalty(): void
    {
        $report = $this->resolvedReportWithPenalty(500000);
        $this->actingAs($this->staffUser)->post("/violations/{$report->id}/appeal", [
            'appeal_reason' => 'Tôi muốn kháng cáo nhưng lý do không thuyết phục lắm.',
        ])->assertRedirect();

        $this->actingAs($this->owner)->post("/violations/{$report->id}/appeal/review", [
            'decision' => 'rejected',
            'appeal_review_note' => 'Bằng chứng không đủ, giữ nguyên xử lý.',
        ])->assertRedirect();

        $report->refresh();
        $this->assertEquals('rejected', $report->appeal_status);
        $this->assertEquals('resolved', $report->status);
        $this->assertDatabaseHas('salary_adjustments', [
            'reference_id' => $report->id,
            'type' => 'violation',
            'status' => 'applied',
        ]);
    }

    public function test_manager_from_other_restaurant_cannot_review(): void
    {
        $report = $this->resolvedReportWithPenalty();
        $this->actingAs($this->staffUser)->post("/violations/{$report->id}/appeal", [
            'appeal_reason' => 'Đơn kháng cáo hợp lệ của tôi cần được xem xét kỹ.',
        ])->assertRedirect();

        $otherRestaurant = Restaurant::factory()->create();
        $otherOwner = User::factory()->create(['restaurant_id' => $otherRestaurant->id, 'status' => 'active']);
        $otherOwner->assignRole('owner');

        // Route-model binding đã scope theo tenant (IDOR fix) → 404 (không tìm thấy)
        // an toàn hơn 403. Chấp nhận cả hai; điều quan trọng là KHÔNG xử lý được.
        $response = $this->actingAs($otherOwner)->post("/violations/{$report->id}/appeal/review", [
            'decision' => 'accepted',
        ]);
        $this->assertContains($response->status(), [403, 404]);

        // Không đổi trạng thái.
        $this->assertEquals('pending', $report->refresh()->appeal_status);
    }
}
