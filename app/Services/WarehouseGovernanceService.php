<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryTransaction;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Models\WarehouseGovernanceRule;
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
        $rule = $this->getRules($restaurantId);
        $rule->update([
            'max_auto_approve_variance_amount' => $data['max_auto_approve_variance_amount'] ?? $rule->max_auto_approve_variance_amount,
            'max_auto_approve_variance_percent' => $data['max_auto_approve_variance_percent'] ?? $rule->max_auto_approve_variance_percent,
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
        $items = $request->items()->get();

        foreach ($items as $item) {
            $approvedQty = (float) ($item->approved_quantity ?? $item->requested_quantity);
            $receivedQty = $approvedQty;

            foreach ($receivedItems as $recItem) {
                if ($recItem['id'] == $item->id && isset($recItem['received_quantity'])) {
                    $receivedQty = (float) $recItem['received_quantity'];
                    break;
                }
            }

            if ($receivedQty < $approvedQty) {
                $discrepancyQty = $approvedQty - $receivedQty;
                $financialLoss = round($discrepancyQty * (float) $item->unit_cost, 2);

                $disputeCode = 'DSP-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (InventoryDiscrepancyDispute::where('restaurant_id', $request->restaurant_id)->count() + 1), 4, '0', STR_PAD_LEFT);

                $dispute = InventoryDiscrepancyDispute::create([
                    'restaurant_id' => $request->restaurant_id,
                    'supply_request_id' => $request->id,
                    'dispute_code' => $disputeCode,
                    'ingredient_id' => $item->ingredient_id,
                    'dispatched_quantity' => $approvedQty,
                    'received_quantity' => $receivedQty,
                    'discrepancy_quantity' => $discrepancyQty,
                    'financial_loss_amount' => $financialLoss,
                    'responsible_type' => 'unknown',
                    'responsible_user_id' => $request->dispatched_by, // Default suspect: warehouse dispatcher
                    'status' => 'open',
                    'dispute_reason' => "Chi nhánh nhận thiếu {$discrepancyQty} {$item->unit_symbol} theo đơn cấp phát {$request->request_code}.",
                ]);

                $disputes[] = $dispute;

                // Flag supply request
                $request->update(['discrepancy_flag' => true]);
            }
        }

        return $disputes;
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
        $dispute = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)->findOrFail($disputeId);

        $dispute->update([
            'responsible_type' => $responsibleType,
            'responsible_user_id' => $responsibleUserId,
            'status' => 'resolved',
            'resolution_notes' => $resolutionNotes,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
        ]);

        return $dispute->fresh(['ingredient', 'responsibleUser', 'resolver', 'supplyRequest']);
    }

    /**
     * Get financial risk dashboard and shrinkage statistics.
     */
    public function getRiskAndReliabilitySummary(int $restaurantId): array
    {
        $openDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->where('status', 'open')
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
