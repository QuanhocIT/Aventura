<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\ViolationReport;
use App\Notifications\SalaryReadyNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    /**
     * Tìm hoặc tạo bản nháp lương cho nhân viên trong tháng chứa $date.
     */
    public function getOrCreateDraft(int $restaurantId, Employee $employee, string $date): Salary
    {
        $periodStart = Carbon::parse($date)->startOfMonth()->toDateString();
        $periodEnd = Carbon::parse($date)->endOfMonth()->toDateString();

        $salary = Salary::withoutGlobalScopes()->firstOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'employee_id' => $employee->id,
                'pay_period_start' => $periodStart,
                'pay_period_end' => $periodEnd,
            ],
            [
                'branch_id' => $employee->branch_id,
                'base_salary' => $this->calculateDynamicBaseSalary($employee, $periodStart, $periodEnd),
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_salary' => 0, // recalculate will handle it
                'status' => 'draft',
            ]
        );

        if ($salary->branch_id === null && $employee->branch_id !== null) {
            $salary->update(['branch_id' => $employee->branch_id]);
        }

        return $salary;
    }

    /**
     * Tính toán lương gốc động dựa trên hình thức trả lương (compensation_type).
     */
    /**
     * Tính toán lương gốc động dựa trên hình thức trả lương (compensation_type).
     */
    public function calculateDynamicBaseSalary(Employee $employee, string $start, string $end, ?array $context = null): float
    {
        $compType = $employee->compensation_type ?? 'fixed';
        if ($compType === 'hourly') {
            return $this->hourlyCalculation($employee, $start, $end, $context)['total_wages'];
        }

        if ($compType === 'shift') {
            return $this->shiftCalculation($employee, $start, $end, $context)['total_wages'];
        }

        // Lương tháng cố định được trả đủ theo hợp đồng. Việc vắng/đi muộn
        // phải có một loại điều chỉnh được duyệt riêng, không tự suy đoán từ
        // việc thiếu log chấm công.
        return (float) ($employee->base_salary ?? 0);
    }

    /** @return array{total_wages: float, regular_hours: float, ot_hours: float, unapproved_ot_hours: float, actual_work_days: int, completed_shifts_count: int} */
    private function hourlyCalculation(Employee $employee, string $start, string $end, ?array $context = null): array
    {
        $context ??= [];
        $assignments = $this->periodAssignments($employee, $start, $end, $context, true);
        $otRequests = $this->periodOvertimeRequests($employee, $start, $end, $context)
            ->groupBy(fn ($request) => Carbon::parse($request->scheduled_date)->toDateString());
        $restaurant = $context['restaurant'] ?? Restaurant::find($employee->restaurant_id);
        $graceMinutes = $restaurant?->grace_period_minutes ?? 10;
        $otMultiplier = (float) ($restaurant?->ot_multiplier ?? 1.50);
        $payRate = (float) ($employee->pay_rate ?? 0);
        $regularSeconds = 0.0;
        $paidOtSeconds = 0.0;
        $unapprovedOtSeconds = 0.0;
        $workDates = [];

        foreach ($assignments as $assignment) {
            $dateStr = Carbon::parse($assignment->scheduled_date)->toDateString();
            $workDates[$dateStr] = true;
            $shift = $assignment->shift;

            if (! $shift) {
                $regularSeconds += Carbon::parse($assignment->check_in_at)->diffInSeconds(Carbon::parse($assignment->check_out_at));
                continue;
            }

            $schedStart = Carbon::parse($dateStr.' '.$shift->start_time);
            $schedEnd = Carbon::parse($dateStr.' '.$shift->end_time);
            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $schedEnd->addDay();
            }

            $actualCheckIn = Carbon::parse($assignment->check_in_at);
            $actualCheckOut = Carbon::parse($assignment->check_out_at);
            $graceStart = $schedStart->copy()->addMinutes($graceMinutes);
            $graceEnd = $schedEnd->copy()->subMinutes($graceMinutes);
            $workedCheckIn = $actualCheckIn->greaterThan($schedStart) && $actualCheckIn->lessThanOrEqualTo($graceStart)
                ? $schedStart->copy() : $actualCheckIn->copy();
            $workedCheckOut = $actualCheckOut->lessThan($schedEnd) && $actualCheckOut->greaterThanOrEqualTo($graceEnd)
                ? $schedEnd->copy() : $actualCheckOut->copy();

            if ($workedCheckOut->lessThanOrEqualTo($workedCheckIn)) {
                continue;
            }

            $regularDuration = 0.0;
            $otDuration = 0.0;
            $intersectStart = $workedCheckIn->greaterThan($schedStart) ? $workedCheckIn : $schedStart;
            $intersectEnd = $workedCheckOut->lessThan($schedEnd) ? $workedCheckOut : $schedEnd;
            if ($intersectEnd->greaterThan($intersectStart)) {
                $regularDuration = $intersectStart->diffInSeconds($intersectEnd);
            }
            if ($workedCheckOut->greaterThan($schedEnd)) {
                $otStart = $workedCheckIn->greaterThan($schedEnd) ? $workedCheckIn : $schedEnd;
                if ($workedCheckOut->greaterThan($otStart)) {
                    $otDuration += $otStart->diffInSeconds($workedCheckOut);
                }
            }
            if ($workedCheckIn->lessThan($schedStart)) {
                $otEnd = $workedCheckOut->lessThan($schedStart) ? $workedCheckOut : $schedStart;
                if ($otEnd->greaterThan($workedCheckIn)) {
                    $otDuration += $workedCheckIn->diffInSeconds($otEnd);
                }
            }

            $approvedOtHours = (float) ($otRequests->get($dateStr)?->sum('hours_approved') ?? 0);
            $approvedOtSeconds = max(0.0, $approvedOtHours * 3600);
            $paidOtSeconds += min($otDuration, $approvedOtSeconds);
            $unapprovedOtSeconds += max(0.0, $otDuration - $approvedOtSeconds);
            $regularSeconds += $regularDuration;
        }

        $regularHours = $regularSeconds / 3600;
        $otHours = $paidOtSeconds / 3600;

        return [
            'total_wages' => round(($regularHours * $payRate) + ($otHours * $payRate * $otMultiplier), 2),
            'regular_hours' => round($regularHours, 2),
            'ot_hours' => round($otHours, 2),
            'unapproved_ot_hours' => round($unapprovedOtSeconds / 3600, 2),
            'actual_work_days' => count($workDates),
            'completed_shifts_count' => $assignments->count(),
        ];
    }

    /** @return array{total_wages: float, actual_work_days: int, completed_shifts_count: int} */
    private function shiftCalculation(Employee $employee, string $start, string $end, ?array $context = null): array
    {
        $assignments = $this->periodAssignments($employee, $start, $end, $context ?? [], false);
        $dates = $assignments->map(fn ($assignment) => Carbon::parse($assignment->scheduled_date)->toDateString())->unique();
        $payRate = (float) ($employee->pay_rate ?? 0);

        return [
            'total_wages' => (float) ($assignments->count() * $payRate),
            'actual_work_days' => $dates->count(),
            'completed_shifts_count' => $assignments->count(),
        ];
    }

    private function periodAssignments(Employee $employee, string $start, string $end, array $context, bool $requiresPunch): Collection
    {
        if (isset($context['assignments'])) {
            return $context['assignments']
                ->where('employee_id', $employee->id)
                ->where('status', 'completed')
                ->filter(function ($assignment) use ($start, $end, $requiresPunch) {
                    $date = Carbon::parse($assignment->scheduled_date)->toDateString();

                    return $date >= $start && $date <= $end
                        && (! $requiresPunch || ($assignment->check_in_at && $assignment->check_out_at));
                });
        }

        $query = ScheduleAssignment::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->where('status', 'completed')
            ->when($requiresPunch, fn ($q) => $q->whereNotNull('check_in_at')->whereNotNull('check_out_at'))
            ->with('shift');

        return $query->get();
    }

    private function periodOvertimeRequests(Employee $employee, string $start, string $end, array $context): Collection
    {
        if (isset($context['ot_requests'])) {
            return $context['ot_requests']
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->filter(fn ($request) => ($date = Carbon::parse($request->scheduled_date)->toDateString()) >= $start && $date <= $end);
        }

        return OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$start, $end])
            ->where('status', 'approved')
            ->get();
    }

    /** @return array{paid: int, unpaid: int} */
    private function leaveDayCounts(Employee $employee, string $start, string $end): array
    {
        $requests = LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get(['start_date', 'end_date', 'leave_type']);
        $counts = ['paid' => 0, 'unpaid' => 0];

        foreach ($requests as $request) {
            $from = Carbon::parse($request->start_date)->max(Carbon::parse($start));
            $to = Carbon::parse($request->end_date)->min(Carbon::parse($end));
            if ($from->greaterThan($to)) {
                continue;
            }

            $key = $request->leave_type === 'unpaid' ? 'unpaid' : 'paid';
            $counts[$key] += $from->diffInDays($to) + 1;
        }

        return $counts;
    }

    /**
     * Tính tiền lương tích lũy từ đầu tháng đến ngày hiện tại (hoặc ngày chỉ định).
     */
    public function calculateEarnedWagesForMonth(Employee $employee, string $date, ?array $context = null): float
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
            return $this->calculateDynamicBaseSalary($employee, $start, $end, $context);
        }
    }

    /**
     * Thêm điều chỉnh lương (bonus hoặc khấu trừ) rồi tính lại net_salary.
     * Bọc trong transaction để đảm bảo Atomicity: hoặc cả 2 (create + recalculate) thành công,
     * hoặc không có gì được lưu.
     */
    public function addAdjustment(Salary $salary, array $data): SalaryAdjustment
    {
        return DB::transaction(function () use ($salary, $data) {
            $adjustment = SalaryAdjustment::create(array_merge($data, [
                'salary_id' => $salary->id,
                'restaurant_id' => $salary->restaurant_id,
                'status' => $data['status'] ?? 'applied',
            ]));

            if (($data['type'] ?? null) === 'advance' && ($data['status'] ?? 'applied') === 'applied') {
                $amount = (float) $data['amount'];
                if ($amount > 0) {
                    $method = $data['payment_method'] ?? 'cash';
                    $idempotencyKey = 'salary-advance:'.$adjustment->id;
                    if ($method === 'cash') {
                        app(CashPostingService::class)->record([
                            'restaurant_id' => $salary->restaurant_id,
                            'branch_id' => $salary->branch_id,
                            'type' => 'out',
                            'amount' => $amount,
                            'source' => 'payroll',
                            'idempotency_key' => $idempotencyKey,
                            'debit_account' => '3341',
                            'credit_account' => '1111',
                            'journal_source_type' => SalaryAdjustment::class,
                            'journal_source_id' => $adjustment->id,
                            'notes' => 'Tạm ứng lương nhân viên #'.$salary->employee_id,
                            'created_by' => $data['created_by'] ?? null,
                            'occurred_at' => now(),
                        ]);
                    } else {
                        app(FinancialPostingService::class)->post([
                            'restaurant_id' => $salary->restaurant_id,
                            'branch_id' => $salary->branch_id,
                            'entry_date' => today(),
                            'source_type' => SalaryAdjustment::class,
                            'source_id' => $adjustment->id,
                            'idempotency_key' => $idempotencyKey,
                            'description' => 'Tạm ứng lương nhân viên #'.$salary->employee_id,
                            'lines' => [
                                ['account' => '3341', 'debit' => $amount, 'credit' => 0],
                                ['account' => '1121', 'debit' => 0, 'credit' => $amount],
                            ],
                        ]);
                    }
                }
            }

            $this->recalculate($salary);

            return $adjustment;
        });
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

        $bonuses = (float) $adjustments
            ->where('type', 'bonus')
            ->filter(fn (SalaryAdjustment $adjustment) => in_array($adjustment->status, ['applied', null], true))
            ->sum('amount');

        // Chỉ tính khấu trừ từ các adjustments có trạng thái 'applied'
        $deductions = (float) $adjustments
            ->whereIn('type', ['penalty', 'cash_shortage', 'inventory_loss', 'violation', 'advance'])
            ->where('status', 'applied')
            ->sum('amount');

        $salary->update([
            'bonus_amount' => $bonuses,
            'deduction_amount' => $deductions,
            'net_salary' => max(0, (float) $salary->base_salary + $bonuses - $deductions),
        ]);
    }

    /**
     * Tạo bản nháp lương cho tất cả nhân viên active của nhà hàng trong tháng.
     * Dùng cho nút "Tạo bảng lương" trên trang salaries.
     */
    public function generateMonthlyDrafts(int $restaurantId, string $yearMonth, ?int $branchId = null): array
    {
        $periodStart = Carbon::parse($yearMonth.'-01')->startOfMonth()->toDateString();
        $periodEnd = Carbon::parse($yearMonth.'-01')->endOfMonth()->toDateString();

        $periodStartDateTime = $periodStart.' 00:00:00';
        $periodEndDateTime = $periodEnd.' 23:59:59';
        $periodFormatYM = Carbon::parse($periodStart)->format('Y-m');

        $employees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $employeeIds = $employees->pluck('id')->toArray();
        $userIds = $employees->pluck('user_id')->filter()->toArray();

        // Build Bulk Context
        $context = [];

        // 1. Restaurant
        $context['restaurant'] = Restaurant::find($restaurantId);

        // 2. Ingredients
        $context['ingredients'] = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get()
            ->keyBy('id');

        // 3. Assignments (eager load employee and shift)
        $context['assignments'] = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('scheduled_date', [$periodStart, $periodEnd])
            ->with(['employee', 'shift'])
            ->get();

        // 4. All assignments for the month (used for shift matching)
        $context['all_assignments'] = $context['assignments'];

        // 5. Overtime requests
        $context['ot_requests'] = OvertimeRequest::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('scheduled_date', [$periodStart, $periodEnd])
            ->where('status', 'approved')
            ->get();

        // 6. Shift shortages
        $context['responsibility_closings'] = empty($userIds) ? collect() : ShiftClosing::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('cashier_user_id', $userIds)
            ->whereBetween('closing_date', [$periodStart, $periodEnd])
            ->where(function ($q) {
                $q->where('responsibility_amount', '<>', 0)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('responsibility_amount')
                            ->where('cash_difference', '<', 0);
                    });
            })
            ->get();

        // 7. Waste transactions
        $context['waste_transactions'] = InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$periodStartDateTime, $periodEndDateTime])
            ->where('total_cost', '>', 0)
            ->get();

        // 8. Violations
        $context['violations'] = ViolationReport::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('occurred_at', [$periodStartDateTime, $periodEndDateTime])
            ->where('penalty_amount', '>', 0)
            ->where('status', '!=', 'dismissed')
            ->get();

        // 9. KPIs
        $context['kpis'] = EmployeeKpi::withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->where('period', $periodFormatYM)
            ->where('status', 'finalized')
            ->get();

        // 10. Existing adjustments grouped by a composite key
        $adjustments = SalaryAdjustment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('employee_id', $employeeIds)
            ->get();

        $adjustmentsMap = [];
        foreach ($adjustments as $adj) {
            $key = $adj->reference_type.'_'.$adj->reference_id.'_'.$adj->employee_id;
            $adjustmentsMap[$key][] = $adj;
        }
        $context['adjustments_map'] = $adjustmentsMap;

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $locked = 0;

        foreach ($employees as $employee) {
            $baseSalary = $this->calculateDynamicBaseSalary($employee, $periodStart, $periodEnd, $context);

            // Bọc mỗi nhân viên trong transaction riêng để tránh duplicate salary
            // khi 2 request gọi generateMonthlyDrafts đồng thời.
            DB::transaction(function () use ($employee, $restaurantId, $periodStart, $periodEnd, $baseSalary, $context, &$created, &$updated, &$skipped, &$locked) {
                // Khóa tìm kiếm trước khi tạo để tránh race condition
                $salary = Salary::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('employee_id', $employee->id)
                    ->where('pay_period_start', $periodStart)
                    ->where('pay_period_end', $periodEnd)
                    ->lockForUpdate()
                    ->first();

                if ($salary) {
                    $skipped++;

                    if ($salary->status !== 'draft') {
                        $locked++;

                        return;
                    }

                    $salary->update(['base_salary' => $baseSalary]);
                    $this->sweepAdjustments($salary, $employee, $context);
                    $updated++;

                    return;
                }

                $salary = Salary::create([
                    'restaurant_id' => $restaurantId,
                    // TRƯỚC ĐÂY KHÔNG GHI branch_id — cột salaries.branch_id
                    // luôn NULL, khiến lọc bảng lương theo chi nhánh (nếu bật)
                    // luôn trả về rỗng dù nhân viên có chi nhánh rõ ràng.
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'pay_period_start' => $periodStart,
                    'pay_period_end' => $periodEnd,
                    'base_salary' => $baseSalary,
                    'bonus_amount' => 0,
                    'deduction_amount' => 0,
                    'net_salary' => $baseSalary,
                    'status' => 'draft',
                ]);

                $this->sweepAdjustments($salary, $employee, $context);
                $created++;
            });
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'locked' => $locked];
    }

    /** Tính lại một phiếu lương nháp từ dữ liệu chấm công và điều chỉnh hiện tại. */
    public function refreshDraftSalary(Salary $salary): Salary
    {
        if ($salary->status !== 'draft' || ! $salary->employee) {
            return $salary;
        }

        $start = Carbon::parse($salary->pay_period_start)->toDateString();
        $end = Carbon::parse($salary->pay_period_end)->toDateString();
        $baseSalary = $this->calculateDynamicBaseSalary($salary->employee, $start, $end);

        $salary->update(['base_salary' => $baseSalary]);
        $this->sweepAdjustments($salary, $salary->employee);
        $this->recalculate($salary);

        return $salary->fresh();
    }

    /**
     * Tự động quét và cấn trừ các khoản phạt (Hụt két, Hao hụt kho, Vi phạm kỷ luật).
     */
    public function sweepAdjustments(Salary $salary, Employee $employee, ?array $context = null): void
    {
        $restaurantId = $salary->restaurant_id;
        $start = $salary->pay_period_start->toDateString();
        $end = $salary->pay_period_end->toDateString();

        // 1. Quy trách nhiệm ca: âm trừ lương, dương cộng lương.
        if ($employee->user_id) {
            if (isset($context['responsibility_closings'])) {
                $responsibilityClosings = $context['responsibility_closings']
                    ->where('cashier_user_id', $employee->user_id)
                    ->filter(function ($s) use ($start, $end) {
                        $dateStr = $s->closing_date instanceof Carbon
                            ? $s->closing_date->toDateString()
                            : Carbon::parse($s->closing_date)->toDateString();

                        return $dateStr >= $start && $dateStr <= $end;
                    });
            } else {
                $responsibilityClosings = ShiftClosing::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('cashier_user_id', $employee->user_id)
                    ->whereBetween('closing_date', [$start, $end])
                    ->where(function ($q) {
                        $q->where('responsibility_amount', '<>', 0)
                            ->orWhere(function ($legacy) {
                                $legacy->whereNull('responsibility_amount')
                                    ->where('cash_difference', '<', 0);
                            });
                    })
                    ->get();
            }

            foreach ($responsibilityClosings as $closing) {
                $responsibilityAmount = is_null($closing->responsibility_amount)
                    ? (float) $closing->cash_difference
                    : (float) $closing->responsibility_amount;

                if ($responsibilityAmount === 0.0) {
                    continue;
                }

                $exists = false;
                if (isset($context['adjustments_map'])) {
                    $key = ShiftClosing::class.'_'.$closing->id.'_'.$employee->id;
                    $exists = isset($context['adjustments_map'][$key]);
                } else {
                    $exists = SalaryAdjustment::withoutGlobalScopes()
                        ->where('employee_id', $employee->id)
                        ->where('reference_id', $closing->id)
                        ->where('reference_type', ShiftClosing::class)
                        ->exists();
                }

                if (! $exists) {
                    SalaryAdjustment::create([
                        'salary_id' => $salary->id,
                        'restaurant_id' => $restaurantId,
                        'employee_id' => $employee->id,
                        'type' => $responsibilityAmount < 0 ? 'cash_shortage' : 'bonus',
                        'amount' => abs($responsibilityAmount),
                        'reason' => ($responsibilityAmount < 0 ? 'Khấu trừ trách nhiệm ca ngày ' : 'Cộng trách nhiệm ca ngày ').$closing->closing_date,
                        'reference_id' => $closing->id,
                        'reference_type' => ShiftClosing::class,
                        'status' => 'applied',
                    ]);
                }
            }
        }

        // 2. Trừ lương Bếp/Pha chế (Inventory loss): Thất thoát kho cấn trừ theo mốc thời gian ca trực có ngưỡng hao hụt
        if (isset($context['waste_transactions'])) {
            $wasteTransactions = $context['waste_transactions'];
        } else {
            $wasteTransactions = InventoryTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'waste')
                ->whereBetween('occurred_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->where('total_cost', '>', 0)
                ->get();
        }

        foreach ($wasteTransactions as $transaction) {
            if (isset($context['all_assignments'])) {
                $activeAssignments = ScheduleAssignment::findEmployeesOnShiftAtFromCollection($context['all_assignments'], $transaction->occurred_at);
            } else {
                $activeAssignments = ScheduleAssignment::findEmployeesOnShiftAt($transaction->occurred_at, $restaurantId);
            }

            if ($activeAssignments->isEmpty()) {
                continue;
            }

            $ingredient = null;
            if (isset($context['ingredients'])) {
                $ingredient = $context['ingredients']->get($transaction->ingredient_id);
            } else {
                $ingredient = Ingredient::withoutGlobalScopes()->find($transaction->ingredient_id);
            }

            if (! $ingredient) {
                continue;
            }

            $cat = mb_strtolower($ingredient->category_name ?? '');
            $isBeverage = false;
            if (str_contains($cat, 'uống') || str_contains($cat, 'nước') || str_contains($cat, 'bar') || str_contains($cat, 'bia') || str_contains($cat, 'rượu') || str_contains($cat, 'drink') || str_contains($cat, 'beverage') || str_contains($cat, 'pha chế')) {
                $isBeverage = true;
            }

            $filteredAssignments = $activeAssignments->filter(function ($a) use ($isBeverage) {
                $emp = $a->employee;
                if (! $emp) {
                    return false;
                }

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

            $myAssignment = $filteredAssignments->first(fn ($a) => $a->employee_id === $employee->id);
            if (! $myAssignment) {
                continue;
            }

            $leaders = $filteredAssignments->filter(fn ($a) => $a->is_shift_leader);
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
                $exists = false;
                if (isset($context['adjustments_map'])) {
                    $key = InventoryTransaction::class.'_'.$transaction->id.'_'.$employee->id;
                    $exists = isset($context['adjustments_map'][$key]);
                } else {
                    $exists = SalaryAdjustment::withoutGlobalScopes()
                        ->where('employee_id', $employee->id)
                        ->where('reference_id', $transaction->id)
                        ->where('reference_type', InventoryTransaction::class)
                        ->exists();
                }

                if (! $exists) {
                    $allowedRatio = (float) ($ingredient->allowed_waste_ratio ?? 0);

                    $totalPenalty = (float) $transaction->total_cost * (1 - $allowedRatio / 100);
                    $totalPenalty = max(0.0, $totalPenalty);

                    $penaltyAmount = $totalPenalty / $shareCount;

                    if ($penaltyAmount > 0) {
                        SalaryAdjustment::create([
                            'salary_id' => $salary->id,
                            'restaurant_id' => $restaurantId,
                            'employee_id' => $employee->id,
                            'type' => 'inventory_loss',
                            'amount' => $penaltyAmount,
                            'reason' => 'Phạt hao hụt nguyên liệu: '.($transaction->notes ?: 'Thất thoát nguyên liệu ca trực').' (Đã khấu trừ '.$allowedRatio.'% định mức, chia sẻ cho '.$shareCount.' người chịu trách nhiệm)',
                            'reference_id' => $transaction->id,
                            'reference_type' => InventoryTransaction::class,
                            'status' => 'applied',
                        ]);
                    }
                }
            }
        }

        // 3. Trừ lương Vi phạm kỷ luật (Violations): Biên bản xử phạt vi phạm đi trễ, kỷ luật đã duyệt
        if (isset($context['violations'])) {
            $violations = $context['violations']
                ->where('employee_id', $employee->id)
                ->filter(function ($v) use ($start, $end) {
                    $dateStr = $v->occurred_at instanceof Carbon
                        ? $v->occurred_at->toDateString()
                        : Carbon::parse($v->occurred_at)->toDateString();

                    return $dateStr >= $start && $dateStr <= $end;
                });
        } else {
            $violations = ViolationReport::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('occurred_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->where('penalty_amount', '>', 0)
                ->where('status', '!=', 'dismissed')
                ->get();
        }

        foreach ($violations as $violation) {
            $exists = false;
            if (isset($context['adjustments_map'])) {
                $key = ViolationReport::class.'_'.$violation->id.'_'.$employee->id;
                $exists = isset($context['adjustments_map'][$key]);
            } else {
                $exists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('reference_id', $violation->id)
                    ->where('reference_type', ViolationReport::class)
                    ->exists();
            }

            if (! $exists) {
                SalaryAdjustment::create([
                    'salary_id' => $salary->id,
                    'restaurant_id' => $restaurantId,
                    'employee_id' => $employee->id,
                    'type' => 'violation',
                    'amount' => (float) $violation->penalty_amount,
                    'reason' => 'Khấu trừ vi phạm: '.$violation->violation_type.' ('.$violation->description.')',
                    'reference_id' => $violation->id,
                    'reference_type' => ViolationReport::class,
                    'status' => 'applied',
                ]);
            }
        }

        // 4. Cộng thưởng & Hoa hồng KPI từ đánh giá hiệu suất đã duyệt (finalized)
        $period = Carbon::parse($salary->pay_period_start)->format('Y-m');
        if (isset($context['kpis'])) {
            $kpi = $context['kpis']
                ->where('employee_id', $employee->id)
                ->where('period', $period)
                ->first();
        } else {
            $kpi = EmployeeKpi::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('period', $period)
                ->where('status', 'finalized')
                ->first();
        }

        if ($kpi) {
            // Hoa hồng doanh số KPI
            if ((float) $kpi->total_commission > 0) {
                $commissionExists = false;
                if (isset($context['adjustments_map'])) {
                    $key = EmployeeKpi::class.'_'.$kpi->id.'_'.$employee->id;
                    $matches = $context['adjustments_map'][$key] ?? [];
                    foreach ($matches as $adj) {
                        if ($adj->type === 'bonus' && str_contains($adj->reason, 'Thưởng hoa hồng')) {
                            $commissionExists = true;
                            break;
                        }
                    }
                } else {
                    $commissionExists = SalaryAdjustment::withoutGlobalScopes()
                        ->where('salary_id', $salary->id)
                        ->where('employee_id', $employee->id)
                        ->where('type', 'bonus')
                        ->where('reference_id', $kpi->id)
                        ->where('reference_type', EmployeeKpi::class)
                        ->where('reason', 'like', 'Thưởng hoa hồng%')
                        ->exists();
                }

                if (! $commissionExists) {
                    SalaryAdjustment::create([
                        'salary_id' => $salary->id,
                        'restaurant_id' => $restaurantId,
                        'employee_id' => $employee->id,
                        'type' => 'bonus',
                        'amount' => (float) $kpi->total_commission,
                        'reason' => 'Thưởng hoa hồng doanh số theo hiệu suất KPI kỳ '.$kpi->period,
                        'reference_id' => $kpi->id,
                        'reference_type' => EmployeeKpi::class,
                        'status' => 'applied',
                    ]);
                }
            }

            // Thưởng vượt chỉ tiêu KPI
            if ((float) $kpi->total_bonus > 0) {
                $bonusExists = false;
                if (isset($context['adjustments_map'])) {
                    $key = EmployeeKpi::class.'_'.$kpi->id.'_'.$employee->id;
                    $matches = $context['adjustments_map'][$key] ?? [];
                    foreach ($matches as $adj) {
                        if ($adj->type === 'bonus' && str_contains($adj->reason, 'Thưởng vượt chỉ tiêu')) {
                            $bonusExists = true;
                            break;
                        }
                    }
                } else {
                    $bonusExists = SalaryAdjustment::withoutGlobalScopes()
                        ->where('salary_id', $salary->id)
                        ->where('employee_id', $employee->id)
                        ->where('type', 'bonus')
                        ->where('reference_id', $kpi->id)
                        ->where('reference_type', EmployeeKpi::class)
                        ->where('reason', 'like', 'Thưởng vượt chỉ tiêu%')
                        ->exists();
                }

                if (! $bonusExists) {
                    SalaryAdjustment::create([
                        'salary_id' => $salary->id,
                        'restaurant_id' => $restaurantId,
                        'employee_id' => $employee->id,
                        'type' => 'bonus',
                        'amount' => (float) $kpi->total_bonus,
                        'reason' => 'Thưởng vượt chỉ tiêu KPI kỳ '.$kpi->period,
                        'reference_id' => $kpi->id,
                        'reference_type' => EmployeeKpi::class,
                        'status' => 'applied',
                    ]);
                }
            }
        }

        // 5. Tính toán lại tổng các khoản và cập nhật net_salary thực lãnh
        $this->recalculate($salary);
    }

    /**
     * Lấy giải trình chi tiết công thức tính lương cho phiếu lương (Pay Stub Breakdown).
     */
    public function getSalaryCalculationDetails(Salary $salary): array
    {
        $employee = $salary->employee;
        if (! $employee) {
            return [];
        }

        $start = $salary->pay_period_start instanceof Carbon
            ? $salary->pay_period_start->toDateString()
            : Carbon::parse($salary->pay_period_start)->toDateString();

        $end = $salary->pay_period_end instanceof Carbon
            ? $salary->pay_period_end->toDateString()
            : Carbon::parse($salary->pay_period_end)->toDateString();

        $compType = $employee->compensation_type ?? 'fixed';
        $payRate = (float) ($employee->pay_rate ?? 0);
        $contractSalary = (float) ($employee->base_salary ?? 0);

        $carbonStart = Carbon::parse($start);
        $daysInMonth = $carbonStart->daysInMonth;
        $standardDays = 26; // Chuẩn công tháng (mặc định 26 ngày)

        $calculation = $compType === 'hourly'
            ? $this->hourlyCalculation($employee, $start, $end)
            : $this->shiftCalculation($employee, $start, $end);
        $leaveDays = $this->leaveDayCounts($employee, $start, $end);
        $actualWorkDays = (int) ($calculation['actual_work_days'] ?? 0);
        $completedShiftsCount = (int) ($calculation['completed_shifts_count'] ?? 0);
        $paidLeaveDays = $leaveDays['paid'];
        $unpaidLeaveDays = $leaveDays['unpaid'];
        $totalPaidDays = $actualWorkDays + $paidLeaveDays;
        $regularHours = (float) ($calculation['regular_hours'] ?? 0);
        $otHours = (float) ($calculation['ot_hours'] ?? 0);
        $unapprovedOtHours = (float) ($calculation['unapproved_ot_hours'] ?? 0);
        $restaurant = Restaurant::find($employee->restaurant_id);
        $otMultiplier = (float) ($restaurant?->ot_multiplier ?? 1.50);
        $regularAmount = round($regularHours * $payRate, 2);
        $overtimeAmount = round($otHours * $payRate * $otMultiplier, 2);

        $dailyRate = $standardDays > 0 ? round($contractSalary / $standardDays, 0) : 0;

        $formulaText = '';
        if ($compType === 'fixed') {
            $formulaText = number_format($contractSalary)." đ (lương tháng cố định; {$standardDays} ngày chuẩn tham chiếu) = ".number_format($salary->base_salary).' đ';
        } elseif ($compType === 'hourly') {
            $formulaText = '('.number_format($regularHours, 2).'h giờ thường × '.number_format($payRate).' đ/h) + ('.number_format($otHours, 2).'h OT được duyệt × '.number_format($payRate)." đ/h × {$otMultiplier}) = ".number_format($salary->base_salary).' đ';
            if ($unapprovedOtHours > 0) {
                $formulaText .= ' | '.number_format($unapprovedOtHours, 2).'h ngoài ca chưa được duyệt OT (không tính lương)';
            }
        } elseif ($compType === 'shift') {
            $formulaText = "{$completedShiftsCount} ca hoàn thành × ".number_format($payRate).' đ/ca = '.number_format($salary->base_salary).' đ';
        }

        return [
            'compensation_type' => $compType,
            'compensation_type_label' => match ($compType) {
                'fixed' => 'Lương tháng cố định',
                'hourly' => 'Lương theo giờ',
                'shift' => 'Lương theo ca',
                default => 'Lương cố định',
            },
            'contract_salary' => $contractSalary,
            'pay_rate' => $payRate,
            'standard_days' => $standardDays,
            'days_in_month' => $daysInMonth,
            'actual_work_days' => $actualWorkDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'total_paid_days' => $totalPaidDays,
            'daily_rate' => $dailyRate,
            'completed_shifts_count' => $completedShiftsCount,
            'regular_hours' => round($regularHours, 2),
            'ot_hours' => round($otHours, 2),
            'unapproved_ot_hours' => round($unapprovedOtHours, 2),
            'ot_multiplier' => $otMultiplier,
            'regular_amount' => $regularAmount,
            'overtime_amount' => $overtimeAmount,
            'policy_note' => $compType === 'fixed'
                ? 'Lương tháng cố định không tự giảm vì thiếu log chấm công; các ngày vắng/đi muộn cần điều chỉnh được duyệt.'
                : 'Chỉ ca hoàn thành và OT được duyệt mới được dùng để tính lương.',
            'formula_text' => $formulaText,
        ];
    }

    /**
     * Phê duyệt hàng loạt danh sách bảng lương.
     */
    public function bulkApprove(int $restaurantId, array $salaryIds, int $approvedById, ?int $branchId = null): int
    {
        $salaries = Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $salaryIds)
            ->where('status', 'draft')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $count = 0;
        foreach ($salaries as $salary) {
            $salary = $this->refreshDraftSalary($salary);
            $salary->update([
                'status' => 'approved',
                'approved_by' => $approvedById,
            ]);
            $count++;

            $employeeUser = $salary->employee?->user;
            if ($employeeUser) {
                $periodStr = $salary->pay_period_start ? Carbon::parse($salary->pay_period_start)->format('m/Y') : '';
                $employeeUser->notify(new SalaryReadyNotification(
                    $salary,
                    "Phiếu lương kỳ {$periodStr} đã được phê duyệt. Bạn có thể xem chi tiết ngay."
                ));
            }
        }

        return $count;
    }
}
