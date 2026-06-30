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
        $periodStart = Carbon::parse($date)->startOfMonth();
        $periodEnd   = Carbon::parse($date)->endOfMonth();

        return Salary::withoutGlobalScopes()->firstOrCreate(
            [
                'restaurant_id'    => $restaurantId,
                'employee_id'      => $employee->id,
                'pay_period_start' => $periodStart,
                'pay_period_end'   => $periodEnd,
            ],
            [
                'base_salary'      => $this->calculateDynamicBaseSalary($employee, $periodStart->toDateString(), $periodEnd->toDateString()),
                'bonus_amount'     => 0,
                'deduction_amount' => 0,
                'net_salary'       => 0, // recalculate will handle it
                'status'           => 'draft',
            ]
        );
    }

    /**
     * Tính toán lương gốc động dựa trên hình thức trả lương (compensation_type).
     */
    public function calculateDynamicBaseSalary(Employee $employee, string $start, string $end): float
    {
        $compType = $employee->compensation_type ?? 'fixed';
        $payRate = (float) ($employee->pay_rate ?? 0);

        if ($compType === 'hourly') {
            // Lấy toàn bộ các ca chấm công completed của nhân viên trong chu kỳ
            $assignments = \App\Models\ScheduleAssignment::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$start, $end])
                ->where('status', 'completed')
                ->whereNotNull('check_in_at')
                ->whereNotNull('check_out_at')
                ->get();





            $restaurant = \App\Models\Restaurant::find($employee->restaurant_id);
            $graceMinutes = $restaurant?->grace_period_minutes ?? 10;
            $otMultiplier = (float) ($restaurant?->ot_multiplier ?? 1.50);

            $totalWages = 0.0;
            foreach ($assignments as $assignment) {
                $shift = $assignment->shift;
                if (!$shift) {
                    $checkIn = Carbon::parse($assignment->check_in_at);
                    $checkOut = Carbon::parse($assignment->check_out_at);
                    $hours = $checkIn->diffInSeconds($checkOut) / 3600.0;
                    $totalWages += $hours * $payRate;
                    continue;
                }

                $dateStr = $assignment->scheduled_date instanceof Carbon 
                    ? $assignment->scheduled_date->toDateString() 
                    : Carbon::parse($assignment->scheduled_date)->toDateString();
                
                $schedStart = Carbon::parse($dateStr . ' ' . $shift->start_time);
                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $schedEnd = Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
                } else {
                    $schedEnd = Carbon::parse($dateStr . ' ' . $shift->end_time);
                }

                $actualCheckIn = Carbon::parse($assignment->check_in_at);
                $actualCheckOut = Carbon::parse($assignment->check_out_at);

                // Apply Grace Period to Check-In
                $graceStartThreshold = $schedStart->copy()->addMinutes($graceMinutes);
                if ($actualCheckIn->greaterThan($schedStart) && $actualCheckIn->lessThanOrEqualTo($graceStartThreshold)) {
                    $workedCheckIn = $schedStart->copy();
                } else {
                    $workedCheckIn = $actualCheckIn->copy();
                }

                // Apply Grace Period to Check-Out
                $graceEndThreshold = $schedEnd->copy()->subMinutes($graceMinutes);
                if ($actualCheckOut->lessThan($schedEnd) && $actualCheckOut->greaterThanOrEqualTo($graceEndThreshold)) {
                    $workedCheckOut = $schedEnd->copy();
                } else {
                    $workedCheckOut = $actualCheckOut->copy();
                }

                if ($workedCheckOut->lessThanOrEqualTo($workedCheckIn)) {
                    continue;
                }

                $regularDurationSeconds = 0.0;
                $otDurationSeconds = 0.0;

                // Duration within scheduled shift
                $intersectStart = $workedCheckIn->greaterThan($schedStart) ? $workedCheckIn : $schedStart;
                $intersectEnd = $workedCheckOut->lessThan($schedEnd) ? $workedCheckOut : $schedEnd;

                if ($intersectEnd->greaterThan($intersectStart)) {
                    $regularDurationSeconds = $intersectStart->diffInSeconds($intersectEnd);
                }

                // OT after scheduled end
                if ($workedCheckOut->greaterThan($schedEnd)) {
                    $otStart = $workedCheckIn->greaterThan($schedEnd) ? $workedCheckIn : $schedEnd;
                    if ($workedCheckOut->greaterThan($otStart)) {
                        $otDurationSeconds += $otStart->diffInSeconds($workedCheckOut);
                    }
                }

                // OT before scheduled start
                if ($workedCheckIn->lessThan($schedStart)) {
                    $otEnd = $workedCheckOut->lessThan($schedStart) ? $workedCheckOut : $schedStart;
                    if ($otEnd->greaterThan($workedCheckIn)) {
                        $otDurationSeconds += $workedCheckIn->diffInSeconds($otEnd);
                    }
                }

                $otRequest = \App\Models\OvertimeRequest::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->whereDate('scheduled_date', $dateStr)
                    ->where('status', 'approved')
                    ->first();

                $approvedOtHours = $otRequest ? (float) $otRequest->hours_approved : 0.0;
                $approvedOtSeconds = $approvedOtHours * 3600.0;

                $paidOtSeconds = min($otDurationSeconds, $approvedOtSeconds);
                $unapprovedOtSeconds = max(0.0, $otDurationSeconds - $paidOtSeconds);

                $regularHours = ($regularDurationSeconds + $unapprovedOtSeconds) / 3600.0;
                $otHours = $paidOtSeconds / 3600.0;

                $totalWages += ($regularHours * $payRate) + ($otHours * $payRate * $otMultiplier);
            }

            return round($totalWages, 2);
        }

        if ($compType === 'shift') {
            // Tính số ca làm hoàn thành trong chu kỳ
            $completedShiftsCount = \App\Models\ScheduleAssignment::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$start, $end])
                ->where('status', 'completed')
                ->count();

            return (float) ($completedShiftsCount * $payRate);
        }

        // Mặc định là fixed (lương cứng)
        return (float) ($employee->base_salary ?? 0);
    }

    /**
     * Tính tiền lương tích lũy từ đầu tháng đến ngày hiện tại (hoặc ngày chỉ định).
     */
    public function calculateEarnedWagesForMonth(Employee $employee, string $date): float
    {
        $carbonDate = Carbon::parse($date);
        $start = $carbonDate->copy()->startOfMonth()->toDateString();
        $end = $carbonDate->toDateString();

        $compType = $employee->compensation_type ?? 'fixed';
        if ($compType === 'fixed') {
            $daysInMonth = $carbonDate->daysInMonth;
            $elapsedDays = $carbonDate->day;
            $baseSalary = (float) ($employee->base_salary ?? 0);
            return round(($baseSalary / $daysInMonth) * $elapsedDays, 2);
        } else {
            return $this->calculateDynamicBaseSalary($employee, $start, $end);
        }
    }

    /**
     * Thêm điều chỉnh lương (bonus hoặc khấu trừ) rồi tính lại net_salary.
     */
    public function addAdjustment(Salary $salary, array $data): SalaryAdjustment
    {
        $adjustment = SalaryAdjustment::create(array_merge($data, [
            'salary_id'     => $salary->id,
            'restaurant_id' => $salary->restaurant_id,
            'status'        => $data['status'] ?? 'applied',
        ]));

        $this->recalculate($salary);

        return $adjustment;
    }

    /**
     * Tính lại bonus_amount, deduction_amount và net_salary từ tất cả adjustments.
     * Chỉ cấn trừ các adjustments có trạng thái 'applied' (không trừ 'disputed' hay 'waived').
     * net_salary không được âm (min = 0).
     */
    public function recalculate(Salary $salary): void
    {
        $adjustments = SalaryAdjustment::withoutGlobalScopes()
            ->where('salary_id', $salary->id)
            ->get();

        $bonuses = (float) $adjustments->where('type', 'bonus')->sum('amount');
        
        // Chỉ tính khấu trừ từ các adjustments có trạng thái 'applied'
        $deductions = (float) $adjustments
            ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation', 'advance'])
            ->where('status', 'applied')
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
        $periodStart = Carbon::parse($yearMonth . '-01')->startOfMonth();
        $periodEnd   = Carbon::parse($yearMonth . '-01')->endOfMonth();

        $employees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $baseSalary = $this->calculateDynamicBaseSalary($employee, $periodStart->toDateString(), $periodEnd->toDateString());

            $salary = Salary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('employee_id', $employee->id)
                ->where('pay_period_start', $periodStart)
                ->where('pay_period_end', $periodEnd)
                ->first();

            if ($salary) {
                $salary->update(['base_salary' => $baseSalary]);
                $this->sweepAdjustments($salary, $employee);
                $skipped++;
                continue;
            }

            $salary = Salary::create([
                'restaurant_id'    => $restaurantId,
                'employee_id'      => $employee->id,
                'pay_period_start' => $periodStart,
                'pay_period_end'   => $periodEnd,
                'base_salary'      => $baseSalary,
                'bonus_amount'     => 0,
                'deduction_amount' => 0,
                'net_salary'       => $baseSalary,
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
        $start = $salary->pay_period_start->toDateString();
        $end = $salary->pay_period_end->toDateString();

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
                        'status'         => 'applied',
                    ]);
                }
            }
        }

        // 2. Trừ lương Bếp/Pha chế (Inventory loss): Thất thoát kho cấn trừ theo mốc thời gian ca trực có ngưỡng hao hụt
        $wasteTransactions = \App\Models\InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->where('total_cost', '>', 0)
            ->get();

        foreach ($wasteTransactions as $transaction) {
            $activeAssignments = \App\Models\ScheduleAssignment::findEmployeesOnShiftAt($transaction->occurred_at, $restaurantId);
            if ($activeAssignments->isEmpty()) {
                continue;
            }

            $ingredient = \App\Models\Ingredient::withoutGlobalScopes()->find($transaction->ingredient_id);
            if (!$ingredient) {
                continue;
            }

            $cat = mb_strtolower($ingredient->category_name ?? '');
            $isBeverage = false;
            if (str_contains($cat, 'uống') || str_contains($cat, 'nước') || str_contains($cat, 'bar') || str_contains($cat, 'bia') || str_contains($cat, 'rượu') || str_contains($cat, 'drink') || str_contains($cat, 'beverage') || str_contains($cat, 'pha chế')) {
                $isBeverage = true;
            }

            $filteredAssignments = $activeAssignments->filter(function ($a) use ($isBeverage) {
                $emp = $a->employee;
                if (!$emp) return false;

                $jobTitle = mb_strtolower($emp->job_title ?? '');
                $roleName = mb_strtolower($emp->role?->name ?? '');

                if ($isBeverage) {
                    return str_contains($jobTitle, 'bar') || str_contains($jobTitle, 'pha chế') || str_contains($jobTitle, 'bartender') || str_contains($roleName, 'bartender');
                } else {
                    return str_contains($jobTitle, 'bếp') || str_contains($jobTitle, 'chef') || str_contains($jobTitle, 'cook') || str_contains($jobTitle, 'kitchen') || str_contains($roleName, 'kitchen');
                }
            });

            if ($filteredAssignments->isEmpty()) {
                continue;
            }

            $myAssignment = $filteredAssignments->first(fn($a) => $a->employee_id === $employee->id);
            if (!$myAssignment) {
                continue;
            }

            $leaders = $filteredAssignments->filter(fn($a) => $a->is_shift_leader);
            $shareCount = 1;
            $isResponsible = false;

            if ($leaders->isNotEmpty()) {
                if ($myAssignment->is_shift_leader) {
                    $isResponsible = true;
                    $shareCount = $leaders->count();
                }
            } else {
                $isResponsible = true;
                $shareCount = $filteredAssignments->count();
            }

            if ($isResponsible) {
                $exists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('reference_id', $transaction->id)
                    ->where('reference_type', \App\Models\InventoryTransaction::class)
                    ->exists();

                if (!$exists) {
                    $ingredient = \App\Models\Ingredient::withoutGlobalScopes()->find($transaction->ingredient_id);
                    $allowedRatio = $ingredient ? (float) ($ingredient->allowed_waste_ratio ?? 0) : 0;
                    
                    $totalPenalty = (float) $transaction->total_cost * (1 - $allowedRatio / 100);
                    $totalPenalty = max(0.0, $totalPenalty);
                    
                    $penaltyAmount = $totalPenalty / $shareCount;

                    if ($penaltyAmount > 0) {
                        SalaryAdjustment::create([
                            'salary_id'      => $salary->id,
                            'restaurant_id'  => $restaurantId,
                            'employee_id'    => $employee->id,
                            'type'           => 'inventory_loss',
                            'amount'         => $penaltyAmount,
                            'reason'         => "Phạt hao hụt nguyên liệu: " . ($transaction->notes ?: "Thất thoát nguyên liệu ca trực") . " (Đã khấu trừ " . $allowedRatio . "% định mức, chia sẻ cho " . $shareCount . " người chịu trách nhiệm)",
                            'reference_id'   => $transaction->id,
                            'reference_type' => \App\Models\InventoryTransaction::class,
                            'status'         => 'applied',
                        ]);
                    }
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
                    'status'         => 'applied',
                ]);
            }
        }

        // 4. Cộng thưởng & Hoa hồng KPI từ đánh giá hiệu suất đã duyệt (finalized)
        $period = Carbon::parse($salary->pay_period_start)->format('Y-m');
        $kpi = \App\Models\EmployeeKpi::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->where('status', 'finalized')
            ->first();

        if ($kpi) {
            // Hoa hồng doanh số KPI
            if ((float)$kpi->total_commission > 0) {
                $commissionExists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('salary_id', $salary->id)
                    ->where('employee_id', $employee->id)
                    ->where('type', 'bonus')
                    ->where('reference_id', $kpi->id)
                    ->where('reference_type', \App\Models\EmployeeKpi::class)
                    ->where('reason', 'like', 'Thưởng hoa hồng%')
                    ->exists();

                if (!$commissionExists) {
                    SalaryAdjustment::create([
                        'salary_id'      => $salary->id,
                        'restaurant_id'  => $restaurantId,
                        'employee_id'    => $employee->id,
                        'type'           => 'bonus',
                        'amount'         => (float)$kpi->total_commission,
                        'reason'         => "Thưởng hoa hồng doanh số theo hiệu suất KPI kỳ " . $kpi->period,
                        'reference_id'   => $kpi->id,
                        'reference_type' => \App\Models\EmployeeKpi::class,
                        'status'         => 'applied',
                    ]);
                }
            }

            // Thưởng vượt chỉ tiêu KPI
            if ((float)$kpi->total_bonus > 0) {
                $bonusExists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('salary_id', $salary->id)
                    ->where('employee_id', $employee->id)
                    ->where('type', 'bonus')
                    ->where('reference_id', $kpi->id)
                    ->where('reference_type', \App\Models\EmployeeKpi::class)
                    ->where('reason', 'like', 'Thưởng vượt chỉ tiêu%')
                    ->exists();

                if (!$bonusExists) {
                    SalaryAdjustment::create([
                        'salary_id'      => $salary->id,
                        'restaurant_id'  => $restaurantId,
                        'employee_id'    => $employee->id,
                        'type'           => 'bonus',
                        'amount'         => (float)$kpi->total_bonus,
                        'reason'         => "Thưởng vượt chỉ tiêu KPI kỳ " . $kpi->period,
                        'reference_id'   => $kpi->id,
                        'reference_type' => \App\Models\EmployeeKpi::class,
                        'status'         => 'applied',
                    ]);
                }
            }
        }

        // 5. Tính toán lại tổng các khoản và cập nhật net_salary thực lãnh
        $this->recalculate($salary);
    }
}
