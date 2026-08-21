<?php

namespace App\Services;

use App\Exceptions\AuthorityDeniedException;
use App\Models\ApprovalDecision;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\OrderItem;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Support\ApprovalOperations;
use App\Support\AuthorityDecision;
use Illuminate\Support\Facades\Cache;

/**
 * Nơi duy nhất trả lời câu hỏi "người này có được duyệt yêu cầu này không".
 *
 * Mọi controller, service, job và command đều phải đi qua đây. Trước đây logic
 * phân quyền nằm rải ở controller (chặn theo vai trò) và trong ApprovalService
 * (chặn cứng order_refund cho owner), nên mở quyền cho Quản lý đồng nghĩa với
 * việc sửa nhiều chỗ và dễ bỏ sót.
 */
class ApprovalAuthorityService
{
    /**
     * Chạy lần lượt 5 chốt chặn. Chốt nào chặn trước thì trả về ngay kèm lý do.
     */
    public function decide(User $actor, ApprovalRequest $approval): AuthorityDecision
    {
        // 1 ─ Cách ly nhà hàng. Super Admin đứng ngoài mô hình thuê bao.
        if ($actor->isSuperAdmin()) {
            return AuthorityDecision::allow(AuthorityDecision::BASIS_SUPER_ADMIN);
        }

        if ((int) $approval->restaurant_id !== (int) $actor->restaurant_id) {
            return AuthorityDecision::deny('Yêu cầu không thuộc nhà hàng của bạn.');
        }

        if (! $approval->isOpen()) {
            return AuthorityDecision::deny('Yêu cầu này đã được xử lý trước đó.');
        }

        // 2 ─ Chặn tự duyệt, kể cả gián tiếp.
        if ($selfApproval = $this->selfApprovalReason($actor, $approval)) {
            return AuthorityDecision::deny($selfApproval);
        }

        // 3 ─ Chủ doanh nghiệp có thẩm quyền sẵn có với mọi thao tác trong chuỗi.
        if ($actor->isOwner()) {
            return AuthorityDecision::allow(AuthorityDecision::BASIS_OWNER);
        }

        $hasApprovalPermission = $actor->can('approve_requests');
        $isWarehouseApproval = $actor->hasRole('warehouse_manager')
            && str_starts_with((string) $approval->operation_type, 'warehouse_');

        if (! $actor->isBranchManager() && ($hasApprovalPermission || $isWarehouseApproval)) {
            if ($isWarehouseApproval) {
                return AuthorityDecision::allow(AuthorityDecision::BASIS_DELEGATED);
            }
            if ($approval->branch_id !== null && ! $actor->canAccessBranch((int) $approval->branch_id)) {
                return AuthorityDecision::deny('Yeu cau khong thuoc chi nhanh ban duoc phep quan ly.');
            }
            return AuthorityDecision::allow(AuthorityDecision::BASIS_DELEGATED);
        }

        if (! $actor->isBranchManager()) {
            return AuthorityDecision::deny('Bạn không có thẩm quyền phê duyệt.');
        }

        // Yêu cầu đã bị đẩy lên Chủ thì Quản lý không lấy lại được.
        if ($approval->status === ApprovalRequest::STATUS_ESCALATED) {
            return AuthorityDecision::deny('Yêu cầu đã vượt thẩm quyền và được chuyển lên Chủ doanh nghiệp.');
        }

        // 4 ─ Danh sách cấm tuyệt đối, không cấu hình được.
        if (ApprovalOperations::isForbiddenForManager($approval->operation_type)) {
            return AuthorityDecision::deny('Thao tác này chỉ Chủ doanh nghiệp mới được phê duyệt.');
        }

        // 5 ─ Phạm vi chi nhánh.
        if (! $this->managesBranch($actor, $approval->branch_id)) {
            return AuthorityDecision::deny('Yêu cầu không thuộc chi nhánh bạn quản lý.');
        }

        // 6 ─ Chính sách do Chủ cấu hình.
        $policy = ApprovalPolicy::resolve(
            (int) $approval->restaurant_id,
            $approval->operation_type,
            $approval->branch_id ? (int) $approval->branch_id : null,
        );

        $delegation = $this->activeDelegation($actor, $approval->operation_type);
        if (! $policy || (! $policy->manager_can_approve && ! $delegation)) {
            return AuthorityDecision::deny('Chủ doanh nghiệp chưa ủy quyền loại phê duyệt này cho Quản lý.');
        }

        if ($delegation?->max_amount_limit !== null) {
            $amount = $approval->amount_involved !== null
                ? (float) $approval->amount_involved
                : ApprovalOperations::amountFor($approval->operation_type, $approval->operation_data ?? []);

            if ($amount !== null && $amount > (float) $delegation->max_amount_limit) {
                return AuthorityDecision::escalate(
                    sprintf('Vượt hạn mức ủy quyền tạm thời của Quản lý (%sđ).', number_format((float) $delegation->max_amount_limit)),
                    $policy,
                );
            }
        }

        // 7 ─ Hạn mức tiền.
        if ($limitBreach = $this->limitBreachReason($actor, $approval, $policy)) {
            return AuthorityDecision::escalate($limitBreach, $policy);
        }

        // 8 ─ Điều kiện nghiệp vụ tại thời điểm duyệt.
        if ($conditionFailure = $this->conditionFailureReason($approval, $policy)) {
            return AuthorityDecision::escalate($conditionFailure, $policy);
        }

        return AuthorityDecision::allow(AuthorityDecision::BASIS_DELEGATED, $policy);
    }

