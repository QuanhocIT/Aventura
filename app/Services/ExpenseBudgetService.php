<?php

namespace App\Services;

use App\Models\BranchExpenseBudget;
use App\Models\OperatingExpense;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Kiểm soát hạn mức chi tiêu theo chi nhánh: tính tổng đã chi trong tháng và so với
 * hạn mức do Chủ đặt. Dùng khi Quản lý ghi chi phí để chặn vượt hạn mức.
 */
class ExpenseBudgetService
{
    /** Tổng chi phí đã ghi của một chi nhánh trong tháng. */
    public function committedThisMonth(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): float
    {
        $month = ($month ?? Carbon::now())->copy();

        return (float) OperatingExpense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereBetween('expense_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            // Draft requests reserve budget; rejected requests must release it.
            ->whereIn('status', ['draft', 'approved', 'paid'])
            ->sum('amount');
    }

    public function budgetFor(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): ?BranchExpenseBudget
    {
        if (! $branchId) {
            return null;
        }
        $month = ($month ?? Carbon::now())->copy()->startOfMonth();

        return BranchExpenseBudget::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereDate('effective_month', $month->toDateString())
            ->first();
    }

    /** Hạn mức còn lại (budget − đã chi). Null nếu chưa đặt hạn mức. */
    public function remaining(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): ?float
    {
        $budget = $this->budgetFor($restaurantId, $branchId, $month);
        if (! $budget) {
            return null;
        }

        return (float) $budget->budget_amount - $this->committedThisMonth($restaurantId, $branchId, $month);
    }

    /**
     * Ghi thêm một khoản chi có vượt hạn mức không?
     * Chưa đặt hạn mức → luôn cho phép. Có hạn mức → phải còn đủ chỗ.
     */
    public function canFit(int $restaurantId, ?int $branchId, float $additionalAmount, ?CarbonInterface $month = null): bool
    {
        $remaining = $this->remaining($restaurantId, $branchId, $month);
        if ($remaining === null) {
            return true;
        }

        return $additionalAmount <= $remaining + 0.01;
    }
}
