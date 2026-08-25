<?php

namespace App\Services;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
use App\Models\RestaurantBranch;
use App\Models\WageTier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Kiểm soát quỹ lương theo chi nhánh: quy đổi lương nhân viên về THÁNG, tính tổng đã
 * cam kết và so với quỹ do Chủ đặt. Dùng khi Quản lý tạo/duyệt nhân viên để chặn
 * vượt quỹ.
 */
class PayrollBudgetService
{
    /** Lương tháng (quy đổi) của một nhân viên theo hình thức trả công. */
    public function monthlyWageOf(Employee $employee): float
    {
        $rate = (float) ($employee->pay_rate ?: $employee->base_salary);

        return match ($employee->compensation_type) {
            'hourly' => $rate * WageTier::HOURS_PER_MONTH,
            'shift' => $rate * WageTier::SHIFTS_PER_MONTH,
            default => (float) ($employee->base_salary ?: $rate), // monthly
        };
    }

    /** Quy đổi một mức lương (hình thức + rate/base) về lương THÁNG. */
    public function estimateMonthly(string $compensationType, float $payRate, float $baseSalary): float
    {
        return match ($compensationType) {
            'hourly' => $payRate * WageTier::HOURS_PER_MONTH,
            'shift' => $payRate * WageTier::SHIFTS_PER_MONTH,
            default => $baseSalary ?: $payRate, // fixed
        };
    }

    /** Tổng lương tháng đã cam kết cho nhân viên đang hoạt động của một chi nhánh. */
    public function committedMonthlyWages(int $restaurantId, ?int $branchId): float
    {
        if (! $branchId) {
            return 0.0;
        }

        $branch = RestaurantBranch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->find($branchId);

        return Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId, $branch) {
                $query->where('branch_id', $branchId);

                // Legacy Kho Tổng staff may have only users.warehouse_branch_id
                // populated. Count them in the central warehouse budget too.
                if ($branch?->is_central_warehouse || $branch?->warehouse_type === 'central') {
                    $query->orWhereExists(function ($userQuery) use ($branchId) {
                        $userQuery->select('users.id')
                            ->from('users')
                            ->whereColumn('users.id', 'employees.user_id')
                            ->where('users.warehouse_branch_id', $branchId);
                    });
                }
            })
            ->where('status', 'active')
            ->get()
            ->sum(fn (Employee $e) => $this->monthlyWageOf($e));
    }

    public function summary(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): array
    {
        $budget = $this->budgetFor($restaurantId, $branchId, $month);
        $committed = $this->committedMonthlyWages($restaurantId, $branchId);
        $amount = $budget ? (float) $budget->budget_amount : null;
        $remaining = $amount === null ? null : $amount - $committed;

        return [
            'branch_id' => $branchId,
            'branch_name' => $budget?->branch?->name
                ?? ($branchId ? RestaurantBranch::withoutGlobalScopes()->find($branchId)?->name : null),
            'month' => ($month ?? Carbon::now())->copy()->startOfMonth()->format('m/Y'),
            'budget_amount' => $amount,
            'committed' => $committed,
            'remaining' => $remaining,
            'over_budget' => $remaining !== null && $remaining < -0.01,
            'configured' => $budget !== null,
        ];
    }

    public function budgetFor(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): ?BranchPayrollBudget
    {
        if (! $branchId) {
            return null;
        }
        $month = ($month ?? Carbon::now())->copy()->startOfMonth();

        return BranchPayrollBudget::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereDate('effective_month', $month->toDateString())
            ->first();
    }

    /** Quỹ còn lại (budget − đã cam kết). Null nếu chưa đặt quỹ (không ràng buộc). */
    public function remaining(int $restaurantId, ?int $branchId, ?CarbonInterface $month = null): ?float
    {
        $budget = $this->budgetFor($restaurantId, $branchId, $month);
        if (! $budget) {
            return null;
        }

        return (float) $budget->budget_amount - $this->committedMonthlyWages($restaurantId, $branchId);
    }

    /**
     * Thêm một mức lương tháng nữa có vượt quỹ không?
     * Chưa đặt quỹ → luôn cho phép (chưa ràng buộc). Có quỹ → phải còn đủ chỗ.
     */
    public function canFit(int $restaurantId, ?int $branchId, float $additionalMonthlyWage, ?CarbonInterface $month = null): bool
    {
        $remaining = $this->remaining($restaurantId, $branchId, $month);
        if ($remaining === null) {
            return true;
        }

        return $additionalMonthlyWage <= $remaining + 0.01;
    }
}
