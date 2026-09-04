<?php

namespace App\Services;

use App\Exceptions\AuthorityDeniedException;
use App\Models\ApprovalDecision;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\IngredientSupplier;
use App\Models\Inventory;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalEscalatedNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\DelegatedApprovalNotification;
use App\Support\ApprovalOperations;
use App\Support\AuthorityDecision;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        private SalaryService $salaryService,
        private InventoryService $inventoryService,
        private OrderRefundService $orderRefundService,
        private OrderItemCancellationService $orderItemCancellationService,
        private CentralWarehouseService $warehouseService,
        private ApprovalAuthorityService $authorityService,
        private CashPostingService $cashPostingService,
    ) {}

    /**
     * Tạo yêu cầu chờ phê duyệt và thông báo đến Owner cùng quản lý chi nhánh.
     */
    public function submitRequest(string $operationType, array $data, User $requester): ApprovalRequest
    {
        $branchId = $data['branch_id'] ?? $requester->assignedBranchId();
        $restaurantId = (int) $requester->restaurant_id;

        // Chính sách được ghim ngay lúc tạo để giao diện biết trước ai sẽ xử lý.
        // Thẩm quyền vẫn được kiểm lại lúc duyệt, vì chính sách có thể đổi.
        $policy = ApprovalPolicy::resolve($restaurantId, $operationType, $branchId ? (int) $branchId : null);
        $managerMayHandle = $policy?->manager_can_approve
            && ! ApprovalOperations::isForbiddenForManager($operationType);

        $request = ApprovalRequest::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'requester_id' => $requester->id,
            'subject_employee_id' => $this->resolveSubjectEmployeeId($operationType, $data, $restaurantId),
            'operation_type' => $operationType,
            'operation_data' => $data,
            'amount_involved' => ApprovalOperations::amountFor($operationType, $data),
            'policy_id' => $policy?->id,
            'required_authority' => $managerMayHandle
                ? ApprovalRequest::AUTHORITY_MANAGER
                : ApprovalRequest::AUTHORITY_OWNER,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);

        $this->notifyReviewers($request, $requester);

        $this->flushApprovalCaches($restaurantId, (int) $requester->id);

        return $request;
    }

    /**
     * Phê duyệt → thực thi thao tác → ghi sổ → thông báo requester và Chủ.
     *
     * @throws AuthorityDeniedException khi người duyệt không đủ thẩm quyền
     */
    public function approve(ApprovalRequest $approval, User $reviewer): void
    {
        // Kiểm tra sơ bộ ngoài giao dịch để bắt được trường hợp cần đẩy lên Chủ:
        // ghi nhận escalation phải nằm ngoài transaction, nếu không nó sẽ bị
        // cuốn theo rollback khi ta ném lỗi.
        $preCheck = $this->authorityService->decide($reviewer, $approval);

        if (! $preCheck->allowed) {
            if ($preCheck->shouldEscalate) {
                $this->escalate($approval, $reviewer, $preCheck);
            }

            throw new AuthorityDeniedException($preCheck->reason ?? 'Bạn không có thẩm quyền phê duyệt yêu cầu này.');
        }

        DB::transaction(function () use ($approval, $reviewer) {
            // Khóa bi quan bản ghi phê duyệt để tránh chạy trùng lặp
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if (! $lockedApproval->isOpen()) {
                throw new \RuntimeException('Yêu cầu này đã được xử lý trước đó.');
            }

            // Kiểm tra lần hai bên trong khóa. Điều kiện nghiệp vụ có thể đã đổi
            // giữa hai lần kiểm — ví dụ bếp vừa bấm bắt đầu chế biến.
            $decision = $this->authorityService->authorize($reviewer, $lockedApproval);

            $this->executeOperation($lockedApproval, $reviewer->id);

            $lockedApproval->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'reviewer_id' => $reviewer->id,
                'decided_by_role' => $this->primaryRole($reviewer),
                'reviewed_at' => now(),
            ]);

            $this->recordDecision($lockedApproval, $reviewer, 'approved', $decision);
        });

        $this->flushApprovalCaches((int) $approval->restaurant_id, (int) $approval->requester_id);

        $approval->refresh();
        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'approved', $reviewer));

        // "Các phê duyệt này sẽ được ghi lại rồi báo về cho chủ quản biết rõ."
        if ($preCheck->basis === AuthorityDecision::BASIS_DELEGATED) {
            $this->notifyOwnersOfDelegatedDecision($approval, $reviewer, 'approved');
        }
    }

    /**
     * Từ chối → ghi sổ → thông báo requester.
     *
     * @throws AuthorityDeniedException khi người duyệt không đủ thẩm quyền
     */
    public function reject(ApprovalRequest $approval, User $reviewer, string $reason): void
    {
        // Giống như phê duyệt, từ chối cũng là một quyết định có hậu quả.
        // Kiểm tra trước transaction để escalation không bị rollback khi
        // reviewer chỉ được phép đẩy yêu cầu lên Owner.
        $preCheck = $this->authorityService->decide($reviewer, $approval);

        if (! $preCheck->allowed) {
            if ($preCheck->shouldEscalate) {
                $this->escalate($approval, $reviewer, $preCheck);
            }

            throw new AuthorityDeniedException($preCheck->reason ?? 'Bạn không có thẩm quyền từ chối yêu cầu này.');
        }

        DB::transaction(function () use ($approval, $reviewer, $reason) {
            // Khóa bi quan bản ghi phê duyệt
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if (! $lockedApproval->isOpen()) {
                throw new \RuntimeException('Yêu cầu này đã được xử lý trước đó.');
            }

            // Từ chối cũng là một quyết định có hệ quả (nhân viên bị ghi vắng
            // ca, đơn hoàn tiền bị bác), nên vẫn phải qua kiểm tra thẩm quyền.
            $decision = $this->authorityService->authorize($reviewer, $lockedApproval);

            $lockedApproval->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'reviewer_id' => $reviewer->id,
                'decided_by_role' => $this->primaryRole($reviewer),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->revertSideEffectsOnReject($lockedApproval);

            $this->recordDecision($lockedApproval, $reviewer, 'rejected', $decision, $reason);
        });

        $this->flushApprovalCaches((int) $approval->restaurant_id, (int) $approval->requester_id);

        $approval->refresh();
        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'rejected', $reviewer));

        if ($approval->decided_by_role !== 'owner') {
            $this->notifyOwnersOfDelegatedDecision($approval, $reviewer, 'rejected');
        }
    }

    /**
     * Đẩy yêu cầu lên Chủ doanh nghiệp khi Quản lý đúng vai nhưng vượt thẩm quyền.
     *
     * Yêu cầu không bị hủy — nó đổi trạng thái để biến mất khỏi hàng chờ của
     * Quản lý và chỉ còn Chủ xử lý được.
     */
    public function escalate(ApprovalRequest $approval, User $actor, AuthorityDecision $decision): void
    {
        DB::transaction(function () use ($approval, $actor, $decision) {
            $locked = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ApprovalRequest::STATUS_PENDING) {
                return;
            }

            $locked->update([
                'status' => ApprovalRequest::STATUS_ESCALATED,
                'escalated_at' => now(),
                'escalation_reason' => $decision->reason,
            ]);

            $this->recordDecision($locked, $actor, 'escalated', $decision, $decision->reason);
        });

        $this->flushApprovalCaches((int) $approval->restaurant_id, (int) $approval->requester_id);

        $approval->refresh();
        $this->notifyOwners(
            (int) $approval->restaurant_id,
            fn (User $owner) => $owner->notify(new ApprovalEscalatedNotification($approval, $actor)),
        );
    }

    /**
     * Chuyển các yêu cầu bị bỏ quên lên Chủ theo SLA đã cấu hình trong chính sách.
     * Được gọi từ scheduler và ngay trước khi mở hàng đợi để không phụ thuộc vào
     * việc người dùng có đang mở màn hình phê duyệt hay không.
     */
    public function autoEscalateOverdue(?int $restaurantId = null): int
    {
        $owners = User::withoutGlobalScopes()
            ->role('owner')
            ->when($restaurantId !== null, fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->get()
            ->groupBy('restaurant_id');

        if ($owners->isEmpty()) {
            return 0;
        }

        $requests = ApprovalRequest::withoutGlobalScopes()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->whereNotNull('policy_id')
            ->when($restaurantId !== null, fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->with('policy')
            ->get();

        $escalated = 0;
        foreach ($requests as $approval) {
            $minutes = $approval->policy?->auto_escalate_after_minutes;
            if (! $minutes || $approval->created_at?->gt(now()->subMinutes($minutes))) {
                continue;
            }

            $actor = $owners->get($approval->restaurant_id)?->first();
            if (! $actor) {
                continue;
            }

            $decision = AuthorityDecision::escalate(
                "Tự động chuyển lên Chủ sau {$minutes} phút chưa có người xử lý.",
                $approval->policy,
            );
            $this->escalate($approval, $actor, $decision);
            $escalated++;
        }

        return $escalated;
    }

    /**
     * Hoàn tác các thay đổi trạng thái đã đặt trước khi chờ duyệt.
     */
    private function revertSideEffectsOnReject(ApprovalRequest $approval): void
    {
        if ($approval->operation_type === 'inventory_stocktake' && ! empty($approval->operation_data['count_session_id'])) {
            InventoryCountSession::where('restaurant_id', $approval->restaurant_id)
                ->whereKey($approval->operation_data['count_session_id'])
                ->whereIn('status', ['in_progress', 'pending_approval'])
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => $approval->rejection_reason,
                    'rejected_by' => $approval->reviewer_id,
                    'rejected_at' => now(),
                ]);
        }

        $assignmentId = $approval->operation_data['assignment_id'] ?? null;

        if (! $assignmentId) {
            return;
        }

        $assignment = ScheduleAssignment::withoutGlobalScopes()->find($assignmentId);

        if (! $assignment) {
            return;
        }

        if ($approval->operation_type === 'shift_checkin'
            && in_array($assignment->status, ['scheduled', 'pending_checkin'], true)) {
            $assignment->update(['status' => 'absent']);
        } elseif ($approval->operation_type === 'shift_checkout'
            && $assignment->status === 'pending_checkout') {
            $assignment->update(['status' => 'checked_in']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sổ phê duyệt & thông báo
    // ─────────────────────────────────────────────────────────────────────────

    private function recordDecision(
        ApprovalRequest $approval,
        User $actor,
        string $decisionType,
        AuthorityDecision $authority,
        ?string $reason = null,
    ): void {
        ApprovalDecision::create([
            'restaurant_id' => $approval->restaurant_id,
            'branch_id' => $approval->branch_id,
            'approval_request_id' => $approval->id,
            'decided_by' => $actor->id,
            'decided_by_name' => $actor->name,
            'decided_by_role' => $this->primaryRole($actor),
            'decision' => $decisionType,
            'operation_type' => $approval->operation_type,
            'amount_involved' => $approval->amount_involved,
            'authority_basis' => $authority->basis,
            'policy_snapshot' => $authority->policy?->snapshot(),
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
        ]);
    }

    private function primaryRole(User $user): string
    {
        return $user->roles()->pluck('name')->first() ?? 'staff';
    }

    /**
     * Gửi yêu cầu tới Chủ (giám sát toàn chuỗi) và Quản lý chi nhánh liên quan.
     */
    private function notifyReviewers(ApprovalRequest $request, User $requester): void
    {
        // Trước đây chỉ lấy owner đầu tiên; nhà hàng có nhiều tài khoản chủ sẽ
        // có người không nhận được thông báo nào.
        $recipientIds = User::where('restaurant_id', $requester->restaurant_id)
            ->role('owner')
            ->pluck('id');

        // Gửi thêm cho quản lý được gán trực tiếp vào chi nhánh và các tài
        // khoản manager có branch_id tương ứng. Dùng cả hai nguồn vì dữ liệu
        // cũ có thể chỉ lưu một trong hai cách.
        if ($request->branch_id) {
            $branch = RestaurantBranch::withoutGlobalScopes()
                ->where('restaurant_id', $requester->restaurant_id)
                ->find($request->branch_id);

            $recipientIds = $recipientIds
                ->merge([$branch?->manager_user_id])
                ->merge(
                    User::role('manager')
                        ->where('restaurant_id', $requester->restaurant_id)
                        ->where('branch_id', $request->branch_id)
                        ->pluck('id'),
                );
        }

        User::whereIn('id', $recipientIds->filter()->unique()->reject(fn ($id) => (int) $id === (int) $requester->id))
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new ApprovalRequestedNotification($request, $requester)));
    }

    private function notifyOwnersOfDelegatedDecision(ApprovalRequest $approval, User $reviewer, string $decision): void
    {
        $this->notifyOwners(
            (int) $approval->restaurant_id,
            fn (User $owner) => $owner->notify(new DelegatedApprovalNotification($approval, $reviewer, $decision)),
            exceptUserId: (int) $reviewer->id,
        );
    }

    private function notifyOwners(int $restaurantId, \Closure $notify, ?int $exceptUserId = null): void
    {
        User::where('restaurant_id', $restaurantId)
            ->role('owner')
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->get()
            ->each($notify);
    }

    /**
     * Badge của Quản lý dùng khóa riêng theo từng người nên không xóa hết được
     * từ đây; TTL 60 giây đã đủ để nó tự khớp lại.
     */
    private function flushApprovalCaches(int $restaurantId, ?int $requesterId = null): void
    {
        Cache::forget("pending_approvals:{$restaurantId}");

        if ($requesterId) {
            Cache::forget("my_open_requests:{$requesterId}");
        }
    }

    /**
     * Nhân viên chịu tác động, dùng để chặn tự duyệt gián tiếp.
     *
     * Chỉ nhận id thực sự thuộc nhà hàng này — dữ liệu thao tác đến từ nhiều
     * luồng khác nhau và khóa ngoại sẽ nổ nếu id rác lọt qua.
     */
    private function resolveSubjectEmployeeId(string $operationType, array $data, int $restaurantId): ?int
    {
        $employeeId = ApprovalOperations::subjectEmployeeIdFor($operationType, $data);

        if (! $employeeId) {
            return null;
        }

        return Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereKey($employeeId)
            ->value('id');
    }

    /**
     * Thực thi thao tác đã được phê duyệt.
     */
    private function executeOperation(ApprovalRequest $approval, int $reviewerId): void
    {
        $data = $approval->operation_data;

        match ($approval->operation_type) {
            // These legacy low-risk requests are approval-ledger operations:
            // the originating workflow has already applied the user-facing
            // change, while approval records the controlled decision.
            'discount_small',
            'leave_request_short',
            'ingredient_transfer_small',
            'menu_item_pause' => $this->validateLowRiskApproval($approval->operation_type, $data),
            'inventory_create' => $this->executeInventoryCreate($data, $approval->restaurant_id),
            'inventory_update' => $this->executeInventoryUpdate($data, $approval->restaurant_id),
            'inventory_delete' => $this->executeInventoryDelete($data, $approval->restaurant_id),
            'inventory_adjustment' => $this->executeInventoryAdjustment($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_purchase' => $this->executePurchase($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_purchase_batch' => $this->executePurchaseBatch($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_waste' => $this->executeWaste($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_stocktake' => $this->executeStocktake($data, $approval->restaurant_id, $approval->requester_id, $reviewerId),
            'inventory_recipe_save' => $this->executeRecipeSave($data, $approval->restaurant_id),
            'inventory_recipe_delete' => $this->executeRecipeDelete($data, $approval->restaurant_id),
            'warehouse_set_central' => $this->warehouseService->setCentralWarehouse($approval->restaurant_id, (int) $data['branch_id']),
            'warehouse_price_update' => $this->executeWarehousePriceUpdate($data, $approval->restaurant_id, $approval->requester_id, $reviewerId),
            'warehouse_supply_approve' => $this->executeSupplyApprove($data, $approval->restaurant_id, $reviewerId),
            'warehouse_supply_dispatch' => $this->executeSupplyDispatch($data, $approval->restaurant_id, $reviewerId),
            'warehouse_supply_reject' => $this->executeSupplyReject($data, $approval->restaurant_id, $reviewerId),
            'salary_adjustment' => $this->executeSalaryAdjustment($data, $approval->restaurant_id),
            'shift_checkin' => $this->executeShiftCheckin($data, $approval->restaurant_id),
            'shift_checkout' => $this->executeShiftCheckout($data, $approval->restaurant_id),
            'employee_bonus' => $this->executeEmployeeBonus($data, $approval->restaurant_id, $approval->requester_id),
            'order_refund' => $this->executeOrderRefund($data, $approval->restaurant_id, $reviewerId),
            'order_item_cancel' => $this->executeOrderItemCancellation($data, $approval->restaurant_id, $reviewerId),
            'cash_manual_transaction' => $this->executeCashManualTransaction($data, $approval->restaurant_id, $approval->requester_id, $approval->id),
            default => throw ValidationException::withMessages([
                'operation_type' => 'Approval operation has no executable handler and was not applied.',
            ]),
        };
    }

    /**
     * Validate legacy low-risk approval payloads without silently treating an
     * unknown operation as approved. The operation is intentionally explicit
     * in the match above so all other unsupported operations still fail closed.
     */
    private function validateLowRiskApproval(string $operationType, array $data): void
    {
        $amount = ApprovalOperations::amountFor($operationType, $data);

        if ($amount !== null && $amount > 100000) {
            throw ValidationException::withMessages([
                'amount_involved' => 'Thao tác rủi ro thấp không được vượt quá 100.000đ.',
            ]);
        }
    }

    private function executeCashManualTransaction(array $data, int $restaurantId, int $requesterId, int $approvalId): void
    {
        $isExpense = ($data['type'] ?? null) === 'out' || ($data['source'] ?? null) === 'expense';

        $this->cashPostingService->record([
            'restaurant_id' => $restaurantId,
            'branch_id' => $data['branch_id'] ?? null,
            'cash_register_id' => $data['cash_register_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'source' => $data['source'],
            'idempotency_key' => 'approved-cash:'.$approvalId,
            'voucher_code' => $data['voucher_code'] ?? null,
            'approval_request_id' => $approvalId,
            'enforce_cash_balance' => true,
            'budget_limit' => (float) ($data['budget_limit'] ?? 0),
            'allow_budget_overrun' => (bool) ($data['allow_budget_overrun'] ?? false),
            'debit_account' => $isExpense ? '6271' : '1111',
            'credit_account' => $isExpense ? '1111' : '5112',
            'notes' => $data['notes'] ?? null,
            'created_by' => $requesterId,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    private function executeInventoryCreate(array $data, int $restaurantId): void
    {
        if (blank($data['storage_location'] ?? null) || empty($data['default_shelf_life_days'])) {
            throw ValidationException::withMessages([
                'inventory_create' => 'Không thể tạo nguyên liệu khi thiếu vị trí kho hoặc HSD tiêu chuẩn.',
            ]);
        }

        $branchId = $data['branch_id'] ?? null;
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? ('ING-'.strtoupper(Str::random(6))),
            'unit_id' => $data['unit_id'],
            'category_name' => $data['category'] ?? $data['category_name'] ?? null,
            'storage_type' => $data['storage_type'] ?? 'dry',
            'default_shelf_life_days' => $data['default_shelf_life_days'] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'expiry_warning_days' => $data['expiry_warning_days'] ?? 3,
            'min_stock_level' => $data['min_stock_level'] ?? 0,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'supplier_id' => $data['supplier_id'] ?? null,
            'safety_stock_quantity' => $data['safety_stock_quantity'] ?? 0,
            'lead_time_days' => $data['lead_time_days'] ?? 0,
            'batch_tracking_required' => $data['batch_tracking_required'] ?? false,
            'storage_temperature_min_c' => $data['storage_temperature_min_c'] ?? null,
            'storage_temperature_max_c' => $data['storage_temperature_max_c'] ?? null,
            'auto_waste_end_of_day' => $data['auto_waste_end_of_day'] ?? false,
            'status' => 'active',
        ]);
        $this->syncIngredientSuppliers($ingredient, $data['supplier_id'] ?? null, $data['backup_supplier_ids'] ?? []);
    }

    private function executeInventoryUpdate(array $data, int $restaurantId): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['ingredient_id']);
        $ingredient->update($data['attributes'] ?? []);
        if (array_key_exists('supplier_id', $data['attributes'] ?? []) || array_key_exists('backup_supplier_ids', $data['attributes'] ?? [])) {
            $this->syncIngredientSuppliers(
                $ingredient,
                $data['attributes']['supplier_id'] ?? $ingredient->supplier_id,
                $data['attributes']['backup_supplier_ids'] ?? [],
            );
        }
    }

    private function syncIngredientSuppliers(Ingredient $ingredient, ?int $primarySupplierId, array $backupSupplierIds): void
    {
        $supplierIds = array_values(array_unique(array_filter(array_merge(
            $primarySupplierId ? [$primarySupplierId] : [],
            array_map('intval', $backupSupplierIds),
        ))));

        IngredientSupplier::where('restaurant_id', $ingredient->restaurant_id)
            ->where('ingredient_id', $ingredient->id)
            ->delete();

        foreach ($supplierIds as $index => $supplierId) {
            IngredientSupplier::create([
                'restaurant_id' => $ingredient->restaurant_id,
                'ingredient_id' => $ingredient->id,
                'supplier_id' => $supplierId,
                'priority' => $index + 1,
                'is_primary' => $supplierId === $primarySupplierId,
                'is_active' => true,
            ]);
        }
    }

    private function executeInventoryDelete(array $data, int $restaurantId): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['ingredient_id']);
        $ingredient->delete();
    }

    private function executeInventoryAdjustment(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase([
            'ingredient_id' => $data['ingredient_id'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? 0,
            'notes' => $data['notes'] ?? 'Điều chỉnh tồn kho theo phê duyệt của Chủ nhà hàng',
            'branch_id' => $data['branch_id'] ?? null,
        ], $restaurantId, $performedBy);
    }

    private function executeOrderItemCancellation(array $data, int $restaurantId, int $reviewerId): void
    {
        $item = OrderItem::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['order_item_id']);
        $reviewer = User::findOrFail($reviewerId);

        $this->orderItemCancellationService->cancel(
            $item,
            $reviewer,
            (string) $data['reason'],
        );
    }

    private function executeOrderRefund(array $data, int $restaurantId, int $reviewerId): void
    {
        $order = Order::where('restaurant_id', $restaurantId)->findOrFail($data['order_id']);
        $reviewer = User::findOrFail($reviewerId);

        $this->orderRefundService->process($order, [
            'refund_type' => $data['refund_type'],
            'refund_amount' => $data['refund_amount'],
            'reason' => $data['reason'],
        ], $reviewer);
    }

    private function executeEmployeeBonus(array $data, int $restaurantId, int $requesterId): void
    {
        $employee = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['employee_id']);

        $awardedAt = \Carbon\Carbon::parse($data['awarded_at'])->toDateString();

        EmployeeBonus::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'awarded_by' => $requesterId,
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'awarded_at' => $awardedAt,
            'status' => 'active',
        ]);

        $salary = Salary::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('pay_period_start', '<=', $awardedAt)
            ->whereDate('pay_period_end', '>=', $awardedAt)
            ->where('status', 'draft')
            ->first();

        if ($salary) {
            $this->salaryService->sweepAdjustments($salary, $employee);
        }
    }

    private function executeShiftCheckin(array $data, int $restaurantId): void
    {
        $assignment = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->find($data['assignment_id']);

        if ($assignment && in_array($assignment->status, ['scheduled', 'pending_checkin'])) {
            $checkInTime = ! empty($data['requested_at']) ? Carbon::parse($data['requested_at']) : now();
            $assignment->update([
                'check_in_at' => $checkInTime,
                'status' => 'checked_in',
            ]);
            $assignment->employee?->flushShiftAccessCache();
            Cache::forget("employee_dashboard:{$assignment->employee_id}:".now()->format('Y-m'));
        }
    }

    private function executeShiftCheckout(array $data, int $restaurantId): void
    {
        $assignment = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->find($data['assignment_id']);

        if ($assignment && in_array($assignment->status, ['checked_in', 'pending_checkout'])) {
            $checkOutTime = ! empty($data['requested_at']) ? Carbon::parse($data['requested_at']) : now();
            $assignment->update([
                'check_out_at' => $checkOutTime,
                'status' => 'completed',
            ]);
            $assignment->employee?->flushShiftAccessCache();
            Cache::forget("employee_dashboard:{$assignment->employee_id}:".now()->format('Y-m'));
        }
    }

    private function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase($data, $restaurantId, $performedBy);
    }

    private function executePurchaseBatch(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchaseBatch($data, $restaurantId, $performedBy);
    }

    private function executeWaste(array $data, int $restaurantId, int $performedBy): void
    {
        $transaction = $this->inventoryService->executeWaste($data, $restaurantId, $performedBy);

        $ingredientQuery = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when(! empty($data['branch_id']), fn ($q) => $q->where(function ($scope) use ($data): void {
                $scope->whereNull('branch_id')->orWhere('branch_id', $data['branch_id']);
            }));
        $ingredient = $ingredientQuery->findOrFail($data['ingredient_id']);
        $wasteQty = (float) $data['quantity'];
        // Khớp khoản khấu trừ (nếu có) với giá vốn thực tế của lô đã trừ.
        $wasteCost = $transaction
            ? (float) $transaction->total_cost
            : $wasteQty * (float) $ingredient->average_cost;

        if (
            $transaction
            && app(WarehouseGovernanceService::class)->getRules($restaurantId)->penalty_deduction_enabled
            && ! empty($data['employee_id'])
            && $wasteCost > 0
        ) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->when(! empty($data['branch_id']), fn ($q) => $q->where('branch_id', $data['branch_id']))
                ->find($data['employee_id']);
            if ($employee) {
                $allowedRatio = $ingredient ? (float) ($ingredient->allowed_waste_ratio ?? 0) : 0;
                $penaltyAmount = $wasteCost * (1 - $allowedRatio / 100);
                $penaltyAmount = max(0.0, $penaltyAmount);

                if ($penaltyAmount > 0) {
                    $salary = $this->salaryService->getOrCreateDraft($restaurantId, $employee, now()->toDateString());
                    $this->salaryService->addAdjustment($salary, [
                        'employee_id' => $employee->id,
                        'type' => 'inventory_loss',
                        'amount' => $penaltyAmount,
                        'reason' => "Hao hụt {$ingredient->name}: {$wasteQty} ".($ingredient->unit?->symbol ?? '').' — '.number_format($wasteCost).'đ'.' (Đã khấu trừ '.$allowedRatio.'% định mức cho phép)',
                        'reference_id' => $transaction->id,
                        'reference_type' => InventoryTransaction::class,
                        'status' => 'applied',
                    ]);
                }
            }
        }
    }

    private function executeRecipeSave(array $data, int $restaurantId): void
    {
        $productId = (int) $data['product_id'];
        $items = collect($data['items'] ?? []);

        ProductRecipe::where('restaurant_id', $restaurantId)
            ->where('product_id', $productId)
            ->whereNotIn('ingredient_id', $items->pluck('ingredient_id'))
            ->delete();

        foreach ($items as $item) {
            ProductRecipe::updateOrCreate([
                'restaurant_id' => $restaurantId,
                'product_id' => $productId,
                'ingredient_id' => $item['ingredient_id'],
            ], [
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => $item['quantity'],
                'waste_rate' => $item['waste_rate'] ?? 0,
            ]);
        }

        ProductRecipe::where('restaurant_id', $restaurantId)->where('product_id', $productId)->exists()
            ? Product::where('restaurant_id', $restaurantId)->whereKey($productId)->update(['is_available' => true])
            : Product::where('restaurant_id', $restaurantId)->whereKey($productId)->update(['is_available' => false]);
    }

    private function executeRecipeDelete(array $data, int $restaurantId): void
    {
        $recipe = ProductRecipe::where('restaurant_id', $restaurantId)->findOrFail($data['recipe_id']);
        $productId = $recipe->product_id;
        $recipe->delete();
        Product::where('restaurant_id', $restaurantId)->whereKey($productId)
            ->update(['is_available' => ProductRecipe::where('restaurant_id', $restaurantId)->where('product_id', $productId)->exists()]);
    }

    private function executeStocktake(array $data, int $restaurantId, int $performedBy, ?int $approvedBy = null): void
    {
        DB::transaction(function () use ($data, $restaurantId, $performedBy, $approvedBy): void {
            foreach ($data['reconcile_items'] as $item) {
                $ingredient = Ingredient::withoutGlobalScopes()->where('restaurant_id', $restaurantId)
                    ->where(function ($query) use ($data): void {
                        $query->whereNull('branch_id')->orWhere('branch_id', $data['branch_id']);
                    })
                    ->lockForUpdate()
                    ->findOrFail($item['ingredient_id']);
                $inventory = Inventory::withoutGlobalScopes()->where([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $data['branch_id'],
                    'ingredient_id' => $ingredient->id,
                ])->lockForUpdate()->first();
                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $data['branch_id'],
                        'ingredient_id' => $ingredient->id,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => 0,
                    ]);
                    $inventory = Inventory::withoutGlobalScopes()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
                }
                $current = (float) $inventory->quantity_on_hand;
                $physical = (float) $item['physical_qty'];
                $delta = $physical - $current;
                if ($delta !== 0.0) {
                    $transaction = InventoryTransaction::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $data['branch_id'],
                        'ingredient_id' => $ingredient->id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $performedBy,
                        'type' => 'stocktake',
                        'direction' => $delta > 0 ? 'in' : 'out',
                        'quantity' => abs($delta),
                        'unit_cost' => (float) $ingredient->average_cost,
                        'total_cost' => abs($delta) * (float) $ingredient->average_cost,
                        'notes' => $data['notes'] ?? 'Kiểm kê kho đã được Chủ nhà hàng duyệt',
                        'occurred_at' => now(),
                    ]);
                    app(InventoryService::class)->reconcileBatchesForStocktake($inventory, $current, $physical, $transaction, $performedBy);
                }
                $inventory->update(['quantity_on_hand' => $physical, 'theoretical_quantity' => $physical, 'last_counted_at' => now(), 'updated_by' => $performedBy]);
            }

            if (! empty($data['count_session_id'])) {
                $session = InventoryCountSession::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $data['branch_id'])
                    ->whereKey($data['count_session_id'])
                    ->with('items.ingredient')
                    ->lockForUpdate()
                    ->firstOrFail();
                $totalVarianceValue = 0.0;

                foreach ($data['reconcile_items'] as $item) {
                    $countItem = $session->items->firstWhere('ingredient_id', $item['ingredient_id']);
                    if (! $countItem) {
                        continue;
                    }

                    $expected = (float) $countItem->expected_quantity;
                    $physical = (float) $item['physical_qty'];
                    $variance = $physical - $expected;
                    $unitCost = (float) ($countItem->ingredient?->average_cost ?? 0);
                    $varianceValue = round($variance * $unitCost, 2);
                    $variancePercent = $expected > 0
                        ? round(($variance / $expected) * 100, 2)
                        : ($variance != 0 ? 100 : 0);

                    $countItem->update([
                        'counted_quantity_1' => $physical,
                        'final_quantity' => $physical,
                        'variance_quantity' => $variance,
                        'variance_percent' => $variancePercent,
                        'variance_value' => $varianceValue,
                        'reconciliation_status' => 'not_required',
                    ]);
                    $totalVarianceValue += abs($varianceValue);
                }

                $session->update([
                    'status' => 'approved',
                    'total_variance_value' => round($totalVarianceValue, 2),
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                    'completed_at' => now(),
                ]);
            }
        });
    }

    private function executeWarehousePriceUpdate(array $data, int $restaurantId, ?int $changedBy = null, ?int $approvedBy = null): void
    {
        foreach ($data['prices'] as $row) {
            $ingredient = Ingredient::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereKey((int) $row['ingredient_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $oldPrice = (float) $ingredient->average_cost;
            $newPrice = round((float) $row['average_cost'], 2);

            if (abs($newPrice - $oldPrice) < 0.005) {
                continue;
            }

            $ingredient->update(['average_cost' => $newPrice]);
            IngredientPriceHistory::create([
                'restaurant_id' => $restaurantId,
                'ingredient_id' => $ingredient->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'change_percent' => $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : ($newPrice > 0 ? 100 : 0),
                'changed_by' => $changedBy,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'change_reason' => $data['reason'] ?? 'Thay đổi giá vốn được phê duyệt.',
                'requires_owner_approval' => true,
                'status' => 'approved',
            ]);
        }
    }

    private function executeSupplyApprove(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $this->warehouseService->approveSupplyRequest($request, User::findOrFail($reviewerId), $data['items'] ?? null, $data['notes'] ?? null);
    }

    private function executeSupplyDispatch(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $reviewer = User::findOrFail($reviewerId);
        $dispatched = $this->warehouseService->dispatchSupplyRequest($request, $reviewer, $data['seal_code'] ?? null);

        if (! empty($data['transporter_id'])) {
            $transporter = User::where('restaurant_id', $restaurantId)->findOrFail((int) $data['transporter_id']);
            $this->warehouseService->assignTransporter($dispatched, $transporter, $reviewer);
        }
    }

    private function executeSupplyReject(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $this->warehouseService->rejectSupplyRequest($request, User::findOrFail($reviewerId), $data['reason']);
    }

    private function executeSalaryAdjustment(array $data, int $restaurantId): void
    {
        $salary = Salary::withoutGlobalScopes()->findOrFail($data['salary_id']);

        if ($data['type'] === 'advance') {
            $employee = $salary->employee;
            if ($employee) {
                $salaryMonth = Carbon::parse($salary->pay_period_start);
                $calculationDate = today()->isSameMonth($salaryMonth) ? today() : $salaryMonth->endOfMonth();
                $earnedWages = $this->salaryService->calculateEarnedWagesForMonth($employee, $calculationDate->toDateString());

                $existingAdvanceAmount = SalaryAdjustment::withoutGlobalScopes()
                    ->where('salary_id', $salary->id)
                    ->where('type', 'advance')
                    ->where('status', 'applied')
                    ->sum('amount');

                $limit = $earnedWages * 0.50;
                if (($existingAdvanceAmount + $data['amount']) > $limit) {
                    throw new \Exception(sprintf('Yêu cầu tạm ứng vượt quá giới hạn 50%% tiền lương tích lũy trong tháng (Hạn mức tạm ứng tối đa: %sđ).', number_format($limit)));
                }
            }
        }

        $this->salaryService->addAdjustment($salary, [
            'employee_id' => $salary->employee_id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'status' => 'applied',
        ]);
    }
}
