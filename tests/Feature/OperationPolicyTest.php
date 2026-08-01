<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
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
}
