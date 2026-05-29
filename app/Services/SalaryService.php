<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use Carbon\Carbon;

class SalaryService
{
    /**
     * Tìm hoặc tạo bản nháp lương cho nhân viên trong tháng chứa $date.
     */
    public function getOrCreateDraft(int $restaurantId, Employee $employee, string $date): Salary
    {
        $periodStart = Carbon::parse($date)->startOfMonth()->toDateString();
        $periodEnd   = Carbon::parse($date)->endOfMonth()->toDateString();

        return Salary::withoutGlobalScopes()->firstOrCreate(
            [
                'restaurant_id'    => $restaurantId,
                'employee_id'      => $employee->id,
                'pay_period_start' => $periodStart,
                'pay_period_end'   => $periodEnd,
            ],
            [
                'base_salary'      => $employee->base_salary ?? 0,
                'bonus_amount'     => 0,
                'deduction_amount' => 0,
                'net_salary'       => $employee->base_salary ?? 0,
                'status'           => 'draft',
            ]
        );
    }

    /**
     * Thêm điều chỉnh lương (bonus hoặc khấu trừ) rồi tính lại net_salary.
     */
    public function addAdjustment(Salary $salary, array $data): SalaryAdjustment
    {
        $adjustment = SalaryAdjustment::create(array_merge($data, [
            'salary_id'     => $salary->id,
            'restaurant_id' => $salary->restaurant_id,
        ]));

        $this->recalculate($salary);

        return $adjustment;
    }

    /**
     * Tính lại bonus_amount, deduction_amount và net_salary từ tất cả adjustments.
     * net_salary không được âm (min = 0).
     */
    public function recalculate(Salary $salary): void
    {
        $adjustments = SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->get();

        $bonuses    = (float) $adjustments->where('type', 'bonus')->sum('amount');
        $deductions = (float) $adjustments
            ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation'])
            ->sum('amount');

        $salary->update([
            'bonus_amount'     => $bonuses,
            'deduction_amount' => $deductions,
            'net_salary'       => max(0, (float) $salary->base_salary + $bonuses - $deductions),
        ]);
    }

    /**
     * Tạo bản nháp lương cho tất cả nhân viên active của nhà hàng trong tháng.
     * Dùng cho nút "Tạo bảng lương" trên trang salaries.
     */
    public function generateMonthlyDrafts(int $restaurantId, string $yearMonth): array
    {
        $periodStart = Carbon::parse($yearMonth . '-01')->startOfMonth()->toDateString();
        $periodEnd   = Carbon::parse($yearMonth . '-01')->endOfMonth()->toDateString();

        $employees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $exists = Salary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('employee_id', $employee->id)
                ->where('pay_period_start', $periodStart)
                ->where('pay_period_end', $periodEnd)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Salary::create([
                'restaurant_id'    => $restaurantId,
                'employee_id'      => $employee->id,
                'pay_period_start' => $periodStart,
                'pay_period_end'   => $periodEnd,
                'base_salary'      => $employee->base_salary ?? 0,
                'bonus_amount'     => 0,
                'deduction_amount' => 0,
                'net_salary'       => $employee->base_salary ?? 0,
                'status'           => 'draft',
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
