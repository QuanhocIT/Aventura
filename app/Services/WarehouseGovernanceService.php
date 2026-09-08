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
            $damagedQty = 0.0;
            $expiredQty = 0.0;
            $wrongItemQty = 0.0;

            foreach ($receivedItems as $recItem) {
                if ((int) ($recItem['id'] ?? 0) === (int) $item->id && isset($recItem['received_quantity'])) {
                    $receivedQty = (float) $recItem['received_quantity'];
                    $damagedQty = (float) ($recItem['received_damaged_quantity'] ?? 0);
                    $expiredQty = (float) ($recItem['received_expired_quantity'] ?? 0);
                    $wrongItemQty = (float) ($recItem['received_wrong_item_quantity'] ?? 0);
                    break;
                }
            }

            if ($receivedQty < 0 || $receivedQty > $dispatchedQty) {
                throw new \InvalidArgumentException('Số lượng nhận không hợp lệ so với số lượng thực xuất.');
            }

            $qualityLossQty = $damagedQty + $expiredQty + $wrongItemQty;
            $shortageQty = max(0, $dispatchedQty - $receivedQty);
            $discrepancyQty = ($dispatchedQty - $receivedQty) + $qualityLossQty;
            if ($discrepancyQty > 0) {
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
                    'dispute_reason' => $qualityLossQty > 0
                        ? "Chi nhánh ghi nhận {$qualityLossQty} ".($item->ingredient?->unit?->symbol ?? 'đơn vị')." hỏng/hết hạn/sai hàng và thiếu {$shortageQty} theo đơn cấp phát {$request->request_code}."
                        : "Chi nhánh nhận thiếu {$discrepancyQty} ".($item->ingredient?->unit?->symbol ?? 'đơn vị')." theo đơn cấp phát {$request->request_code}.",
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
        ?string $resolutionNotes = null,
        ?float $penaltyAmount = null,
        bool $writeOffInventory = false,
        ?string $claimStatus = null
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

        return DB::transaction(function () use (
            $disputeId,
            $restaurantId,
            $resolver,
            $responsibleType,
            $responsibleUserId,
            $resolutionNotes,
            $penaltyAmount,
            $writeOffInventory,
            $claimStatus
        ): InventoryDiscrepancyDispute {
            $dispute = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->findOrFail($disputeId);

            if (! in_array($dispute->status, ['open', 'investigating', 'appealed'], true)) {
                throw new \InvalidArgumentException('Biên bản đã được xử lý và không thể quy trách nhiệm lại.');
            }

            $dispute->loadMissing(['supplyRequest.toBranch', 'supplyRequest.fromBranch', 'ingredient']);
            $responsible = $responsibleUserId
                ? User::where('restaurant_id', $restaurantId)->where('status', 'active')->findOrFail($responsibleUserId)
                : null;

            $this->assertResponsibleParty($dispute, $responsibleType, $responsible, $restaurantId);

            $lossAmount = (float) $dispute->financial_loss_amount;
            $effectivePenalty = 0.0;
            $waivedAmount = 0.0;

            if (in_array($responsibleType, ['warehouse_staff', 'branch_staff'], true)) {
                $effectivePenalty = $penaltyAmount !== null ? max(0, min($lossAmount, $penaltyAmount)) : $lossAmount;
                $waivedAmount = max(0, round($lossAmount - $effectivePenalty, 2));
            } elseif ($responsibleType === 'transporter') {
                $effectivePenalty = $penaltyAmount !== null ? max(0, min($lossAmount, $penaltyAmount)) : $lossAmount;
                $waivedAmount = max(0, round($lossAmount - $effectivePenalty, 2));
                if (! $claimStatus) {
                    $claimStatus = 'pending_collection';
                }
            } else { // unknown
                $effectivePenalty = 0.0;
                $waivedAmount = $lossAmount;
            }

            $penaltyAdjustment = $this->createPenaltyAdjustmentIfEnabled(
                $dispute,
                $responsibleType,
                $responsible,
                $restaurantId,
                $effectivePenalty
            );

            if ($dispute->status === 'appealed' && ! $penaltyAdjustment && in_array($responsibleType, ['transporter', 'unknown'], true)) {
                $this->waiveExistingPenaltyAdjustment($dispute, $restaurantId);
            }

            $storedResolutionNotes = $resolutionNotes;
            if ($dispute->status === 'appealed' && filled($dispute->resolution_notes)) {
                $storedResolutionNotes = trim($dispute->resolution_notes."\n[Kết luận xem xét lại]: ".trim($resolutionNotes));
            }

            $writeOffTransactionId = $dispute->write_off_transaction_id;
            $writeOffAt = $dispute->write_off_at;

            if ($writeOffInventory && ! $writeOffTransactionId) {
                $targetBranchId = $dispute->supplyRequest?->to_branch_id ?? $dispute->supplyRequest?->from_branch_id;
                if ($targetBranchId && $dispute->ingredient_id) {
                    $inventory = \App\Models\Inventory::firstOrCreate(
                        [
                            'restaurant_id' => $restaurantId,
                            'branch_id' => $targetBranchId,
                            'ingredient_id' => $dispute->ingredient_id,
                        ],
                        ['quantity_on_hand' => 0]
                    );

                    $writeOffQty = (float) $dispute->discrepancy_quantity;
                    $unitCost = $writeOffQty > 0
                        ? round($lossAmount / $writeOffQty, 2)
                        : (float) ($dispute->ingredient?->cost_price ?? 0);

                    $tx = InventoryTransaction::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $targetBranchId,
                        'ingredient_id' => $dispute->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $resolver->id,
                        'type' => 'waste',
                        'waste_category' => 'damage_transit',
                        'direction' => 'out',
                        'quantity' => $writeOffQty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lossAmount,
                        'source_type' => 'inventory_dispute',
                        'source_id' => $dispute->id,
                        'notes' => "Hạch toán xuất hao hụt thất thoát theo biên bản {$dispute->dispute_code}",
                        'occurred_at' => now(),
                    ]);

                    $writeOffTransactionId = $tx->id;
                    $writeOffAt = now();

                    // Ghi nhận bút toán kép tài chính: Có 1521 (giảm tồn kho), Nợ 1388 (phải thu bồi thường), Nợ 8111 (chi phí thất thoát nhà hàng chịu)
                    try {
                        $journalLines = [];
                        if ($effectivePenalty > 0) {
                            $journalLines[] = [
                                'account' => '1388',
                                'debit' => $effectivePenalty,
                                'credit' => 0,
                                'description' => "Phải thu bồi thường thất thoát chuyển kho ({$dispute->dispute_code})",
                            ];
                        }
                        if ($waivedAmount > 0) {
                            $journalLines[] = [
                                'account' => '8111',
                                'debit' => $waivedAmount,
                                'credit' => 0,
                                'description' => "Chi phí hao hụt hàng hóa nhà hàng chịu ({$dispute->dispute_code})",
                            ];
                        }
                        $journalLines[] = [
                            'account' => '1521',
                            'debit' => 0,
                            'credit' => $lossAmount,
                            'description' => "Giảm trừ tồn kho nguyên vật liệu do thất thoát ({$dispute->dispute_code})",
                        ];

                        if (count($journalLines) >= 2 && $lossAmount > 0) {
                            app(\App\Services\FinancialPostingService::class)->post([
                                'restaurant_id' => $restaurantId,
                                'branch_id' => $targetBranchId,
                                'entry_date' => now()->toDateString(),
                                'source_type' => InventoryDiscrepancyDispute::class,
                                'source_id' => $dispute->id,
                                'idempotency_key' => "inventory_dispute:write_off:{$dispute->id}",
                                'description' => "Hạch toán hao hụt chuyển kho theo biên bản {$dispute->dispute_code}",
                                'created_by' => $resolver->id,
                                'lines' => $journalLines,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Financial posting on dispute write-off failed: {$e->getMessage()}", [
                            'dispute_id' => $dispute->id,
                        ]);
                    }
                }
            }

            $finalStatus = 'resolved';
            if ($penaltyAdjustment) {
                $finalStatus = 'penalized';
            } elseif ($responsibleType === 'transporter' && $effectivePenalty > 0 && ($claimStatus ?? 'pending_collection') === 'pending_collection') {
                $finalStatus = 'investigating';
            }

            $dispute->update([
                'responsible_type' => $responsibleType,
                'responsible_user_id' => $responsibleUserId,
                'penalty_amount' => $effectivePenalty,
                'waived_amount' => $waivedAmount,
                'claim_status' => $responsibleType === 'transporter' ? ($claimStatus ?? 'pending_collection') : null,
                'write_off_transaction_id' => $writeOffTransactionId,
                'write_off_at' => $writeOffAt,
                'status' => $finalStatus,
                'resolution_notes' => $storedResolutionNotes,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
            ]);

            if ($responsible) {
                $responsible->notify(new WarehouseDisputeAssignedNotification($dispute->fresh(['ingredient', 'supplyRequest']), $resolver));
            }

            return $dispute->fresh([
                'ingredient.unit',
                'responsibleUser',
                'resolver',
                'claimCollector',
                'supplyRequest.toBranch',
                'supplyRequest.fromBranch',
                'supplyRequest.transporter',
                'supplyRequest.receivingReport.confirmedBy',
                'supplyRequest.receivingReport.driverConfirmedBy',
                'writeOffTransaction',
            ]);
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
        int $restaurantId,
        ?float $penaltyAmount = null
    ): ?SalaryAdjustment {
        $rules = $this->getRules($restaurantId);
        if (! $rules->penalty_deduction_enabled || ! $responsible || ! in_array($responsibleType, ['warehouse_staff', 'branch_staff'], true)) {
            return null;
        }

        $effectivePenalty = $penaltyAmount !== null
            ? min((float) $dispute->financial_loss_amount, max(0, $penaltyAmount))
            : (float) $dispute->financial_loss_amount;

        if ($effectivePenalty <= 0) {
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
            if ($existing->status !== 'applied' || (float) $existing->amount !== $effectivePenalty) {
                $existing->update([
                    'amount' => $effectivePenalty,
                    'status' => 'applied',
                    'dispute_reason' => null,
                ]);
                app(SalaryService::class)->recalculate($existing->salary);
            }

            return $existing;
        }

        $salaryService = app(SalaryService::class);
        $targetDate = now();
        $salary = $salaryService->getOrCreateDraft($restaurantId, $employee, $targetDate->toDateString());
        $rolloverNote = '';
        while (in_array($salary->status, ['approved', 'paid'], true)) {
            $targetDate = $targetDate->copy()->addMonth()->startOfMonth();
            $salary = $salaryService->getOrCreateDraft($restaurantId, $employee, $targetDate->toDateString());
            $rolloverNote = " [Chuyển khấu trừ sang kỳ {$targetDate->format('m/Y')} do kỳ trước đã khóa sổ]";
        }

        return $salaryService->addAdjustment($salary, [
            'employee_id' => $employee->id,
            'type' => 'inventory_loss',
            'amount' => $effectivePenalty,
            'reason' => "Bồi thường thất thoát giao nhận {$dispute->dispute_code} — {$dispute->dispute_reason}{$rolloverNote}",
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

        return $dispute->fresh([
            'ingredient.unit',
            'responsibleUser',
            'resolver',
            'supplyRequest.toBranch',
            'supplyRequest.fromBranch',
            'supplyRequest.transporter',
            'supplyRequest.receivingReport.confirmedBy',
            'supplyRequest.receivingReport.driverConfirmedBy',
            'writeOffTransaction',
            'claimCollector',
        ]);
    }

    /**
     * Thu tiền bồi thường từ đơn vị vận chuyển đối với biên bản lệch hàng.
     */
    public function collectTransporterClaim(
        int $disputeId,
        int $restaurantId,
        User $collector,
        float $collectedAmount,
        string $paymentMethod = 'cash',
        ?string $notes = null
    ): InventoryDiscrepancyDispute {
        return DB::transaction(function () use ($disputeId, $restaurantId, $collector, $collectedAmount, $paymentMethod, $notes): InventoryDiscrepancyDispute {
            $dispute = InventoryDiscrepancyDispute::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->findOrFail($disputeId);

            if ($dispute->responsible_type !== 'transporter') {
                throw new \InvalidArgumentException('Chỉ có thể thu tiền bồi thường đối với biên bản quy trách nhiệm vận chuyển.');
            }

            if ($collectedAmount <= 0) {
                throw new \InvalidArgumentException('Số tiền thu bồi thường phải lớn hơn 0.');
            }

            $dispute->update([
                'claim_status' => 'collected',
                'status' => 'resolved',
                'claim_collected_amount' => $collectedAmount,
                'claim_collected_at' => now(),
                'claim_collected_by' => $collector->id,
                'claim_notes' => $notes,
            ]);

            $accountCode = $paymentMethod === 'bank' ? '1121' : '1111';
            $targetBranchId = $dispute->supplyRequest?->to_branch_id ?? $dispute->supplyRequest?->from_branch_id;

            try {
                app(\App\Services\FinancialPostingService::class)->post([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $targetBranchId,
                    'entry_date' => now()->toDateString(),
                    'source_type' => InventoryDiscrepancyDispute::class,
                    'source_id' => $dispute->id,
                    'idempotency_key' => "inventory_dispute:claim_collection:{$dispute->id}:".now()->timestamp,
                    'description' => "Thu tiền bồi thường vận chuyển biên bản {$dispute->dispute_code}",
                    'created_by' => $collector->id,
                    'lines' => [
                        [
                            'account' => $accountCode,
                            'debit' => $collectedAmount,
                            'credit' => 0,
                            'description' => "Thu tiền bồi thường thất thoát hàng ({$paymentMethod})",
                        ],
                        [
                            'account' => '1388',
                            'debit' => 0,
                            'credit' => $collectedAmount,
                            'description' => "Giảm trừ khoản phải thu bồi thường ({$dispute->dispute_code})",
                        ],
                    ],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Financial posting on claim collection failed: {$e->getMessage()}", [
                    'dispute_id' => $dispute->id,
                ]);
            }

            return $dispute->fresh([
                'ingredient.unit',
                'responsibleUser',
                'resolver',
                'claimCollector',
                'supplyRequest.toBranch',
                'supplyRequest.fromBranch',
                'supplyRequest.transporter',
                'supplyRequest.receivingReport.confirmedBy',
                'supplyRequest.receivingReport.driverConfirmedBy',
                'writeOffTransaction',
            ]);
        });
    }

    /**
     * Get financial risk dashboard and shrinkage statistics.
     */
    public function getRiskAndReliabilitySummary(int $restaurantId): array
    {
        $openDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['open', 'investigating', 'appealed'])
            ->count();

        $totalFinancialLoss = (float) InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->sum('financial_loss_amount');

        $totalPenalized = (float) InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->sum('penalty_amount');

        $totalWaived = (float) InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->sum('waived_amount');

        $totalClaimCollected = (float) InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->where('claim_status', 'collected')
            ->sum('claim_collected_amount');

        $wasteLossTotal = (float) InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->sum('total_cost');

        $recentDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->with([
                'ingredient.unit',
                'responsibleUser',
                'resolver',
                'claimCollector',
                'supplyRequest.toBranch',
                'supplyRequest.fromBranch',
                'supplyRequest.transporter',
                'supplyRequest.receivingReport.confirmedBy',
                'supplyRequest.receivingReport.driverConfirmedBy',
                'writeOffTransaction',
            ])
            ->orderByDesc('id')
            ->take(50)
            ->get();

        $rules = $this->getRules($restaurantId);

        return [
            'open_disputes_count' => $openDisputes,
            'total_discrepancy_loss' => $totalFinancialLoss,
            'total_penalized_amount' => $totalPenalized,
            'total_waived_amount' => $totalWaived,
            'total_claim_collected' => $totalClaimCollected,
            'total_waste_loss' => $wasteLossTotal,
            'total_combined_loss' => $totalFinancialLoss + $wasteLossTotal,
            'rules' => $rules,
            'recent_disputes' => $recentDisputes,
        ];
    }
}
