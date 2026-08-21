<?php

namespace Tests\Feature;

use App\Models\OperationPolicy;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PolicyEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * OperationPolicyController::update() không có test nào trước đây — chính vì
 * vậy PolicyEnforcementService::logSensitiveAction() ghi 'event' => 'policy_check'
 * (không nằm trong ENUM created/updated/deleted của audit_logs) chưa từng bị bắt:
 * MỌI lần chủ nhà hàng đổi chính sách phân quyền, bản ghi audit đều âm thầm
 * không lưu được xuống DB (bị catch ở tầng khác nuốt mất hoặc lỗi 500 tuỳ nơi gọi).
 */
class OperationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $this->owner->assignRole($ownerRole);
    }

    public function test_owner_can_update_operation_policy_and_it_gets_audit_logged(): void
    {
        $response = $this->actingAs($this->owner)->post(route('operation-policies.update'), [
            'max_discount_percent_staff' => 10,
            'max_discount_percent_manager' => 30,
            'max_cancel_amount_staff' => 100000,
            'max_cancel_amount_manager' => 1000000,
            'staff_view_revenue' => false,
            'staff_view_salary' => false,
            'staff_view_cost_price' => false,
            'manager_view_other_salary' => false,
            'restrict_to_shift_hours' => true,
            'audit_all_changes' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('operation_policies', [
            'restaurant_id' => $this->owner->restaurant_id,
            'max_discount_percent_staff' => 10,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'policy_updated',
            'user_id' => $this->owner->id,
            'event' => 'updated',
        ]);
    }

    public function test_manager_and_staff_limits_cannot_be_inverted(): void
    {
        $response = $this->actingAs($this->owner)->post(route('operation-policies.update'), [
            'max_discount_percent_staff' => 30,
            'max_discount_percent_manager' => 10,
            'max_cancel_amount_staff' => 800000,
            'max_cancel_amount_manager' => 500000,
            'staff_view_revenue' => false,
            'staff_view_salary' => false,
            'staff_view_cost_price' => false,
            'manager_view_other_salary' => false,
            'restrict_to_shift_hours' => false,
            'audit_all_changes' => true,
        ]);

        $response->assertSessionHasErrors([
            'max_discount_percent_manager',
            'max_cancel_amount_manager',
        ]);
    }

    public function test_policy_service_requires_bypass_when_manager_exceeds_cancel_limit(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create([
            'restaurant_id' => $this->owner->restaurant_id,
            'status' => 'active',
        ]);
        $manager->assignRole($managerRole);

        OperationPolicy::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $this->owner->restaurant_id],
            [
                'max_cancel_amount_staff' => 0,
                'max_cancel_amount_manager' => 500000,
            ],
        );

        $decision = app(PolicyEnforcementService::class)->canCancelOrder($manager, 600000);

        $this->assertFalse($decision['allowed']);
        $this->assertTrue($decision['requires_approval']);
        $this->assertSame(500000.0, (float) $decision['max_allowed']);
    }
}
