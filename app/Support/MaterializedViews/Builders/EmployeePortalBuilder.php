<?php

namespace App\Support\MaterializedViews\Builders;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\ScheduleAssignment;
use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Snapshot phần dữ liệu ổn định của cổng nhân sự.
 *
 * Notifications không nằm trong snapshot vì cần phản ánh gần như thời gian thực;
 * controller vẫn đọc riêng 15 thông báo mới nhất theo user hiện tại.
 */
class EmployeePortalBuilder implements MaterializedViewBuilder
{
    public function scopeKey(?int $branchId): string
    {
        return $branchId ? "branch:{$branchId}" : 'restaurant';
    }

    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array
    {
        $asOf = Carbon::parse($date->toDateString());
        $period = $asOf->format('Y-m');
        $startOfMonth = $asOf->copy()->startOfMonth();
        $endOfMonth = $asOf->copy()->endOfMonth();

        $employees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get(['id', 'base_salary']);

        if ($employees->isEmpty()) {
            return [
                'period' => $period,
                'employees' => [],
            ];
        }

        $employeeIds = $employees->pluck('id')->all();

        $completedAssignments = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('status', 'completed')
            ->get(['employee_id', 'check_in_at', 'check_out_at']);

        $completedByEmployee = $completedAssignments->groupBy('employee_id');

        $kpis = EmployeeKpi::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('period', $period)
            ->with('metrics')
            ->get()
            ->keyBy('employee_id');

        $startRange = $asOf->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
        $endRange = $asOf->copy()->addWeeks(2)->endOfWeek(Carbon::SUNDAY);

        $schedulesByEmployee = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('scheduled_date', [$startRange->toDateString(), $endRange->toDateString()])
            ->with('shift')
            ->get()
            ->groupBy('employee_id');

        $payload = [];

        foreach ($employees as $employee) {
            $employeeAssignments = $completedByEmployee->get($employee->id, collect());
            $hoursWorked = $employeeAssignments->sum(function (ScheduleAssignment $assignment): float {
                if (! $assignment->check_in_at || ! $assignment->check_out_at) {
                    return 0.0;
                }

                return Carbon::parse($assignment->check_in_at)
                    ->diffInSeconds(Carbon::parse($assignment->check_out_at)) / 3600.0;
            });

            $kpi = $kpis->get($employee->id);
            $baseSalary = (float) ($employee->base_salary ?? 0);
            $kpiBonus = $kpi ? (float) $kpi->total_bonus : 0.0;
            $kpiCommission = $kpi ? (float) $kpi->total_commission : 0.0;

            $payload[(string) $employee->id] = [
                'summary' => [
                    'shifts_completed' => $employeeAssignments->count(),
                    'hours_worked' => round($hoursWorked, 1),
                    'estimated_earnings' => $baseSalary + $kpiBonus + $kpiCommission,
                    'base_salary' => $baseSalary,
                    'kpi_bonus' => $kpiBonus,
                    'kpi_commission' => $kpiCommission,
                ],
                'kpis' => $this->formatKpi($kpi),
                'schedules' => $schedulesByEmployee
                    ->get($employee->id, collect())
                    ->map(fn (ScheduleAssignment $assignment) => $this->formatSchedule($assignment))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'period' => $period,
            'employees' => $payload,
        ];
    }

    /** @return array<string, mixed>|null */
    private function formatKpi(?EmployeeKpi $kpi): ?array
    {
        if (! $kpi) {
            return null;
        }

        return [
            'total_score' => (float) $kpi->total_score,
            'total_bonus' => (float) $kpi->total_bonus,
            'total_commission' => (float) $kpi->total_commission,
            'status' => $kpi->status,
            'metrics' => $kpi->metrics
                ->map(fn ($metric) => [
                    'metric_name' => $metric->metric_name,
                    'actual_value' => (float) $metric->actual_value,
                    'target_value' => (float) $metric->target_value,
                    'score' => (float) $metric->score,
                    'is_achieved' => (bool) $metric->is_achieved,
                    'bonus_earned' => (float) $metric->bonus_earned,
                    'commission_earned' => (float) $metric->commission_earned,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatSchedule(ScheduleAssignment $assignment): array
    {
        $date = $assignment->scheduled_date instanceof Carbon
            ? $assignment->scheduled_date
            : Carbon::parse($assignment->scheduled_date);

        return [
            'id' => $assignment->id,
            'date' => $date->toDateString(),
            'formatted_date' => $date->format('d/m/Y'),
            'day_name' => $this->getDayVn($date->format('l')),
            'shift_name' => $assignment->shift?->name ?? 'Ca Trực',
            'start_time' => $assignment->shift?->start_time ? substr($assignment->shift->start_time, 0, 5) : '',
            'end_time' => $assignment->shift?->end_time ? substr($assignment->shift->end_time, 0, 5) : '',
            'status' => $assignment->status,
            'check_in_at' => $assignment->check_in_at ? Carbon::parse($assignment->check_in_at)->format('H:i') : null,
            'check_out_at' => $assignment->check_out_at ? Carbon::parse($assignment->check_out_at)->format('H:i') : null,
        ];
    }

    private function getDayVn(string $day): string
    {
        return [
            'Monday' => 'Thứ Hai',
            'Tuesday' => 'Thứ Ba',
            'Wednesday' => 'Thứ Tư',
            'Thursday' => 'Thứ Năm',
            'Friday' => 'Thứ Sáu',
            'Saturday' => 'Thứ Bảy',
            'Sunday' => 'Chủ Nhật',
        ][$day] ?? $day;
    }
}
