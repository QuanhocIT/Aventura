<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\ApprovalAuthorityService;
use App\Services\ApprovalService;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalPolicyGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->owner->assignRole('owner');
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->manager->assignRole('manager');
        $this->branch->update(['manager_user_id' => $this->manager->id]);

        ApprovalPolicyDefaults::applyTo((int) $this->restaurant->id);
        ApprovalAuthorityService::flushManagedBranchCache($this->manager->id);
    }

    public function test_owner_can_update_sla_and_audit_policy_changes(): void
    {
        $policy = ApprovalPolicy::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'order_refund')
            ->whereNull('branch_id')
            ->firstOrFail();

        $response = $this->actingAs($this->owner)->put('/approvals/policies', [
            'policies' => [[
                'operation_type' => 'order_refund',
                'branch_id' => null,
                'manager_can_approve' => true,
                'manager_limit_amount' => 500000,
                'manager_daily_limit' => 2000000,
                'manager_monthly_limit' => 20000000,
                'requires_owner_countersign' => true,
                'auto_escalate_after_minutes' => 30,
                'is_active' => true,
            ]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('approval_policies', [
            'id' => $policy->id,
            'auto_escalate_after_minutes' => 30,
            'requires_owner_countersign' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approval_policy_updated',
            'event' => 'updated',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_duplicate_operation_scope_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->from('/approvals/policies')->put('/approvals/policies', [
            'policies' => [
                $this->policyPayload(),
                $this->policyPayload(),
            ],
        ]);

        $response->assertRedirect('/approvals/policies');
        $response->assertSessionHasErrors('policies.1.operation_type');
    }

    public function test_active_delegation_can_temporarily_open_a_policy_but_respects_its_cap(): void
    {
        ApprovalPolicy::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'order_refund')
            ->whereNull('branch_id')
            ->update(['manager_can_approve' => false]);

        $this->actingAs($this->owner)->post('/approvals/delegations', [
            'delegatee_id' => $this->manager->id,
            'module' => 'all',
            'max_amount_limit' => 200000,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'reason' => 'Chủ đi công tác',
        ])->assertSessionHasNoErrors();

        $request = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'requester_id' => User::factory()->create(['restaurant_id' => $this->restaurant->id])->id,
            'operation_type' => 'order_refund',
            'operation_data' => ['refund_amount' => 100000],
            'amount_involved' => 100000,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);

        $decision = app(ApprovalAuthorityService::class)->decide($this->manager, $request);
        $this->assertTrue($decision->allowed, $decision->reason ?? '');

        $request->update(['amount_involved' => 250000]);
        $decision = app(ApprovalAuthorityService::class)->decide($this->manager, $request->refresh());
        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->shouldEscalate);
        $this->assertStringContainsString('ủy quyền', mb_strtolower($decision->reason));
        $this->assertDatabaseHas('approval_delegations', ['delegatee_id' => $this->manager->id, 'is_active' => 1]);
    }

    public function test_auto_escalation_moves_stale_requests_to_owner(): void
    {
        ApprovalPolicy::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'order_refund')
            ->whereNull('branch_id')
            ->update(['auto_escalate_after_minutes' => 5]);

        $request = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'requester_id' => $this->manager->id,
            'operation_type' => 'order_refund',
            'operation_data' => ['refund_amount' => 100000],
            'amount_involved' => 100000,
            'policy_id' => ApprovalPolicy::where('restaurant_id', $this->restaurant->id)->where('operation_type', 'order_refund')->whereNull('branch_id')->value('id'),
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
        $request->created_at = now()->subMinutes(10);
        $request->saveQuietly();

        $count = app(ApprovalService::class)->autoEscalateOverdue((int) $this->restaurant->id);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $request->id,
            'status' => ApprovalRequest::STATUS_ESCALATED,
        ]);
        $this->assertDatabaseHas('approval_decisions', [
            'approval_request_id' => $request->id,
            'decision' => 'escalated',
        ]);
    }

    private function policyPayload(): array
    {
        return [
            'operation_type' => 'order_refund',
            'branch_id' => null,
            'manager_can_approve' => true,
            'manager_limit_amount' => 500000,
            'manager_daily_limit' => 2000000,
            'manager_monthly_limit' => 20000000,
            'requires_owner_countersign' => false,
            'auto_escalate_after_minutes' => null,
            'is_active' => true,
        ];
    }
}