    /**
     * Phiên bản ném lỗi, dùng ở controller và service.
     *
     * @throws AuthorityDeniedException
     */
    public function authorize(User $actor, ApprovalRequest $approval): AuthorityDecision
    {
        $decision = $this->decide($actor, $approval);

        if (! $decision->allowed) {
            throw new AuthorityDeniedException(
                $decision->reason ?? 'Bạn không có thẩm quyền phê duyệt yêu cầu này.'
            );
        }

        return $decision;
    }

    /**
     * Người này có được THỰC HIỆN THẲNG thao tác, không qua phê duyệt?
     *
     * Nhiều controller đang dùng `can('approve_requests')` để trả lời câu hỏi
     * này. Vấn đề: vai trò manager cũng có quyền đó, nên Quản lý được đối xử
     * như Chủ ở những chỗ lẽ ra phải chặn — duyệt khuyến mãi, áp khấu trừ lương.
     * Dùng hàm này thay thế: nó tôn trọng cả danh sách cấm lẫn chính sách của Chủ.
     */
    public function canActDirectly(User $actor, string $operationType, ?int $branchId = null): bool
    {
        if ($actor->isSuperAdmin() || $actor->isOwner()) {
            return true;
        }

        if (! $actor->isBranchManager()) {
            return false;
        }

        if (ApprovalOperations::isForbiddenForManager($operationType)) {
            return false;
        }

        if ($branchId !== null && ! $this->managesBranch($actor, $branchId)) {
            return false;
        }

        $policy = ApprovalPolicy::resolve((int) $actor->restaurant_id, $operationType, $branchId);

        // Ủy quyền tạm thời chỉ mở quyền xử lý ApprovalRequest, không được biến thành
        // đường tắt để các controller tác nghiệp trực tiếp bỏ qua bước phê duyệt.
        return (bool) $policy?->manager_can_approve;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Chốt chặn tự duyệt
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Trả về lý do chặn nếu người duyệt cũng chính là người hưởng lợi.
     *
     * Chặn theo requester_id thôi thì chưa đủ: một Quản lý vẫn duyệt được biên
     * bản phạt hay yêu cầu tăng ca của chính mình nếu người bấm nút tạo là nhân
     * viên khác. Vì vậy phải xét thêm nhân viên chịu tác động.
     */
    private function selfApprovalReason(User $actor, ApprovalRequest $approval): ?string
    {
        if ((int) $approval->requester_id === (int) $actor->id) {
            return 'Bạn không thể tự phê duyệt yêu cầu của chính mình.';
        }

        $subjectEmployeeId = $approval->subject_employee_id
            ?? ApprovalOperations::subjectEmployeeIdFor($approval->operation_type, $approval->operation_data ?? []);

        if ($subjectEmployeeId && $this->employeeIdOf($actor) === (int) $subjectEmployeeId) {
            return 'Bạn không thể phê duyệt yêu cầu liên quan tới chính mình.';
        }

        return null;
    }

    public function employeeIdOf(User $actor): ?int
    {
        $id = Employee::withoutGlobalScopes()
            ->where('user_id', $actor->id)
            ->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Hồ sơ nhân viên này có phải của chính người đang thao tác?
     *
     * Dùng cho các luồng nằm ngoài ApprovalRequest nhưng vẫn phải tuân quy định
     * "không duyệt lương, thưởng, phạt tiền cho chính mình" — ví dụ duyệt bảng
     * lương hay đánh dấu đã trả lương.
     */
    public function isSelf(User $actor, ?int $employeeId): bool
    {
        return $employeeId !== null && $this->employeeIdOf($actor) === (int) $employeeId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phạm vi chi nhánh
    // ─────────────────────────────────────────────────────────────────────────

    private function managesBranch(User $actor, ?int $branchId): bool
    {
        if ($branchId === null) {
            // Yêu cầu ở phạm vi toàn chuỗi — ngoài tầm của Quản lý chi nhánh.
            return false;
        }

        return in_array((int) $branchId, $this->managedBranchIds($actor), true);
    }

    /**
     * Chi nhánh mà người này phụ trách.
     *
     * Lấy từ hai nguồn vì dữ liệu cũ không đồng nhất: branches.manager_user_id
     * và chi nhánh được gán cho tài khoản. ApprovalService khi gửi thông báo
     * cũng dùng đúng hai nguồn này.
     *
     * @return list<int>
     */
    public function managedBranchIds(User $actor): array
    {
        return Cache::remember(
            "managed_branches:{$actor->id}",
            300,
            function () use ($actor): array {
                $ids = RestaurantBranch::withoutGlobalScopes()
                    ->where('restaurant_id', $actor->restaurant_id)
                    ->where('manager_user_id', $actor->id)
                    ->pluck('id')
                    ->all();

                if ($assigned = $actor->assignedBranchId()) {
                    $ids[] = $assigned;
                }

                return array_values(array_unique(array_map('intval', $ids)));
            }
        );
    }

    public static function flushManagedBranchCache(int $userId): void
    {
        Cache::forget("managed_branches:{$userId}");
    }

    private function activeDelegation(User $actor, string $operationType): ?ApprovalDelegation
    {
        return ApprovalDelegation::withoutGlobalScopes()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('delegatee_id', $actor->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->where(function ($query) use ($operationType): void {
                $query->where('module', 'all')
                    ->orWhere('module', $this->delegationModuleFor($operationType));
            })
            ->orderByRaw('max_amount_limit IS NULL')
            ->orderBy('max_amount_limit')
            ->first();
    }

    private function delegationModuleFor(string $operationType): string
    {
        if (str_starts_with($operationType, 'warehouse_')) {
            return str_starts_with($operationType, 'warehouse_supply_') ? 'supply_request' : 'inventory';
        }

        if (str_starts_with($operationType, 'inventory_')) {
            return 'inventory';
        }

        return match (true) {
            $operationType === 'supply_request' => 'supply_request',
            str_starts_with($operationType, 'salary_') => 'expense',
            in_array($operationType, ['order_refund', 'order_item_cancel'], true) => 'audit',
            default => 'all',
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hạn mức
    // ─────────────────────────────────────────────────────────────────────────

    private function limitBreachReason(User $actor, ApprovalRequest $approval, ApprovalPolicy $policy): ?string
    {
        $amount = $approval->amount_involved !== null
            ? (float) $approval->amount_involved
            : ApprovalOperations::amountFor($approval->operation_type, $approval->operation_data ?? []);

        if ($amount === null) {
            return null;
        }

        $perRequest = $policy->manager_limit_amount;
        if ($perRequest !== null && $amount > (float) $perRequest) {
            return sprintf(
                'Giá trị %sđ vượt hạn mức một lần duyệt của Quản lý (%sđ).',
                number_format($amount),
                number_format((float) $perRequest),
            );
        }

        // Cộng dồn để chặn việc chia nhỏ nhiều yêu cầu nhằm lách hạn mức một lần.
        if ($policy->manager_daily_limit !== null) {
            $used = $this->approvedTotal($actor, $approval->operation_type, now()->startOfDay());
            if ($used + $amount > (float) $policy->manager_daily_limit) {
                return sprintf(
                    'Vượt hạn mức duyệt trong ngày (đã duyệt %sđ / %sđ).',
                    number_format($used),
                    number_format((float) $policy->manager_daily_limit),
                );
            }
        }

        if ($policy->manager_monthly_limit !== null) {
            $used = $this->approvedTotal($actor, $approval->operation_type, now()->startOfMonth());
            if ($used + $amount > (float) $policy->manager_monthly_limit) {
                return sprintf(
                    'Vượt hạn mức duyệt trong tháng (đã duyệt %sđ / %sđ).',
                    number_format($used),
                    number_format((float) $policy->manager_monthly_limit),
                );
            }
        }

        return null;
    }

    private function approvedTotal(User $actor, string $operationType, \DateTimeInterface $since): float
    {
        return (float) ApprovalDecision::withoutGlobalScopes()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('decided_by', $actor->id)
            ->where('operation_type', $operationType)
            ->where('decision', 'approved')
            ->where('created_at', '>=', $since)
            ->sum('amount_involved');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Điều kiện nghiệp vụ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Điều kiện được kiểm lại ngay lúc duyệt, không chỉ lúc tạo yêu cầu — trạng
     * thái có thể đã đổi trong lúc chờ.
     */
    private function conditionFailureReason(ApprovalRequest $approval, ApprovalPolicy $policy): ?string
    {
        foreach ($policy->conditions ?? [] as $condition => $expected) {
            if ($expected === false) {
                continue;
            }

            $failure = match ($condition) {
                'kitchen_not_started' => $this->kitchenNotStartedFailure($approval),
                default => null,
            };

            if ($failure) {
                return $failure;
            }
        }

        return null;
    }

    /**
     * Yêu cầu của Chủ: Quản lý chỉ được duyệt hủy món khi bếp CHƯA bấm bắt đầu
     * chế biến. Đã bắt đầu thì nguyên liệu đã tiêu hao, phải Chủ quyết.
     */
    private function kitchenNotStartedFailure(ApprovalRequest $approval): ?string
    {
        $itemId = $approval->operation_data['order_item_id'] ?? null;

        if (! $itemId) {
            return null;
        }

        $item = OrderItem::withoutGlobalScopes()
            ->where('restaurant_id', $approval->restaurant_id)
            ->find($itemId);

        if (! $item) {
            return null;
        }

        $started = $item->started_preparing_at !== null
            || $item->prepared_at !== null
            || $item->status === 'preparing';

        return $started
            ? 'Bếp đã bắt đầu chế biến món này, yêu cầu phải do Chủ doanh nghiệp quyết định.'
            : null;
    }
}
