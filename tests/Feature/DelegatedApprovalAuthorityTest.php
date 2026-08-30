<?php

namespace Tests\Feature;

use App\Models\ApprovalDecision;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\ApprovalAuthorityService;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ranh giới thẩm quyền của Quản lý chi nhánh.
 *
 * Đây là loại ràng buộc rất dễ bị vô hiệu hóa vô tình khi refactor sau này, nên
 * mỗi dòng trong danh sách "quyền không giao cho Quản lý" có một test riêng.
 */
class DelegatedApprovalAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    private User $manager;

    private User $cashier;

    private ApprovalAuthorityService $authority;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $this->owner = $this->makeUser('owner');
        $this->manager = $this->makeUser('manager');
        $this->cashier = $this->makeUser('cashier');

        $this->branch->update(['manager_user_id' => $this->manager->id]);

        ApprovalPolicyDefaults::applyTo((int) $this->restaurant->id);

        $this->authority = app(ApprovalAuthorityService::class);
        ApprovalAuthorityService::flushManagedBranchCache($this->manager->id);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeRequest(array $overrides = []): ApprovalRequest
    {
        return ApprovalRequest::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'requester_id' => $this->cashier->id,
            'operation_type' => 'order_refund',
            'operation_data' => ['refund_amount' => 100_000],
            'amount_involved' => 100_000,
            'status' => ApprovalRequest::STATUS_PENDING,
        ], $overrides));
    }

    // ── Quyền được mở ────────────────────────────────────────────────────────

    public function test_manager_can_approve_refund_within_limit(): void
    {
        $decision = $this->authority->decide($this->manager, $this->makeRequest());

        $this->assertTrue($decision->allowed, $decision->reason ?? '');
        $this->assertSame('policy_delegated', $decision->basis);
    }

    public function test_owner_can_approve_anything_in_own_restaurant(): void
    {
        $request = $this->makeRequest(['operation_type' => 'employee_create', 'amount_involved' => null]);

        $this->assertTrue($this->authority->decide($this->owner, $request)->allowed);
    }

    // ── Hạn mức ──────────────────────────────────────────────────────────────

    public function test_manager_cannot_approve_refund_above_per_request_limit(): void
    {
        // Mặc định: 500.000đ một lần.
        $request = $this->makeRequest([
            'operation_data' => ['refund_amount' => 900_000],
            'amount_involved' => 900_000,
        ]);

        $decision = $this->authority->decide($this->manager, $request);

        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->shouldEscalate, 'Vượt hạn mức phải đẩy lên Chủ, không phải chặn im lặng.');
        $this->assertStringContainsString('vượt hạn mức', mb_strtolower($decision->reason));
    }

    public function test_daily_limit_accumulates_across_requests(): void
    {
        // Hạn mức ngày mặc định của hoàn tiền là 2.000.000đ.
        ApprovalDecision::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'approval_request_id' => $this->makeRequest()->id,
            'decided_by' => $this->manager->id,
            'decided_by_name' => $this->manager->name,
            'decided_by_role' => 'manager',
            'decision' => 'approved',
            'operation_type' => 'order_refund',
            'amount_involved' => 1_800_000,
            'authority_basis' => 'policy_delegated',
        ]);

        $decision = $this->authority->decide($this->manager, $this->makeRequest([
            'operation_data' => ['refund_amount' => 400_000],
            'amount_involved' => 400_000,
        ]));

        $this->assertFalse($decision->allowed, 'Chia nhỏ nhiều đơn để lách hạn mức ngày phải bị chặn.');
        $this->assertStringContainsString('trong ngày', $decision->reason);
    }

    // ── Danh sách cấm tuyệt đối ──────────────────────────────────────────────

    #[DataProvider('forbiddenOperations')]
    public function test_manager_can_never_approve_forbidden_operation(string $operationType): void
    {
        // Cố tình bật công tắc trong CSDL: chặn cứng phải thắng cấu hình.
        ApprovalPolicy::withoutGlobalScopes()->updateOrCreate(
            [
                'restaurant_id' => $this->restaurant->id,
                'operation_type' => $operationType,
                'branch_id' => null,
            ],
            ['manager_can_approve' => true, 'is_active' => true],
        );

        $decision = $this->authority->decide(
            $this->manager,
            $this->makeRequest(['operation_type' => $operationType, 'amount_involved' => null]),
        );

        $this->assertFalse($decision->allowed, "{$operationType} không được phép giao cho Quản lý.");
    }

    public static function forbiddenOperations(): array
    {
        return [
            'đổi giá gốc toàn chuỗi' => ['warehouse_price_update'],
            'thiết lập kho tổng' => ['warehouse_set_central'],
            'tạo tài khoản nhân viên' => ['employee_create'],
            'nâng quyền tài khoản' => ['user_role_grant'],
            'rút tiền' => ['withdrawal_request'],
            'đổi tài khoản ngân hàng' => ['bank_account_update'],
            'đổi cấu hình thuế' => ['tax_config_update'],
            'xóa đơn hàng' => ['order_delete'],
            'xóa nhật ký kiểm toán' => ['audit_log_delete'],
        ];
    }

    // ── Tự phê duyệt ─────────────────────────────────────────────────────────

    public function test_manager_cannot_approve_own_request(): void
    {
        $request = $this->makeRequest(['requester_id' => $this->manager->id]);

        $decision = $this->authority->decide($this->manager, $request);

        $this->assertFalse($decision->allowed);
        $this->assertStringContainsString('tự phê duyệt', $decision->reason);
    }

    public function test_owner_can_approve_own_request(): void
    {
        $request = $this->makeRequest(['requester_id' => $this->owner->id]);

        $decision = $this->authority->decide($this->owner, $request);

        $this->assertTrue($decision->allowed);
        $this->assertSame('owner_inherent', $decision->basis);
    }

    public function test_manager_cannot_approve_request_that_affects_themselves(): void
    {
        // Chốt chặn gián tiếp: người tạo là nhân viên khác, nhưng người hưởng
        // lợi lại chính là Quản lý — ví dụ duyệt tăng ca hoặc phạt cho chính mình.
        $managerEmployee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->manager->id,
        ]);

        $request = $this->makeRequest([
            'requester_id' => $this->cashier->id,
            'operation_type' => 'inventory_waste',
            'subject_employee_id' => $managerEmployee->id,
            'amount_involved' => 50_000,
        ]);

        $decision = $this->authority->decide($this->manager, $request);

        $this->assertFalse($decision->allowed);
        $this->assertStringContainsString('liên quan tới chính mình', $decision->reason);
    }

    // ── Phạm vi chi nhánh ────────────────────────────────────────────────────

    public function test_manager_cannot_approve_request_from_another_branch(): void
    {
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $decision = $this->authority->decide(
            $this->manager,
            $this->makeRequest(['branch_id' => $otherBranch->id]),
        );

        $this->assertFalse($decision->allowed);
        $this->assertStringContainsString('chi nhánh', $decision->reason);
    }

    public function test_manager_cannot_approve_chain_wide_request(): void
    {
        $decision = $this->authority->decide(
            $this->manager,
            $this->makeRequest(['branch_id' => null]),
        );

        $this->assertFalse($decision->allowed);
    }

    public function test_user_from_another_restaurant_is_rejected(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $stranger = User::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $stranger->assignRole('owner');

        $this->assertFalse($this->authority->decide($stranger, $this->makeRequest())->allowed);
    }

    // ── Trạng thái ───────────────────────────────────────────────────────────

    public function test_manager_cannot_touch_escalated_request(): void
    {
        $request = $this->makeRequest(['status' => ApprovalRequest::STATUS_ESCALATED]);

        $this->assertFalse($this->authority->decide($this->manager, $request)->allowed);
        $this->assertTrue($this->authority->decide($this->owner, $request)->allowed);
    }

    public function test_already_decided_request_cannot_be_decided_again(): void
    {
        $request = $this->makeRequest(['status' => ApprovalRequest::STATUS_APPROVED]);

        $this->assertFalse($this->authority->decide($this->owner, $request)->allowed);
    }

    // ── Chính sách chưa cấu hình ─────────────────────────────────────────────

    public function test_manager_denied_when_policy_missing(): void
    {
        ApprovalPolicy::withoutGlobalScopes()->where('restaurant_id', $this->restaurant->id)->delete();

        $decision = $this->authority->decide($this->manager, $this->makeRequest());

        $this->assertFalse($decision->allowed, 'Thiếu cấu hình phải nghiêng về phía chặt hơn.');
    }

    public function test_branch_policy_overrides_chain_policy(): void
    {
        ApprovalPolicy::withoutGlobalScopes()->updateOrCreate(
            [
                'restaurant_id' => $this->restaurant->id,
                'operation_type' => 'order_refund',
                'branch_id' => $this->branch->id,
            ],
            ['manager_can_approve' => false, 'is_active' => true],
        );

        $this->assertFalse($this->authority->decide($this->manager, $this->makeRequest())->allowed);
    }

    // ── Truy cập màn hình ────────────────────────────────────────────────────

    public function test_manager_can_open_the_approval_queue(): void
    {
        // Trước đây màn hình này chặn cứng theo isOwner(), nên Quản lý có quyền
        // duyệt mà không có nơi thực hiện.
        $this->actingAs($this->manager)
            ->get(route('approvals.index'))
            ->assertOk();
    }

    public function test_manager_only_sees_own_branch_in_the_queue(): void
    {
        $otherBranch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $mine = $this->makeRequest();
        $theirs = $this->makeRequest(['branch_id' => $otherBranch->id]);

        $response = $this->actingAs($this->manager)->get(route('approvals.index'));
        $ids = collect($response->viewData('page')['props']['approvals'])->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id), 'Quản lý không được thấy yêu cầu của chi nhánh khác.');
    }

    public function test_every_role_can_see_their_own_requests(): void
    {
        $this->makeRequest(['requester_id' => $this->cashier->id]);

        $response = $this->actingAs($this->cashier)->get(route('my-requests.index'));
        $response->assertOk();

        $this->assertCount(1, $response->viewData('page')['props']['requests']['data']);
    }

    public function test_ledger_and_policies_are_owner_only(): void
    {
        $this->actingAs($this->owner)->get(route('approvals.ledger'))->assertOk();
        $this->actingAs($this->owner)->get(route('approvals.policies.index'))->assertOk();

        $this->actingAs($this->manager)->get(route('approvals.ledger'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('approvals.policies.index'))->assertForbidden();
    }

    public function test_owner_cannot_enable_a_forbidden_operation_for_managers(): void
    {
        // Kể cả khi payload bị sửa thủ công, chặn cứng vẫn phải thắng.
        $this->actingAs($this->owner)
            ->put(route('approvals.policies.update'), [
                'policies' => [[
                    'operation_type' => 'employee_create',
                    'branch_id' => null,
                    'manager_can_approve' => true,
                    'manager_limit_amount' => null,
                    'manager_daily_limit' => null,
                    'manager_monthly_limit' => null,
                    'requires_owner_countersign' => false,
                    'is_active' => true,
                ]],
            ])
            ->assertRedirect();

        $this->assertFalse(
            (bool) ApprovalPolicy::withoutGlobalScopes()
                ->where('restaurant_id', $this->restaurant->id)
                ->where('operation_type', 'employee_create')
                ->value('manager_can_approve'),
        );
    }

    // ── Sổ phê duyệt là bằng chứng, không được sửa ───────────────────────────

    public function test_approval_ledger_is_append_only(): void
    {
        $decision = ApprovalDecision::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'approval_request_id' => $this->makeRequest()->id,
            'decided_by' => $this->manager->id,
            'decided_by_name' => $this->manager->name,
            'decided_by_role' => 'manager',
            'decision' => 'approved',
            'operation_type' => 'order_refund',
            'amount_involved' => 100_000,
            'authority_basis' => 'policy_delegated',
        ]);

        $this->expectException(\RuntimeException::class);
        $decision->update(['amount_involved' => 1]);
    }

    public function test_approval_ledger_cannot_be_deleted(): void
    {
        $decision = ApprovalDecision::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'approval_request_id' => $this->makeRequest()->id,
            'decided_by' => $this->manager->id,
            'decided_by_name' => $this->manager->name,
            'decided_by_role' => 'manager',
            'decision' => 'approved',
            'operation_type' => 'order_refund',
            'authority_basis' => 'policy_delegated',
        ]);

        $this->expectException(\RuntimeException::class);
        $decision->delete();
    }

    public function test_owner_acknowledgement_is_allowed_on_ledger(): void
    {
        $decision = ApprovalDecision::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'approval_request_id' => $this->makeRequest()->id,
            'decided_by' => $this->manager->id,
            'decided_by_name' => $this->manager->name,
            'decided_by_role' => 'manager',
            'decision' => 'approved',
            'operation_type' => 'order_refund',
            'authority_basis' => 'policy_delegated',
        ]);

        $decision->update(['owner_reviewed_at' => now(), 'owner_reviewed_by' => $this->owner->id]);

        $this->assertNotNull($decision->fresh()->owner_reviewed_at);
    }
}
