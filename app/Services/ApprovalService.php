<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalRequestedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private SalaryService $salaryService,
        private InventoryService $inventoryService
    ) {}

    /**
     * Tạo yêu cầu chờ phê duyệt và thông báo đến Owner.
     */
    public function submitRequest(string $operationType, array $data, User $requester): ApprovalRequest
    {
        $request = ApprovalRequest::create([
            'restaurant_id' => $requester->restaurant_id,
            'branch_id' => $requester->branch_id,
            'requester_id' => $requester->id,
            'operation_type' => $operationType,
            'operation_data' => $data,
            'status' => 'pending',
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
            // Khóa bi quan bản ghi phê duyệt để tránh chạy trùng lặp
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if ($lockedApproval->status !== 'pending') {
                throw new \Exception('Yêu cầu này đã được xử lý trước đó.');
            }

            $this->executeOperation($lockedApproval);

            $lockedApproval->update([
                'status' => 'approved',
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
        DB::transaction(function () use ($approval, $reviewer, $reason) {
            // Khóa bi quan bản ghi phê duyệt
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if ($lockedApproval->status !== 'pending') {
                throw new \Exception('Yêu cầu này đã được xử lý trước đó.');
            }

            $lockedApproval->update([
                'status' => 'rejected',
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });

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
            'inventory_waste' => $this->executeWaste($data, $approval->restaurant_id, $approval->requester_id),
            'salary_adjustment' => $this->executeSalaryAdjustment($data, $approval->restaurant_id),
            default => null,
        };
    }

    private function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase($data, $restaurantId, $performedBy);
    }

    private function executeWaste(array $data, int $restaurantId, int $performedBy): void
    {
        $transaction = $this->inventoryService->executeWaste($data, $restaurantId, $performedBy);

        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);
        $wasteQty = (float) $data['quantity'];
        $wasteCost = $wasteQty * (float) $ingredient->average_cost;

        if ($transaction && ! empty($data['employee_id']) && $wasteCost > 0) {
            $employee = Employee::withoutGlobalScopes()->find($data['employee_id']);
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
