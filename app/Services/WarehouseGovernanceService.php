<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\SalaryAdjustment;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Models\WarehouseGovernanceRule;
use App\Notifications\WarehouseDisputeAssignedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseGovernanceService
{
    /**
     * Get or create default rules for a restaurant.
     */
    public function getRules(int $restaurantId): WarehouseGovernanceRule
    {
        return WarehouseGovernanceRule::firstOrCreate(
            ['restaurant_id' => $restaurantId],
            [
                'max_auto_approve_variance_amount' => 500000, // 500,000 VND
                'max_auto_approve_variance_percent' => 3.00, // 3%
                'require_seal_code_on_dispatch' => true,
                'auto_dispute_on_discrepancy' => true,
                'penalty_deduction_enabled' => true,
            ]
        );
    }

    /**
     * Update governance rules.
     */
    public function updateRules(int $restaurantId, array $data, User $user): WarehouseGovernanceRule
    {
        if (! $user->isSuperAdmin() && (int) $user->restaurant_id !== $restaurantId) {
            throw new \InvalidArgumentException('Không thể cập nhật quy tắc của nhà hàng khác.');
        }

        $amount = array_key_exists('max_auto_approve_variance_amount', $data)
            ? (float) $data['max_auto_approve_variance_amount']
            : null;
        $percent = array_key_exists('max_auto_approve_variance_percent', $data)
            ? (float) $data['max_auto_approve_variance_percent']
            : null;

        if ($amount !== null && $amount < 0) {
            throw new \InvalidArgumentException('Hạn mức tiền chênh lệch không được âm.');
        }
        if ($percent !== null && ($percent < 0 || $percent > 100)) {
            throw new \InvalidArgumentException('Tỷ lệ sai lệch phải nằm trong khoảng 0–100%.');
        }

        $rule = $this->getRules($restaurantId);
        $rule->update([
            'max_auto_approve_variance_amount' => $amount ?? $rule->max_auto_approve_variance_amount,
            'max_auto_approve_variance_percent' => $percent ?? $rule->max_auto_approve_variance_percent,
            'require_seal_code_on_dispatch' => isset($data['require_seal_code_on_dispatch']) ? (bool) $data['require_seal_code_on_dispatch'] : $rule->require_seal_code_on_dispatch,
            'auto_dispute_on_discrepancy' => isset($data['auto_dispute_on_discrepancy']) ? (bool) $data['auto_dispute_on_discrepancy'] : $rule->auto_dispute_on_discrepancy,
            'penalty_deduction_enabled' => isset($data['penalty_deduction_enabled']) ? (bool) $data['penalty_deduction_enabled'] : $rule->penalty_deduction_enabled,
            'updated_by' => $user->id,
        ]);

        return $rule->fresh();
    }

    /**
     * Check if a variance exceeds governance threshold.
     */
    public function isVarianceOverThreshold(int $restaurantId, float $varianceAmount, float $variancePercent): bool
    {
        $rules = $this->getRules($restaurantId);

        if (abs($varianceAmount) > (float) $rules->max_auto_approve_variance_amount) {
            return true;
        }

        if (abs($variancePercent) > (float) $rules->max_auto_approve_variance_percent) {
            return true;
        }

        return false;
    }

    /**
     * Check for supply request discrepancies and create disputes automatically.
     */
    public function checkAndCreateDisputesFromSupplyRequest(SupplyRequest $request, array $receivedItems): array
    {
        $rules = $this->getRules($request->restaurant_id);
        if (! $rules->auto_dispute_on_discrepancy) {
            return [];
        }

        $disputes = [];
        $items = $request->items()->with('ingredient.unit')->get();

        foreach ($items as $item) {
            // Compare against the physical quantity that left the warehouse.
            // The approved quantity can be higher when picking found a shortage.
            $dispatchedQty = (float) $item->effective_dispatched_quantity;
            $receivedQty = $dispatchedQty;

            foreach ($receivedItems as $recItem) {
                if ((int) ($recItem['id'] ?? 0) === (int) $item->id && isset($recItem['received_quantity'])) {
                    $receivedQty = (float) $recItem['received_quantity'];
                    break;
                }
            }

            if ($receivedQty < 0 || $receivedQty > $dispatchedQty) {
                throw new \InvalidArgumentException('Số lượng nhận không hợp lệ so với số lượng thực xuất.');
            }

            if ($receivedQty < $dispatchedQty) {
                $discrepancyQty = $dispatchedQty - $receivedQty;
                $financialLoss = round($discrepancyQty * (float) $item->unit_cost, 2);

                // Receiving can be retried after a network timeout. Keep one
                // dispute per supply-request line so the loss is never doubled.
                $existingDispute = InventoryDiscrepancyDispute::where('restaurant_id', $request->restaurant_id)
                    ->where('supply_request_id', $request->id)
                    ->where(function ($query) use ($item): void {
                        $query->where('supply_request_item_id', $item->id)
                            ->orWhere(function ($legacyQuery) use ($item): void {
                                $legacyQuery->whereNull('supply_request_item_id')
                                    ->where('ingredient_id', $item->ingredient_id);
                            });
                    })
                    ->first();

                if ($existingDispute) {
                    continue;
                }

                $disputeCode = $this->generateDisputeCode();

                $dispute = InventoryDiscrepancyDispute::create([
                    'restaurant_id' => $request->restaurant_id,
                    'supply_request_id' => $request->id,
                    'supply_request_item_id' => $item->id,
                    'dispute_code' => $disputeCode,
                    'ingredient_id' => $item->ingredient_id,
                    'dispatched_quantity' => $dispatchedQty,
                    'received_quantity' => $receivedQty,
                    'discrepancy_quantity' => $discrepancyQty,
                    'financial_loss_amount' => $financialLoss,
                    'responsible_type' => 'unassigned',
                    'responsible_user_id' => null, // Không tự động quy trách nhiệm trước khi hoàn tất điều tra
                    'status' => 'open',
                    'dispute_reason' => "Chi nhánh nhận thiếu {$discrepancyQty} ".($item->ingredient?->unit?->symbol ?? 'đơn vị')." theo đơn cấp phát {$request->request_code}.",
                ]);

                $disputes[] = $dispute;

                // Flag supply request
                $request->update(['discrepancy_flag' => true]);
            }
        }

        return $disputes;
    }

    private function generateDisputeCode(): string
    {
        $prefix = 'DSP-'.Carbon::now()->format('Ymd').'-';
        // dispute_code is globally unique, so the sequence must not restart
        // for every restaurant.
        $nextNumber = (int) InventoryDiscrepancyDispute::withoutGlobalScopes()->count() + 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $prefix.str_pad((string) ($nextNumber + $attempt), 4, '0', STR_PAD_LEFT);
            if (! InventoryDiscrepancyDispute::withoutGlobalScopes()->where('dispute_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Resolve a discrepancy dispute and assign accountability.
     */
    public function resolveDispute(
        int $disputeId,
        int $restaurantId,
        User $resolver,
        string $responsibleType,
        ?int $responsibleUserId = null,
        ?string $resolutionNotes = null
    ): InventoryDiscrepancyDispute {
        if (! $resolver->isSuperAdmin() && (int) $resolver->restaurant_id !== $restaurantId) {
            throw new \InvalidArgumentException('Không thể xử lý biên bản của nhà hàng khác.');
        }
        if (! in_array($responsibleType, ['warehouse_staff', 'transporter', 'branch_staff', 'unknown'], true)) {
            throw new \InvalidArgumentException('Loại trách nhiệm không hợp lệ.');
        }
        if (blank(trim((string) $resolutionNotes))) {
            throw new \InvalidArgumentException('Bắt buộc ghi nhận kết luận xử lý biên bản.');
        }

        return DB::transaction(function () use ($disputeId, $restaurantId, $resolver, $responsibleType, $responsibleUserId, $resolutionNotes): InventoryDiscrepancyDispute {
            $dispute = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->findOrFail($disputeId);

            if (! in_array($dispute->status, ['open', 'investigating', 'appealed'], true)) {
                throw new \InvalidArgumentException('Biên bản đã được xử lý và không thể quy trách nhiệm lại.');
            }

            $dispute->loadMissing(['supplyRequest', 'ingredient']);
            $responsible = $responsibleUserId
                ? User::where('restaurant_id', $restaurantId)->where('status', 'active')->findOrFail($responsibleUserId)
                : null;

            $this->assertResponsibleParty($dispute, $responsibleType, $responsible, $restaurantId);
            $penaltyAdjustment = $this->createPenaltyAdjustmentIfEnabled($dispute, $responsibleType, $responsible, $restaurantId);
            if ($dispute->status === 'appealed' && ! $penaltyAdjustment && in_array($responsibleType, ['transporter', 'unknown'], true)) {
                $this->waiveExistingPenaltyAdjustment($dispute, $restaurantId);
            }

            $storedResolutionNotes = $resolutionNotes;
            if ($dispute->status === 'appealed' && filled($dispute->resolution_notes)) {
                $storedResolutionNotes = trim($dispute->resolution_notes."\n[Kết luận xem xét lại]: ".trim($resolutionNotes));
            }

            $dispute->update([
                'responsible_type' => $responsibleType,
                'responsible_user_id' => $responsibleUserId,
                'status' => $penaltyAdjustment ? 'penalized' : 'resolved',
                'resolution_notes' => $storedResolutionNotes,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
            ]);

            if ($responsible) {
                $responsible->notify(new WarehouseDisputeAssignedNotification($dispute->fresh(['ingredient', 'supplyRequest']), $resolver));
            }

            return $dispute->fresh(['ingredient', 'responsibleUser', 'resolver', 'supplyRequest']);
        });
    }

    private function assertResponsibleParty(
        InventoryDiscrepancyDispute $dispute,
        string $responsibleType,
        ?User $responsible,
        int $restaurantId
    ): void {
        if (in_array($responsibleType, ['transporter', 'unknown'], true) && $responsible) {
            throw new \InvalidArgumentException('Không được gán tài khoản nội bộ cho trách nhiệm bên ngoài hoặc chưa xác định.');
        }

        if ($responsibleType === 'warehouse_staff' && $responsible) {
            if (! $responsible->hasRole('warehouse_staff')) {
                throw new \InvalidArgumentException('Tài khoản được gán phải có vai trò nhân viên Kho Tổng.');
            }

            $centralBranchId = RestaurantBranch::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->where(fn ($query) => $query->where('is_central_warehouse', true)->orWhere('warehouse_type', 'central'))
                ->value('id');
            $assignedBranchId = $responsible->warehouse_branch_id ?: $responsible->branch_id;

            if ($centralBranchId && (int) $assignedBranchId !== (int) $centralBranchId) {
                throw new \InvalidArgumentException('Nhân viên được gán phải thuộc Kho Tổng đang hoạt động.');
            }
        }

        if ($responsibleType === 'branch_staff' && $responsible) {
            $branchId = (int) $dispute->supplyRequest?->to_branch_id;
            if ($branchId && ! $responsible->canAccessBranch($branchId)) {
                throw new \InvalidArgumentException('Nhân sự chi nhánh phải thuộc đúng chi nhánh nhận hàng.');
            }
        }
    }

    private function createPenaltyAdjustmentIfEnabled(
        InventoryDiscrepancyDispute $dispute,
        string $responsibleType,
        ?User $responsible,
        int $restaurantId
    ): ?SalaryAdjustment {
        $rules = $this->getRules($restaurantId);
        if (! $rules->penalty_deduction_enabled || ! $responsible || ! in_array($responsibleType, ['warehouse_staff', 'branch_staff'], true)) {
            return null;
        }

        $employee = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $responsible->id)
            ->where('status', 'active')
            ->first();
        if (! $employee) {
            // Legacy warehouse accounts may not have an HR employee record yet.
            return null;
        }

        $existing = SalaryAdjustment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('reference_type', InventoryDiscrepancyDispute::class)
            ->where('reference_id', $dispute->id)
            ->first();
        if ($existing) {
            if ($existing->status !== 'applied') {
                $existing->update([
                    'status' => 'applied',
                    'dispute_reason' => null,
                ]);
                app(SalaryService::class)->recalculate($existing->salary);
            }

            return $existing;
        }

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurantId, $employee, now()->toDateString());
        if (in_array($salary->status, ['approved', 'paid'], true)) {
            throw new \InvalidArgumentException('Bảng lương của nhân sự đã khóa, không thể tự động thêm khoản bồi thường.');
        }

        return $salaryService->addAdjustment($salary, [
            'employee_id' => $employee->id,
            'type' => 'inventory_loss',
            'amount' => (float) $dispute->financial_loss_amount,
            'reason' => "Bồi thường thất thoát giao nhận {$dispute->dispute_code} — {$dispute->dispute_reason}",
            'reference_id' => $dispute->id,
            'reference_type' => InventoryDiscrepancyDispute::class,
            'status' => 'applied',
        ]);
    }

    private function waiveExistingPenaltyAdjustment(InventoryDiscrepancyDispute $dispute, int $restaurantId): void
    {
        $adjustment = SalaryAdjustment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('reference_type', InventoryDiscrepancyDispute::class)
            ->where('reference_id', $dispute->id)
            ->first();

        if (! $adjustment || $adjustment->status === 'waived') {
            return;
        }

        $adjustment->update([
            'status' => 'waived',
            'dispute_reason' => 'Đã loại trừ trách nhiệm cá nhân sau khi xem xét phản hồi.',
        ]);
        app(SalaryService::class)->recalculate($adjustment->salary);
    }

    public function respondToDispute(int $disputeId, int $restaurantId, User $actor, string $response): InventoryDiscrepancyDispute
    {
        if (blank(trim($response))) {
            throw new \InvalidArgumentException('Nội dung phản hồi không được để trống.');
        }

        $dispute = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->where('responsible_user_id', $actor->id)
            ->whereIn('status', ['investigating', 'open', 'resolved', 'penalized'])
            ->findOrFail($disputeId);

        if (! $actor->isSuperAdmin() && (int) $actor->restaurant_id !== $restaurantId) {
            throw new \InvalidArgumentException('Không thể phản hồi biên bản của nhà hàng khác.');
        }

        $dispute->update([
            'status' => 'appealed',
            'resolution_notes' => trim(($dispute->resolution_notes ? $dispute->resolution_notes."\n" : '').'[Phản hồi người được quy trách nhiệm '.$actor->name.']: '.trim($response)),
        ]);

        $penaltyAdjustment = SalaryAdjustment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('reference_type', InventoryDiscrepancyDispute::class)
            ->where('reference_id', $dispute->id)
            ->first();
        if ($penaltyAdjustment && $penaltyAdjustment->status === 'applied') {
            $penaltyAdjustment->update([
                'status' => 'disputed',
                'dispute_reason' => trim($response),
            ]);
            app(SalaryService::class)->recalculate($penaltyAdjustment->salary);
        }

        User::where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($dispute) {
                $query->whereKey($dispute->resolved_by)
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'super_admin', 'warehouse_manager']));
            })
            ->get()
            ->each(fn (User $user) => $user->notify(new WarehouseDisputeAssignedNotification($dispute, $actor, true)));

        return $dispute->fresh(['ingredient', 'responsibleUser', 'resolver', 'supplyRequest']);
    }

    /**
     * Get financial risk dashboard and shrinkage statistics.
     */
    public function getRiskAndReliabilitySummary(int $restaurantId): array
    {
        $openDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['open', 'investigating', 'appealed'])
            ->count();

        $totalFinancialLoss = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->sum('financial_loss_amount');

        $wasteLossTotal = InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->sum('total_cost');

        $recentDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->with(['ingredient', 'responsibleUser', 'supplyRequest.toBranch', 'supplyRequest.fromBranch'])
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $rules = $this->getRules($restaurantId);

        return [
            'open_disputes_count' => $openDisputes,
            'total_discrepancy_loss' => (float) $totalFinancialLoss,
            'total_waste_loss' => (float) $wasteLossTotal,
            'total_combined_loss' => (float) ($totalFinancialLoss + $wasteLossTotal),
            'rules' => $rules,
            'recent_disputes' => $recentDisputes,
        ];
    }
}
