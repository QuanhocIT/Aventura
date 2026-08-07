<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalRequestedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private SalaryService $salaryService,
        private InventoryService $inventoryService,
        private OrderRefundService $orderRefundService,
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

        Cache::forget("pending_approvals:{$requester->restaurant_id}");

        return $request;
    }

    /**
     * Owner phê duyệt → thực thi thao tác → thông báo requester.
     */
    public function approve(ApprovalRequest $approval, User $reviewer): void
    {
        if ($approval->operation_type === 'order_refund' && ! $reviewer->hasRole('owner')) {
            throw new \Exception('Chỉ chủ doanh nghiệp mới được duyệt yêu cầu hoàn tiền.');
        }

        DB::transaction(function () use ($approval, $reviewer) {
            // Khóa bi quan bản ghi phê duyệt để tránh chạy trùng lặp
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if ($lockedApproval->status !== 'pending') {
                throw new \Exception('Yêu cầu này đã được xử lý trước đó.');
            }

            $this->executeOperation($lockedApproval, $reviewer->id);

            $lockedApproval->update([
                'status' => 'approved',
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        Cache::forget("pending_approvals:{$approval->restaurant_id}");

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

            if ($approval->operation_type === 'shift_checkin' && ! empty($approval->operation_data['assignment_id'])) {
                $assignment = ScheduleAssignment::withoutGlobalScopes()->find($approval->operation_data['assignment_id']);
                if ($assignment && in_array($assignment->status, ['scheduled', 'pending_checkin'])) {
                    $assignment->update(['status' => 'absent']);
                }
            }
        });

        Cache::forget("pending_approvals:{$approval->restaurant_id}");

        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'rejected'));
    }

    /**
     * Thực thi thao tác đã được phê duyệt.
     */
    private function executeOperation(ApprovalRequest $approval, int $reviewerId): void
    {
        $data = $approval->operation_data;

        match ($approval->operation_type) {
            'inventory_purchase' => $this->executePurchase($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_waste' => $this->executeWaste($data, $approval->restaurant_id, $approval->requester_id),
            'salary_adjustment' => $this->executeSalaryAdjustment($data, $approval->restaurant_id),
            'shift_checkin' => $this->executeShiftCheckin($data, $approval->restaurant_id),
            'order_refund' => $this->executeOrderRefund($data, $approval->restaurant_id, $reviewerId),
            default => null,
        };
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
        }
    }

    private function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase($data, $restaurantId, $performedBy);
    }

    private function executeWaste(array $data, int $restaurantId, int $performedBy): void
    {
        $transaction = $this->inventoryService->executeWaste($data, $restaurantId, $performedBy);

        $ingredientQuery = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when(! empty($data['branch_id']), fn ($q) => $q->where('branch_id', $data['branch_id']));
        $ingredient = $ingredientQuery->findOrFail($data['ingredient_id']);
        $wasteQty = (float) $data['quantity'];
        // Khớp khoản khấu trừ (nếu có) với giá vốn thực tế của lô đã trừ.
        $wasteCost = $transaction
            ? (float) $transaction->total_cost
            : $wasteQty * (float) $ingredient->average_cost;

        if ($transaction && ! empty($data['employee_id']) && $wasteCost > 0) {
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
