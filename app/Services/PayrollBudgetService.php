<?php

namespace App\Services;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
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
        return Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->get()
            ->sum(fn (Employee $e) => $this->monthlyWageOf($e));
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
