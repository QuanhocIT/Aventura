<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(private SalaryService $salaryService) {}

    /**
     * Tạo yêu cầu chờ phê duyệt và thông báo đến Owner.
     */
    public function submitRequest(string $operationType, array $data, User $requester): ApprovalRequest
    {
        $request = ApprovalRequest::create([
            'restaurant_id'  => $requester->restaurant_id,
            'branch_id'      => $requester->branch_id,
            'requester_id'   => $requester->id,
            'operation_type' => $operationType,
            'operation_data' => $data,
            'status'         => 'pending',
        ]);

        // Thông báo Owner
        $owner = User::where('restaurant_id', $requester->restaurant_id)
            ->role('owner')
            ->first();

        if ($owner) {
            $owner->notify(new ApprovalRequestedNotification($request, $requester));
        }

        return $request;
    }

    /**
     * Owner phê duyệt → thực thi thao tác → thông báo requester.
     */
    public function approve(ApprovalRequest $approval, User $reviewer): void
    {
        DB::transaction(function () use ($approval, $reviewer) {
            $this->executeOperation($approval);

            $approval->update([
                'status'      => 'approved',
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'approved'));
    }

    /**
     * Owner từ chối → thông báo requester.
     */
    public function reject(ApprovalRequest $approval, User $reviewer, string $reason): void
    {
        $approval->update([
            'status'           => 'rejected',
            'reviewer_id'      => $reviewer->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'rejected'));
    }

    /**
     * Thực thi thao tác đã được phê duyệt.
     */
    private function executeOperation(ApprovalRequest $approval): void
    {
        $data = $approval->operation_data;

        match ($approval->operation_type) {
            'inventory_purchase' => $this->executePurchase($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_waste'    => $this->executeWaste($data, $approval->restaurant_id, $approval->requester_id),
            'salary_adjustment'  => $this->executeSalaryAdjustment($data, $approval->restaurant_id),
            default              => null,
        };
    }

    private function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);

        $inventory = Inventory::withoutGlobalScopes()->firstOrCreate(
            ['restaurant_id' => $restaurantId, 'ingredient_id' => $ingredient->id],
            ['quantity_on_hand' => 0, 'theoretical_quantity' => 0, 'last_cost' => 0]
        );

        $newQty  = (float) $data['quantity'];
        $newCost = (float) $data['unit_cost'];
        $oldQty  = (float) $inventory->quantity_on_hand;
        $oldAvg  = (float) $ingredient->average_cost;

        $newAvg = ($oldQty + $newQty) > 0
            ? (($oldQty * $oldAvg) + ($newQty * $newCost)) / ($oldQty + $newQty)
            : $newCost;

        InventoryTransaction::create([
            'restaurant_id'    => $restaurantId,
            'ingredient_id'    => $ingredient->id,
            'inventory_id'     => $inventory->id,
            'supplier_id'      => $data['supplier_id'] ?? null,
            'performed_by'     => $performedBy,
            'type'             => 'purchase',
            'direction'        => 'in',
            'quantity'         => $newQty,
            'unit_cost'        => $newCost,
            'total_cost'       => $newQty * $newCost,
            'invoice_file_url' => $data['invoice_file_url'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'occurred_at'      => $data['occurred_at'] ?? now(),
        ]);

        $inventory->update([
            'quantity_on_hand'     => $oldQty + $newQty,
            'theoretical_quantity' => $inventory->theoretical_quantity + $newQty,
            'last_cost'            => $newCost,
        ]);

        $ingredient->update(['average_cost' => round($newAvg, 2)]);
    }

    private function executeWaste(array $data, int $restaurantId, int $performedBy): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);

        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $wasteQty  = (float) $data['quantity'];
        $wasteCost = $wasteQty * (float) $ingredient->average_cost;

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'ingredient_id' => $ingredient->id,
            'inventory_id'  => $inventory?->id,
            'performed_by'  => $performedBy,
            'type'          => 'waste',
            'direction'     => 'out',
            'quantity'      => $wasteQty,
            'unit_cost'     => (float) $ingredient->average_cost,
            'total_cost'    => $wasteCost,
            'notes'         => $data['notes'] ?? null,
            'occurred_at'   => now(),
        ]);

        if ($inventory) {
            $inventory->update([
                'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
            ]);
        }

        if (! empty($data['employee_id']) && $wasteCost > 0) {
            $employee = Employee::withoutGlobalScopes()->find($data['employee_id']);
            if ($employee) {
                $allowedRatio = $ingredient ? (float) ($ingredient->allowed_waste_ratio ?? 0) : 0;
                $penaltyAmount = $wasteCost * (1 - $allowedRatio / 100);
                $penaltyAmount = max(0.0, $penaltyAmount);

                if ($penaltyAmount > 0) {
                    $salary = $this->salaryService->getOrCreateDraft($restaurantId, $employee, now()->toDateString());
                    $this->salaryService->addAdjustment($salary, [
                        'employee_id'    => $employee->id,
                        'type'           => 'inventory_loss',
                        'amount'         => $penaltyAmount,
                        'reason'         => "Hao hụt {$ingredient->name}: {$wasteQty} " . ($ingredient->unit?->symbol ?? '') . ' — ' . number_format($wasteCost) . 'đ' . " (Đã khấu trừ " . $allowedRatio . "% định mức cho phép)",
                        'reference_id'   => $transaction->id,
                        'reference_type' => InventoryTransaction::class,
                        'status'         => 'applied',
                    ]);
                }
            }
        }
    }

    private function executeSalaryAdjustment(array $data, int $restaurantId): void
    {
        $salary = Salary::withoutGlobalScopes()->findOrFail($data['salary_id']);

        $this->salaryService->addAdjustment($salary, [
            'employee_id' => $salary->employee_id,
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'reason'      => $data['reason'],
            'status'      => 'applied',
        ]);
    }
}
