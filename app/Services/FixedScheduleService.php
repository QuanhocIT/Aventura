<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFixedSchedule;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedScheduleService
{
    /**
     * Keep the roster useful immediately while the recurring pattern remains
     * the source of truth for future roster generation.
     */
    private const DEFAULT_ROSTER_DAYS = [1, 2, 3, 4, 5, 6];

    private const ROSTER_HORIZON_DAYS = 90;

    /**
     * Create/update the recurring pattern and materialize it into the roster.
     * A fixed-paid employee always receives a pattern, even when the form was
     * submitted without optional schedule fields.
     *
     * @param  array<int>  $weekdays
     */
    public function createForEmployee(
        Employee $employee,
        ?int $shiftId = null,
        ?array $weekdays = null,
        ?string $effectiveFrom = null,
        ?string $effectiveTo = null,
    ): void {
        if (($employee->compensation_type ?? 'fixed') !== 'fixed') {
            return;
        }

        $from = Carbon::parse($effectiveFrom ?: ($employee->hire_date ?: today()->toDateString()))->startOfDay();
        $days = collect($weekdays ?: self::DEFAULT_ROSTER_DAYS)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days === []) {
            throw ValidationException::withMessages([
                'fixed_weekdays' => 'Nhân viên lương cố định phải có ít nhất một ngày làm việc cố định.',
            ]);
        }

        $shift = $this->resolveShift($employee, $shiftId);
        $to = $effectiveTo ? Carbon::parse($effectiveTo)->endOfDay() : null;

        if ($to && $to->lt($from)) {
            throw ValidationException::withMessages([
                'fixed_schedule_until' => 'Ngày kết thúc lịch cố định phải sau hoặc bằng ngày bắt đầu.',
            ]);
        }

        DB::transaction(function () use ($employee, $shift, $days, $from, $to): void {
            EmployeeFixedSchedule::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_to' => $from->copy()->subDay()->toDateString()]);

            foreach ($days as $weekday) {
                EmployeeFixedSchedule::create([
                    'restaurant_id' => $employee->restaurant_id,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'shift_id' => $shift->id,
                    'weekday' => $weekday,
                    'effective_from' => $from->toDateString(),
                    'effective_to' => $to?->toDateString(),
                    'is_active' => true,
                ]);
            }

            $this->syncAssignments($employee, $from, $to ?: $from->copy()->addDays(self::ROSTER_HORIZON_DAYS));
        });
    }

    public function deactivateForEmployee(Employee $employee): void
    {
        EmployeeFixedSchedule::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'effective_to' => today()->subDay()->toDateString()]);
    }

    /** Materialize active recurring patterns for the requested date range. */
    public function syncAssignments(Employee $employee, Carbon $from, Carbon $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }

        $patterns = EmployeeFixedSchedule::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $to->toDateString())
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString());
            })
            ->with('shift')
            ->get();

        $created = 0;
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            foreach ($patterns as $pattern) {
                if ((int) $pattern->weekday !== $date->dayOfWeekIso) {
                    continue;
                }
                if ($date->lt(Carbon::parse($pattern->effective_from)->startOfDay())) {
                    continue;
                }
                if ($pattern->effective_to && $date->gt(Carbon::parse($pattern->effective_to)->endOfDay())) {
                    continue;
                }

                $assignment = ScheduleAssignment::withoutGlobalScopes()->firstOrCreate(
                    [
                        'restaurant_id' => $employee->restaurant_id,
                        'employee_id' => $employee->id,
                        'shift_id' => $pattern->shift_id,
                        'scheduled_date' => $date->toDateString(),
                    ],
                    [
                        'branch_id' => $employee->branch_id,
                        'status' => 'scheduled',
                        'notes' => 'Tự động từ lịch làm cố định',
                    ],
                );

                if ($assignment->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    private function resolveShift(Employee $employee, ?int $shiftId): WorkShift
    {
        $query = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('status', 'active')
            ->where(function ($scope) use ($employee): void {
                $scope->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id);
            });

        if ($shiftId !== null) {
            $shift = (clone $query)->whereKey($shiftId)->first();
            if (! $shift) {
                throw ValidationException::withMessages([
                    'fixed_shift_id' => 'Ca làm cố định không thuộc nhà hàng hoặc chi nhánh của nhân viên.',
                ]);
            }

            return $shift;
        }

        $shift = $query->orderBy('id')->first();
        if ($shift) {
            return $shift;
        }

        $code = 'CA_CO_DINH';
        $counter = 1;
        while (WorkShift::withTrashed()->where('restaurant_id', $employee->restaurant_id)->where('code', $code)->exists()) {
            $code = 'CA_CO_DINH_'.$counter++;
        }

        return WorkShift::create([
            'restaurant_id' => $employee->restaurant_id,
            'branch_id' => $employee->branch_id,
            'name' => 'Ca cố định',
            'code' => $code,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'status' => 'active',
        ]);
    }
}
