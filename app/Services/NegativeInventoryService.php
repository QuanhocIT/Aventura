<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryNegativeCase;
use App\Models\InventoryNegativeCaseEvent;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NegativeInventoryService
{
    private const ACTIVE_STATUSES = ['open', 'in_progress', 'pending_owner_approval', 'pending_verification'];

    public const ROOT_CAUSES = [
        'sales_before_receipt' => 'Bán/xuất dùng trước khi nhập hoặc cấp phát',
        'recipe_or_unit_error' => 'Sai công thức, định lượng hoặc quy đổi đơn vị',
        'transfer_shortage' => 'Điều chuyển/cấp phát thiếu hoặc chưa ghi nhận đủ',
        'receiving_shortage' => 'Nhập hàng thiếu, sai số lượng hoặc sai lô',
        'waste_not_recorded' => 'Hao hụt/hủy chưa ghi nhận đúng thời điểm',
        'count_variance' => 'Sai lệch kiểm kê hoặc tồn đầu kỳ',
        'system_or_duplicate' => 'Lỗi hệ thống, giao dịch trùng hoặc sai liên kết',
        'other' => 'Nguyên nhân khác cần mô tả rõ',
        'unknown' => 'Chưa xác định, cần điều tra thêm',
    ];

    /**
     * Create/update one active case for an inventory balance below zero.
     * A receipt or adjustment that brings the balance back to zero only makes
     * the case eligible for human closure; the handling plan remains required.
     */
    public function sync(Inventory $inventory, ?InventoryTransaction $sourceTransaction = null): ?InventoryNegativeCase
    {
        $inventory->loadMissing(['ingredient.unit', 'branch']);
        $activeQuery = InventoryNegativeCase::withoutGlobalScopes()
            ->where('restaurant_id', $inventory->restaurant_id)
            ->where('inventory_id', $inventory->id)
            ->whereIn('status', self::ACTIVE_STATUSES);

        $activeCases = $activeQuery->lockForUpdate()->get();
        $onHand = (float) $inventory->quantity_on_hand;

        if ($onHand < -0.0005) {
            $negativeQuantity = round(abs($onHand), 3);
            $estimatedValue = round(
                $negativeQuantity * (float) ($inventory->ingredient?->average_cost ?? $inventory->last_cost ?? 0),
                2,
            );
            $severity = $this->classifySeverity($negativeQuantity, $estimatedValue);
            $sourceType = $this->sourceType($sourceTransaction);
            $requiresOwnerApproval = $this->requiresOwnerApproval($severity);
            $plan = $this->defaultPlan($inventory, $negativeQuantity);
            $case = $activeCases->first();

            if ($case) {
                $wasPendingVerification = $case->status === 'pending_verification';
                $updates = [
                    'negative_quantity' => $negativeQuantity,
                    'estimated_value' => $estimatedValue,
                    'severity' => $severity,
                    'verification_status' => 'not_ready',
                    'last_activity_at' => now(),
                    'source_type' => $sourceType !== 'unknown' ? $sourceType : ($case->source_type ?? $sourceType),
                    'owner_approval_required' => $requiresOwnerApproval,
                    'source_transaction_id' => $sourceTransaction?->id ?: $case->source_transaction_id,
                    'auto_plan' => $case->auto_plan ?: $plan,
                ];

                if ($wasPendingVerification) {
                    $updates['status'] = 'in_progress';
                    $updates['reopen_count'] = (int) $case->reopen_count + 1;
                    $updates['reopened_at'] = now();
                    $updates['reopened_reason'] = 'Tồn lại âm trong thời gian chờ đối chiếu.';
                }

                // A case that becomes high-risk again must return to owner
                // approval unless it has already been approved for this case.
                if ($requiresOwnerApproval && $case->owner_approval_status !== 'approved' && $case->status === 'in_progress') {
                    $updates['status'] = 'pending_owner_approval';
                    $updates['owner_approval_status'] = 'pending';
                }

                $case->update($updates);

                if ($wasPendingVerification) {
                    $this->recordEvent($case, 'reopened', 'pending_verification', 'in_progress', 'Tồn kho lại xuống âm trước khi hồ sơ được xác minh.', [
                        'negative_quantity' => $negativeQuantity,
                        'source_transaction_id' => $sourceTransaction?->id,
                    ]);
                } elseif ($sourceTransaction && (int) $case->source_transaction_id === (int) $sourceTransaction->id) {
                    $this->recordEvent($case, 'negative_movement', $case->status, $case->status, 'Ghi nhận thêm giao dịch làm số âm thay đổi.', [
                        'transaction_id' => $sourceTransaction->id,
                        'negative_quantity' => $negativeQuantity,
                    ]);
                }

                return $case->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'approver']);
            }

            $case = InventoryNegativeCase::create([
                'restaurant_id' => $inventory->restaurant_id,
                'branch_id' => $inventory->branch_id,
                'ingredient_id' => $inventory->ingredient_id,
                'inventory_id' => $inventory->id,
                'source_transaction_id' => $sourceTransaction?->id,
                'source_type' => $sourceType,
                'status' => 'open',
                'severity' => $severity,
                'negative_quantity' => $negativeQuantity,
                'estimated_value' => $estimatedValue,
                'detected_quantity' => $negativeQuantity,
                'detected_value' => $estimatedValue,
                'detected_at' => now(),
                'due_at' => now()->addHours($this->slaHours($severity)),
                'last_activity_at' => now(),
                'sla_hours' => $this->slaHours($severity),
                'auto_plan' => $plan,
                'owner_approval_required' => $requiresOwnerApproval,
                'owner_approval_status' => $requiresOwnerApproval ? 'pending' : null,
                'verification_status' => 'not_ready',
            ]);
            $case->forceFill(['case_code' => $this->caseCode($case)])->saveQuietly();
            $this->recordEvent($case, 'detected', null, 'open', 'Hệ thống tự động phát hiện tồn nguyên liệu xuống dưới 0.', [
                'negative_quantity' => $negativeQuantity,
                'estimated_value' => $estimatedValue,
                'source_transaction_id' => $sourceTransaction?->id,
                'source_type' => $sourceType,
            ]);

            return $case->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'approver']);
        }

        if ($activeCases->isNotEmpty()) {
            foreach ($activeCases as $case) {
                $wasReady = $case->verification_status === 'ready';
                $case->update([
                    'negative_quantity' => 0,
                    'verification_status' => 'ready',
                    'last_activity_at' => now(),
                ]);
                if (! $wasReady) {
                    $this->recordEvent($case, 'stock_replenished', $case->status, $case->status, 'Tồn đã về 0 hoặc dương; hồ sơ chuyển sang bước chờ đối chiếu.', [
                        'on_hand' => $onHand,
                    ]);
                }
            }
        }

        return null;
    }

    public function activeFor(int $restaurantId, ?int $branchId = null): Collection
    {
        return $this->casesFor($restaurantId, $branchId, 'active');
    }

    /**
     * Data contract for the single negative-stock control center. The same
     * contract is used by Owner, Central Warehouse Manager and Branch Manager,
     * with the controller applying the role scope before calling it.
     */
    public function controlData(
        int $restaurantId,
        ?int $branchId = null,
        string $statusFilter = 'active',
        ?string $severity = null,
    ): array {
        $this->ensureCasesForNegativeBalances($restaurantId, $branchId);

        $activeQuery = $this->queryFor($restaurantId, $branchId)
            ->whereIn('status', self::ACTIVE_STATUSES);
        $activeCases = (clone $activeQuery)->get();
        $resolvedQuery = $this->queryFor($restaurantId, $branchId)
            ->where('status', 'resolved');

        $casesQuery = $statusFilter === 'resolved'
            ? $resolvedQuery
            : ($statusFilter === 'all'
                ? $this->queryFor($restaurantId, $branchId)
                : $activeQuery);

        $cases = $casesQuery
            ->when($severity && in_array($severity, ['low', 'medium', 'high', 'critical'], true), fn (Builder $query) => $query->where('severity', $severity))
            ->with($this->presentRelations())
            ->latest('detected_at')
            ->limit(200)
            ->get()
            ->map(fn (InventoryNegativeCase $case): array => $this->present($case))
            ->values();

        return [
            'cases' => $cases,
            'summary' => [
                'active_cases' => $activeCases->count(),
                'open_cases' => $activeCases->where('status', 'open')->count(),
                'in_progress_cases' => $activeCases->where('status', 'in_progress')->count(),
                'negative_cases' => $activeCases->where('negative_quantity', '>', 0)->count(),
                'negative_quantity' => round((float) $activeCases->sum('negative_quantity'), 3),
                'estimated_value' => round((float) $activeCases->sum('estimated_value'), 2),
                'critical_cases' => $activeCases->where('severity', 'critical')->count(),
                'high_cases' => $activeCases->where('severity', 'high')->count(),
                'pending_owner_approval' => $activeCases->where('status', 'pending_owner_approval')->count(),
                'pending_verification' => $activeCases->where('status', 'pending_verification')->count(),
                'overdue_cases' => $activeCases->filter(fn (InventoryNegativeCase $case): bool => $case->due_at?->isPast() === true)->count(),
                'due_today' => $activeCases->filter(fn (InventoryNegativeCase $case): bool => $case->due_at?->isToday() === true)->count(),
                'resolved_last_30_days' => (clone $resolvedQuery)->where('resolved_at', '>=', now()->subDays(30))->count(),
                'resolved_value_last_30_days' => round((float) (clone $resolvedQuery)->where('resolved_at', '>=', now()->subDays(30))->sum('estimated_value'), 2),
            ],
        ];
    }

    /**
     * Backfill cases for balances that were already negative before this
     * workflow was deployed. This is idempotent because sync keeps one active
     * case per inventory record.
     */
    public function ensureCasesForNegativeBalances(int $restaurantId, ?int $branchId = null): void
    {
        Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('quantity_on_hand', '<', 0)
            ->chunkById(100, function (Collection $inventories): void {
                foreach ($inventories as $inventory) {
                    $this->sync($inventory);
                }
            });
    }

    public function casesFor(int $restaurantId, ?int $branchId = null, string $statusFilter = 'active'): Collection
    {
        $this->ensureCasesForNegativeBalances($restaurantId, $branchId);

        $query = $statusFilter === 'resolved'
            ? $this->queryFor($restaurantId, $branchId)->where('status', 'resolved')
            : ($statusFilter === 'all'
                ? $this->queryFor($restaurantId, $branchId)
                : $this->queryFor($restaurantId, $branchId)->whereIn('status', self::ACTIVE_STATUSES));

        return $query
            ->with($this->presentRelations())
            ->latest('detected_at')
            ->get()
            ->map(fn (InventoryNegativeCase $case): array => $this->present($case));
    }

    /**
     * The UI keeps the list compact and loads this richer view on demand.
     * It deliberately includes both the originating movement and the
     * correction movements so an operator can close the loop from one screen.
     */
    public function detail(InventoryNegativeCase $case, User $actor): array
    {
        $this->assertActorScope($case, $actor);

        $case->load([
            ...$this->presentRelations(),
            'sourceTransaction.performedBy:id,name',
            'events.actor:id,name',
        ]);

        $transactions = InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $case->restaurant_id)
            ->where(function (Builder $query) use ($case): void {
                $query->whereKey($case->source_transaction_id)
                    ->orWhere(function (Builder $movement) use ($case): void {
                        $movement->where('inventory_id', $case->inventory_id)
                            ->where('occurred_at', '>=', $case->detected_at ?? $case->created_at);
                    });
            })
            ->with('performedBy:id,name')
            ->latest('occurred_at')
            ->limit(30)
            ->get()
            ->map(fn (InventoryTransaction $transaction): array => $this->presentTransaction($transaction))
            ->values()
            ->all();

        $presented = $this->present($case);
        $presented['timeline'] = $case->events
            ->sortByDesc('created_at')
            ->values()
            ->map(fn (InventoryNegativeCaseEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_label' => $this->eventLabel($event->event_type),
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'note' => $event->note,
                'payload' => $event->payload,
                'actor_name' => $event->actor?->name ?? 'Hệ thống',
                'created_at' => $event->created_at?->format('d/m/Y H:i'),
            ])->all();
        $presented['transactions'] = $transactions;

        return $presented;
    }

    public function rootCauseOptions(): array
    {
        return collect(self::ROOT_CAUSES)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public function updatePlan(
        InventoryNegativeCase $case,
        User $actor,
        string $handlingPlan,
        ?int $responsibleUserId = null,
        ?string $expectedRestockAt = null,
        ?string $rootCause = null,
        ?string $rootCauseCode = null,
        ?string $containmentAction = null,
        ?string $correctiveAction = null,
    ): InventoryNegativeCase {
        $this->assertActorScope($case, $actor);
        $this->assertCanManage($actor);
        if (! in_array($case->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages(['case' => 'Hồ sơ tồn âm đã được xử lý.']);
        }

        $responsibleUser = $responsibleUserId
            ? User::where('restaurant_id', $actor->restaurant_id)->find($responsibleUserId)
            : $actor;
        if (! $responsibleUser) {
            throw ValidationException::withMessages(['responsible_user_id' => 'Người phụ trách không thuộc nhà hàng này.']);
        }
        $this->assertResponsibleUserScope($case, $actor, $responsibleUser);

        $requiresOwnerApproval = (bool) $case->owner_approval_required || $this->requiresOwnerApproval((string) $case->severity);
        $isOwner = $actor->isOwner() || $actor->isSuperAdmin();
        $nextStatus = $requiresOwnerApproval && ! $isOwner ? 'pending_owner_approval' : 'in_progress';
        $now = now();

        $case->update([
            'status' => $nextStatus,
            'handling_plan' => trim($handlingPlan),
            'root_cause' => filled($rootCause) ? trim($rootCause) : $case->root_cause,
            'root_cause_code' => filled($rootCauseCode) ? $rootCauseCode : ($case->root_cause_code ?: 'unknown'),
            'containment_action' => filled($containmentAction) ? trim($containmentAction) : $case->containment_action,
            'corrective_action' => filled($correctiveAction) ? trim($correctiveAction) : $case->corrective_action,
            'responsible_user_id' => $responsibleUser->id,
            'expected_restock_at' => $expectedRestockAt,
            'acknowledged_at' => $case->acknowledged_at ?: $now,
            'last_activity_at' => $now,
            'owner_approval_required' => $requiresOwnerApproval,
            'owner_approval_status' => $requiresOwnerApproval ? ($isOwner ? 'approved' : 'pending') : null,
            'approved_by' => $isOwner && $requiresOwnerApproval ? $actor->id : ($case->approved_by ?? null),
            'approved_at' => $isOwner && $requiresOwnerApproval ? now() : $case->approved_at,
            'approval_note' => $isOwner && $requiresOwnerApproval ? 'Chủ doanh nghiệp lập và phê duyệt phương án.' : $case->approval_note,
        ]);

        $this->audit('negative_inventory_plan_updated', $case, [
            'status' => $nextStatus,
            'severity' => $case->severity,
            'owner_approval_required' => $requiresOwnerApproval,
            'responsible_user_id' => $responsibleUser->id,
        ]);
        $this->recordEvent($case, 'plan_submitted', $case->getOriginal('status'), $nextStatus, 'Đã phân tích nguyên nhân và lập phương án xử lý.', [
            'root_cause_code' => $case->root_cause_code,
            'responsible_user_id' => $responsibleUser->id,
            'expected_restock_at' => $expectedRestockAt,
        ]);

        return $case->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'approver']);
    }

    public function decideApproval(
        InventoryNegativeCase $case,
        User $actor,
        string $decision,
        string $note,
    ): InventoryNegativeCase {
        if (! $actor->isOwner() && ! $actor->isSuperAdmin()) {
            abort(403, 'Chỉ Chủ doanh nghiệp được phê duyệt hồ sơ âm nguyên liệu.');
        }
        if ((int) $case->restaurant_id !== (int) $actor->restaurant_id) {
            abort(403);
        }
        if ($case->status !== 'pending_owner_approval') {
            throw ValidationException::withMessages(['case' => 'Hồ sơ này chưa ở trạng thái chờ Chủ doanh nghiệp phê duyệt.']);
        }

        $approved = $decision === 'approve';
        $case->update([
            'status' => $approved ? 'in_progress' : 'open',
            'owner_approval_status' => $approved ? 'approved' : 'rejected',
            'approval_note' => trim($note),
            'approved_by' => $approved ? $actor->id : null,
            'approved_at' => $approved ? now() : null,
            'last_activity_at' => now(),
        ]);

        $this->audit('negative_inventory_approval_'.$decision, $case, [
            'status' => $case->status,
            'note' => trim($note),
        ]);
        $this->recordEvent($case, $approved ? 'owner_approved' : 'owner_rejected', $case->getOriginal('status'), $case->status, trim($note));

        return $case->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'approver']);
    }

    /**
     * Step 5: the operator submits a corrected balance for an independent
     * verification. A correction must exist in the immutable inventory
     * ledger; editing the inventory row by itself is never sufficient.
     */
    public function submitVerification(
        InventoryNegativeCase $case,
        User $actor,
        string $note,
    ): InventoryNegativeCase {
        $this->assertActorScope($case, $actor);
        $this->assertCanManage($actor);

        if ($case->status !== 'in_progress') {
            throw ValidationException::withMessages(['case' => 'Chỉ hồ sơ đang xử lý mới được gửi chờ đối chiếu.']);
        }
        if (blank(trim((string) $case->handling_plan))) {
            throw ValidationException::withMessages(['handling_plan' => 'Hồ sơ phải có phương án xử lý trước khi gửi đối chiếu.']);
        }
        if (blank(trim((string) $case->root_cause_code))) {
            throw ValidationException::withMessages(['root_cause_code' => 'Phải phân loại nguyên nhân trước khi gửi đối chiếu.']);
        }
        if ($case->root_cause_code === 'unknown' && blank(trim((string) $case->root_cause))) {
            throw ValidationException::withMessages(['root_cause' => 'Nếu chưa xác định được nguyên nhân, phải ghi rõ nội dung điều tra còn thiếu.']);
        }

        return DB::transaction(function () use ($case, $actor, $note): InventoryNegativeCase {
            $lockedCase = InventoryNegativeCase::withoutGlobalScopes()
                ->where('restaurant_id', $actor->restaurant_id)
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();
            $inventory = Inventory::withoutGlobalScopes()
                ->whereKey($lockedCase->inventory_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || (float) $inventory->quantity_on_hand < -0.0005) {
                throw ValidationException::withMessages(['case' => 'Chưa thể gửi đối chiếu: tồn kho vẫn còn âm.']);
            }

            $correction = $this->latestCorrectionTransaction($lockedCase);
            if (! $correction) {
                throw ValidationException::withMessages([
                    'case' => 'Chưa tìm thấy giao dịch bù/điều chỉnh sau thời điểm phát hiện. Hãy thực hiện qua Nhập hàng, Điều chuyển hoặc Kiểm kê trước khi gửi đối chiếu.',
                ]);
            }

            $fromStatus = $lockedCase->status;
            $lockedCase->update([
                'status' => 'pending_verification',
                'verification_status' => 'pending',
                'verification_requested_at' => now(),
                'verification_requested_by' => $actor->id,
                'verification_note' => trim($note),
                'verification_transaction_id' => $correction->id,
                'last_activity_at' => now(),
                'negative_quantity' => 0,
            ]);
            $this->recordEvent($lockedCase, 'verification_requested', $fromStatus, 'pending_verification', trim($note), [
                'transaction_id' => $correction->id,
                'on_hand' => (float) $inventory->quantity_on_hand,
            ]);

            return $lockedCase->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'approver', 'verifier']);
        });
    }

    /**
     * Step 6: close only after a second person verifies the ledger and the
     * current stock. Owners may override segregation of duties, but the
     * override is explicit in the timeline.
     */
    public function verifyAndResolve(
        InventoryNegativeCase $case,
        User $actor,
        string $resolutionType,
        string $note,
    ): InventoryNegativeCase {
        $this->assertActorScope($case, $actor);
        $this->assertCanManage($actor);

        if ($case->status !== 'pending_verification') {
            throw ValidationException::withMessages(['case' => 'Hồ sơ chưa ở bước chờ đối chiếu độc lập.']);
        }
        if (! $actor->isOwner() && ! $actor->isSuperAdmin() && (int) $case->responsible_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['case' => 'Người lập phương án không được tự xác minh hồ sơ của chính mình.']);
        }

        return DB::transaction(function () use ($case, $actor, $resolutionType, $note): InventoryNegativeCase {
            $lockedCase = InventoryNegativeCase::withoutGlobalScopes()
                ->where('restaurant_id', $actor->restaurant_id)
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();
            $inventory = Inventory::withoutGlobalScopes()->whereKey($lockedCase->inventory_id)->lockForUpdate()->first();
            if (! $inventory || (float) $inventory->quantity_on_hand < -0.0005) {
                throw ValidationException::withMessages(['case' => 'Không thể xác minh: tồn kho đã âm trở lại.']);
            }

            $fromStatus = $lockedCase->status;
            $lockedCase->update([
                'status' => 'resolved',
                'negative_quantity' => 0,
                'resolution_type' => $resolutionType,
                'resolution_note' => trim($note),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_quantity' => (float) $inventory->quantity_on_hand,
                'verified_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->recordEvent($lockedCase, 'verified_and_resolved', $fromStatus, 'resolved', trim($note), [
                'on_hand' => (float) $inventory->quantity_on_hand,
                'transaction_id' => $lockedCase->verification_transaction_id,
                'segregation_override' => $actor->isOwner() || $actor->isSuperAdmin(),
            ]);
            $this->audit('negative_inventory_case_verified_and_resolved', $lockedCase, [
                'resolution_type' => $resolutionType,
                'verification_transaction_id' => $lockedCase->verification_transaction_id,
            ]);

            return $lockedCase->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'resolver', 'approver', 'verifier']);
        });
    }

    public function resolve(InventoryNegativeCase $case, User $actor, string $resolutionType, string $resolutionNote): InventoryNegativeCase
    {
        if ($case->status === 'pending_verification') {
            return $this->verifyAndResolve($case, $actor, $resolutionType, $resolutionNote);
        }
        $this->assertActorScope($case, $actor);
        $this->assertCanManage($actor);
        if ($case->status !== 'in_progress') {
            throw ValidationException::withMessages(['case' => 'Hãy lập và lưu phương án xử lý trước khi chốt hồ sơ.']);
        }
        if (blank(trim((string) $case->handling_plan))) {
            throw ValidationException::withMessages(['handling_plan' => 'Hồ sơ phải có phương án xử lý trước khi chốt.']);
        }
        if ($this->requiresOwnerApproval((string) $case->severity) && ! ($actor->isOwner() || $actor->isSuperAdmin())) {
            throw ValidationException::withMessages(['case' => 'Hồ sơ mức cao/critical phải do Chủ doanh nghiệp chốt.']);
        }

        return DB::transaction(function () use ($case, $actor, $resolutionType, $resolutionNote): InventoryNegativeCase {
            $lockedCase = InventoryNegativeCase::withoutGlobalScopes()
                ->where('restaurant_id', $actor->restaurant_id)
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();
            $inventory = Inventory::withoutGlobalScopes()->whereKey($lockedCase->inventory_id)->lockForUpdate()->first();

            if (! $inventory || (float) $inventory->quantity_on_hand < -0.0005) {
                throw ValidationException::withMessages([
                    'case' => 'Chưa thể đóng hồ sơ: tồn kho vẫn còn âm. Hãy nhập bù hoặc kiểm kê/điều chỉnh trước.',
                ]);
            }

            $fromStatus = $lockedCase->status;
            $lockedCase->update([
                'status' => 'resolved',
                'negative_quantity' => 0,
                'resolution_type' => $resolutionType,
                'resolution_note' => trim($resolutionNote),
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'verification_status' => 'verified',
                'verified_by' => $actor->id,
                'verified_quantity' => (float) $inventory->quantity_on_hand,
                'verified_at' => now(),
                'last_activity_at' => now(),
            ]);

            $this->audit('negative_inventory_case_resolved', $lockedCase, [
                'resolution_type' => $resolutionType,
                'resolution_note' => trim($resolutionNote),
            ]);
            $this->recordEvent($lockedCase, 'legacy_direct_resolution', $fromStatus, 'resolved', trim($resolutionNote), [
                'on_hand' => (float) $inventory->quantity_on_hand,
                'workflow_override' => true,
            ]);

            return $lockedCase->fresh(['branch', 'ingredient.unit', 'responsibleUser', 'resolver', 'approver']);
        });
    }

    public function present(InventoryNegativeCase $case): array
    {
        $inventory = $case->inventory;

        return [
            'id' => $case->id,
            'case_code' => $case->case_code ?: 'NEG-'.$case->id,
            'branch_id' => $case->branch_id,
            'branch_name' => $case->branch?->name,
            'ingredient_id' => $case->ingredient_id,
            'ingredient_name' => $case->ingredient?->name,
            'unit_symbol' => $case->ingredient?->unit?->symbol,
            'status' => $case->status,
            'severity' => $case->severity ?: 'medium',
            'severity_label' => $this->severityLabel((string) ($case->severity ?: 'medium')),
            'source_type' => $case->source_type ?: 'unknown',
            'source_label' => $this->sourceLabel((string) ($case->source_type ?: 'unknown')),
            'negative_quantity' => (float) $case->negative_quantity,
            'on_hand' => (float) ($inventory?->quantity_on_hand ?? -$case->negative_quantity),
            'estimated_value' => (float) $case->estimated_value,
            'detected_quantity' => (float) ($case->detected_quantity ?? $case->negative_quantity),
            'detected_value' => (float) ($case->detected_value ?? $case->estimated_value),
            'detected_at' => $case->detected_at?->format('d/m/Y H:i'),
            'due_at' => $case->due_at?->format('d/m/Y H:i'),
            'sla_hours' => (int) ($case->sla_hours ?? 48),
            'age_hours' => $case->detected_at ? max(0, (int) $case->detected_at->diffInHours(now())) : null,
            'is_overdue' => in_array($case->status, self::ACTIVE_STATUSES, true) && $case->due_at?->isPast() === true,
            'workflow_step' => $this->workflowStep((string) $case->status),
            'auto_plan' => $case->auto_plan,
            'handling_plan' => $case->handling_plan,
            'root_cause' => $case->root_cause,
            'root_cause_code' => $case->root_cause_code,
            'root_cause_label' => self::ROOT_CAUSES[$case->root_cause_code] ?? null,
            'containment_action' => $case->containment_action,
            'corrective_action' => $case->corrective_action,
            'responsible_user_id' => $case->responsible_user_id,
            'responsible_user_name' => $case->responsibleUser?->name,
            'expected_restock_at' => $case->expected_restock_at?->toDateString(),
            'owner_approval_required' => (bool) $case->owner_approval_required,
            'owner_approval_status' => $case->owner_approval_status,
            'approval_note' => $case->approval_note,
            'approved_by' => $case->approved_by,
            'approved_by_name' => $case->approver?->name,
            'approved_at' => $case->approved_at?->format('d/m/Y H:i'),
            'verification_status' => $case->verification_status ?: 'not_ready',
            'verification_requested_at' => $case->verification_requested_at?->format('d/m/Y H:i'),
            'verification_requested_by_name' => $case->verificationRequester?->name,
            'verification_note' => $case->verification_note,
            'verification_transaction_id' => $case->verification_transaction_id,
            'verified_quantity' => $case->verified_quantity !== null ? (float) $case->verified_quantity : null,
            'verified_at' => $case->verified_at?->format('d/m/Y H:i'),
            'verified_by_name' => $case->verifier?->name,
            'reopen_count' => (int) ($case->reopen_count ?? 0),
            'resolution_type' => $case->resolution_type,
            'resolution_note' => $case->resolution_note,
            'resolved_at' => $case->resolved_at?->format('d/m/Y H:i'),
            'resolved_by_name' => $case->resolver?->name,
            'source_transaction' => $case->relationLoaded('sourceTransaction') && $case->sourceTransaction
                ? $this->presentTransaction($case->sourceTransaction)
                : null,
        ];
    }

    public function requiresOwnerApproval(string $severity): bool
    {
        return in_array($severity, ['high', 'critical'], true);
    }

    public function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critical — phải xử lý ngay',
            'high' => 'Cao — cần Chủ doanh nghiệp duyệt',
            'low' => 'Thấp',
            default => 'Trung bình',
        };
    }

    public function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'sale_usage' => 'Bán hàng / xuất dùng',
            'central_dispatch' => 'Cấp phát từ Kho Tổng',
            'production' => 'Sơ chế / sản xuất',
            'waste' => 'Hao hụt / hủy',
            'stocktake' => 'Kiểm kê',
            'adjustment' => 'Điều chỉnh tồn',
            'receiving_discrepancy' => 'Nhập hàng / chênh lệch nhận',
            default => 'Chưa phân loại — cần đối chiếu',
        };
    }

    private function queryFor(int $restaurantId, ?int $branchId): Builder
    {
        return InventoryNegativeCase::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    private function presentRelations(): array
    {
        return [
            'branch:id,name',
            'ingredient:id,name,unit_id',
            'ingredient.unit:id,symbol',
            'inventory:id,quantity_on_hand',
            'responsibleUser:id,name',
            'approver:id,name',
            'resolver:id,name',
            'verifier:id,name',
            'verificationRequester:id,name',
            'sourceTransaction',
        ];
    }

    private function latestCorrectionTransaction(InventoryNegativeCase $case): ?InventoryTransaction
    {
        return InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $case->restaurant_id)
            ->where('inventory_id', $case->inventory_id)
            ->where('direction', 'in')
            ->whereIn('type', ['purchase', 'external_receipt', 'adjustment', 'return', 'transfer', 'inventory_count', 'stocktake'])
            ->where('occurred_at', '>=', $case->detected_at ?? $case->created_at)
            ->latest('occurred_at')
            ->first();
    }

    private function presentTransaction(InventoryTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'document_code' => $transaction->document_code,
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'quantity' => (float) $transaction->quantity,
            'quantity_before' => $transaction->quantity_before !== null ? (float) $transaction->quantity_before : null,
            'quantity_after' => $transaction->quantity_after !== null ? (float) $transaction->quantity_after : null,
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'reference_code' => $transaction->reference_code,
            'notes' => $transaction->notes,
            'performed_by_name' => $transaction->performedBy?->name,
            'occurred_at' => $transaction->occurred_at?->format('d/m/Y H:i'),
        ];
    }

    private function workflowStep(string $status): string
    {
        return match ($status) {
            'open' => '1/6 · Tiếp nhận & phân loại',
            'pending_owner_approval' => '3/6 · Chờ Chủ doanh nghiệp duyệt',
            'in_progress' => '4/6 · Thực hiện phương án',
            'pending_verification' => '5/6 · Chờ đối chiếu độc lập',
            'resolved' => '6/6 · Đã xác minh & chốt',
            default => 'Đang xử lý',
        };
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'detected' => 'Tự động phát hiện',
            'plan_submitted' => 'Lập/cập nhật phương án',
            'owner_approved' => 'Chủ doanh nghiệp phê duyệt',
            'owner_rejected' => 'Chủ doanh nghiệp từ chối',
            'stock_replenished' => 'Tồn đã về 0 hoặc dương',
            'verification_requested' => 'Gửi yêu cầu đối chiếu',
            'verified_and_resolved' => 'Xác minh và chốt hồ sơ',
            'reopened' => 'Mở lại do âm trở lại',
            'negative_movement' => 'Phát sinh thêm giao dịch âm',
            'legacy_direct_resolution' => 'Chốt theo luồng tương thích cũ',
            default => $eventType,
        };
    }

    private function slaHours(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 24,
            'medium' => 48,
            default => 72,
        };
    }

    private function caseCode(InventoryNegativeCase $case): string
    {
        return 'NEG-'.now()->format('Ymd').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT);
    }

    private function recordEvent(
        InventoryNegativeCase $case,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $note = null,
        array $payload = [],
    ): void {
        InventoryNegativeCaseEvent::create([
            'restaurant_id' => $case->restaurant_id,
            'negative_case_id' => $case->id,
            'actor_id' => auth()->id(),
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'payload' => $payload ?: null,
        ]);
    }

    private function assertActorScope(InventoryNegativeCase $case, User $actor): void
    {
        if ((int) $case->restaurant_id !== (int) $actor->restaurant_id) {
            abort(403);
        }
        if ($actor->isOwner() || $actor->isSuperAdmin()) {
            return;
        }

        if ($actor->hasAnyRole(['warehouse_manager', 'warehouse_staff'])) {
            $centralBranchId = app(CentralWarehouseService::class)->getCentralWarehouse($actor->restaurant_id)?->id;
            abort_unless($centralBranchId && (int) $case->branch_id === (int) $centralBranchId, 403, 'Tài khoản Kho Tổng chỉ được xử lý hồ sơ tại Kho Tổng.');

            return;
        }

        abort_unless($actor->assignedBranchId() && (int) $actor->assignedBranchId() === (int) $case->branch_id, 403, 'Bạn không có quyền xử lý tồn âm của chi nhánh này.');
    }

    private function assertCanManage(User $actor): void
    {
        abort_unless(
            $actor->isOwner()
                || $actor->isSuperAdmin()
                || $actor->hasAnyRole(['manager', 'quản lý', 'quan_ly', 'quanly', 'warehouse_manager']),
            403,
            'Tài khoản này chỉ được xem hồ sơ âm nguyên liệu.',
        );
    }

    private function assertResponsibleUserScope(InventoryNegativeCase $case, User $actor, User $responsibleUser): void
    {
        if ($actor->isOwner() || $actor->isSuperAdmin()) {
            return;
        }

        if ($actor->hasRole('warehouse_manager')) {
            $centralBranchId = app(CentralWarehouseService::class)->getCentralWarehouse($actor->restaurant_id)?->id;
            abort_unless(
                $responsibleUser->assignedBranchId() === $centralBranchId
                    || (int) $responsibleUser->warehouse_branch_id === (int) $centralBranchId
                    || $responsibleUser->hasAnyRole(['warehouse_manager', 'warehouse_staff']),
                403,
                'Người phụ trách phải thuộc phạm vi Kho Tổng.',
            );

            return;
        }

        abort_unless((int) $responsibleUser->assignedBranchId() === (int) $case->branch_id, 403, 'Người phụ trách phải thuộc chi nhánh đang xử lý.');
    }

    private function classifySeverity(float $negativeQuantity, float $estimatedValue): string
    {
        // Conservative defaults; they can move to restaurant settings later
        // without changing the workflow contract.
        if ($estimatedValue >= 5_000_000 || $negativeQuantity >= 20) {
            return 'critical';
        }
        if ($estimatedValue >= 1_000_000 || $negativeQuantity >= 10) {
            return 'high';
        }
        if ($estimatedValue >= 200_000 || $negativeQuantity >= 3) {
            return 'medium';
        }

        return 'low';
    }

    private function sourceType(?InventoryTransaction $transaction): string
    {
        if (! $transaction) {
            return 'unknown';
        }

        $type = strtolower((string) $transaction->type);
        $source = strtolower((string) $transaction->source_type);

        return match (true) {
            $type === 'usage' && in_array($source, ['work_order', 'production', 'central_kitchen'], true) => 'production',
            $type === 'usage' => 'sale_usage',
            $type === 'transfer' => 'central_dispatch',
            $type === 'waste' || str_contains($source, 'waste') => 'waste',
            $type === 'inventory_count' || $type === 'stocktake' => 'stocktake',
            $type === 'adjustment' || $type === 'inventory_loss' => 'adjustment',
            $type === 'purchase' && str_contains($source, 'discrep') => 'receiving_discrepancy',
            default => 'unknown',
        };
    }

    private function audit(string $action, InventoryNegativeCase $case, array $newValues): void
    {
        if (auth()->check()) {
            AuditLog::log($action, 'updated', $case, null, array_merge([
                'negative_case_id' => $case->id,
                'branch_id' => $case->branch_id,
                'ingredient_id' => $case->ingredient_id,
            ], $newValues));
        }
    }

    private function defaultPlan(Inventory $inventory, float $negativeQuantity): string
    {
        $unit = $inventory->ingredient?->unit?->symbol ?? 'đơn vị';

        return "1) Xác minh giao dịch làm phát sinh âm; 2) lập đơn nhập/cấp phát bù {$negativeQuantity} {$unit}; 3) kiểm kê thực tế; 4) chỉ đóng hồ sơ sau khi tồn về 0 hoặc dương.";
    }
}
