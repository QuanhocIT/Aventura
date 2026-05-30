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
            $salary = Salary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('employee_id', $employee->id)
                ->where('pay_period_start', $periodStart)
                ->where('pay_period_end', $periodEnd)
                ->first();

            if ($salary) {
                // Tiếp tục quét các khoản phạt/khấu trừ mới phát sinh (Tính chất lũy tiến & Bền vững)
                $this->sweepAdjustments($salary, $employee);
                $skipped++;
                continue;
            }

            $salary = Salary::create([
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

            $this->sweepAdjustments($salary, $employee);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Tự động quét và cấn trừ các khoản phạt (Hụt két, Hao hụt kho, Vi phạm kỷ luật).
     */
    public function sweepAdjustments(Salary $salary, Employee $employee): void
    {
        $restaurantId = $salary->restaurant_id;
        $start = $salary->pay_period_start;
        $end = $salary->pay_period_end;

        // 1. Trừ lương Thu ngân (Cash shortage): Hụt két ca trực
        if ($employee->user_id) {
            $shortages = \App\Models\ShiftClosing::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('cashier_user_id', $employee->user_id)
                ->whereBetween('closing_date', [$start, $end])
                ->where('cash_difference', '<', 0)
                ->get();

            foreach ($shortages as $closing) {
                $exists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('reference_id', $closing->id)
                    ->where('reference_type', \App\Models\ShiftClosing::class)
                    ->exists();

                if (request()->hasHeader('PHPUNIT_DEBUG')) {
                    dump([
                        'exists' => $exists,
                        'employee_id' => $employee->id,
                        'closing_id' => $closing->id,
                        'salary_id' => $salary->id,
                    ]);
                }

                if (!$exists) {
                    SalaryAdjustment::create([
                        'salary_id'      => $salary->id,
                        'restaurant_id'  => $restaurantId,
                        'employee_id'    => $employee->id,
                        'type'           => 'cash_shortage',
                        'amount'         => abs((float) $closing->cash_difference),
                        'reason'         => "Khấu trừ hụt két ca trực ngày " . $closing->closing_date,
                        'reference_id'   => $closing->id,
                        'reference_type' => \App\Models\ShiftClosing::class,
                    ]);
                }
            }
        }

        // 2. Trừ lương Bếp/Pha chế (Inventory loss): Thất thoát kho cấn trừ theo mốc thời gian ca trực
        $wasteTransactions = \App\Models\InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->where('total_cost', '>', 0)
            ->get();

        foreach ($wasteTransactions as $transaction) {
            $scheduledEmployee = \App\Models\ScheduleAssignment::findEmployeeOnShiftAt($transaction->occurred_at, $restaurantId);
            if ($scheduledEmployee && $scheduledEmployee->id === $employee->id) {
                $exists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('reference_id', $transaction->id)
                    ->where('reference_type', \App\Models\InventoryTransaction::class)
                    ->exists();

                if (!$exists) {
                    SalaryAdjustment::create([
                        'salary_id'      => $salary->id,
                        'restaurant_id'  => $restaurantId,
                        'employee_id'    => $employee->id,
                        'type'           => 'inventory_loss',
                        'amount'         => (float) $transaction->total_cost,
                        'reason'         => "Phạt hao hụt nguyên liệu: " . ($transaction->notes ?: "Thất thoát nguyên liệu ca trực"),
                        'reference_id'   => $transaction->id,
                        'reference_type' => \App\Models\InventoryTransaction::class,
                    ]);
                }
            }
        }

        // 3. Trừ lương Vi phạm kỷ luật (Violations): Biên bản xử phạt vi phạm đi trễ, kỷ luật đã duyệt
        $violations = \App\Models\ViolationReport::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('employee_id', $employee->id)
            ->whereBetween('occurred_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->where('penalty_amount', '>', 0)
            ->where('status', '!=', 'dismissed')
            ->get();

        foreach ($violations as $violation) {
            $exists = SalaryAdjustment::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('reference_id', $violation->id)
                ->where('reference_type', \App\Models\ViolationReport::class)
                ->exists();

            if (!$exists) {
                SalaryAdjustment::create([
                    'salary_id'      => $salary->id,
                    'restaurant_id'  => $restaurantId,
                    'employee_id'    => $employee->id,
                    'type'           => 'violation',
                    'amount'         => (float) $violation->penalty_amount,
                    'reason'         => "Khấu trừ vi phạm: " . $violation->violation_type . " (" . $violation->description . ")",
                    'reference_id'   => $violation->id,
                    'reference_type' => \App\Models\ViolationReport::class,
                ]);
            }
        }

        // 4. Tính toán lại tổng các khoản và cập nhật net_salary thực lãnh
        $this->recalculate($salary);
    }
}
