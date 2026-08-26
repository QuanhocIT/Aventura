<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeHoliday;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRequest;
use App\Models\Restaurant;
use App\Models\ScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class OvertimePolicyService
{
    public const MAX_DAILY_HOURS = 4.0;
    public const MAX_WEEKLY_HOURS = 12.0;
    public const MAX_MONTHLY_HOURS = 40.0;
    public const MINIMUM_REST_HOURS = 11.0;

    /** @return array<string, array{label: string, description: string}> */
    public function types(): array
    {
        return [
            'normal' => ['label' => 'Ngày thường', 'description' => 'Tăng ca sau ca làm việc hoặc theo nhu cầu phát sinh.'],
            'night' => ['label' => 'Khung giờ ban đêm', 'description' => 'Tăng ca có thời gian làm việc vào ban đêm.'],
            'rest_day' => ['label' => 'Ngày nghỉ hằng tuần', 'description' => 'Tăng ca vào ngày nghỉ theo lịch của nhân viên.'],
            'holiday' => ['label' => 'Ngày lễ / đặc biệt', 'description' => 'Mức hệ số cao nhất theo chính sách doanh nghiệp.'],
        ];
    }

    public function policyFor(Employee $employee, string|Carbon $date): array
    {
        $date = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
        $policies = OvertimePolicy::withoutGlobalScopes()
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->get();

        $priority = function (OvertimePolicy $item) use ($employee): int {
            $score = 0;
            if ((int) $item->employee_id === (int) $employee->id) {
                $score += 1000;
            }
            if ($item->role_id && (int) $item->role_id === (int) $employee->role_id) {
                $score += 100;
            }
            if ($item->branch_id && (int) $item->branch_id === (int) $employee->branch_id) {
                $score += 10;
            }

            return $score;
        };
        $policy = $policies->sort(function (OvertimePolicy $left, OvertimePolicy $right) use ($priority): int {
            $priorityDiff = $priority($right) <=> $priority($left);
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }

            return Carbon::parse($right->effective_from)->timestamp <=> Carbon::parse($left->effective_from)->timestamp;
        })->first();

        if (! $policy) {
            $restaurant = $employee->restaurant ?: Restaurant::find($employee->restaurant_id);
            $normal = max(1.0, (float) ($restaurant?->ot_multiplier ?? 1.50));

            return [
                'id' => null, 'name' => 'Chính sách mặc định',
                'normal_multiplier' => $normal,
                'night_multiplier' => max($normal, 2.0),
                'rest_day_multiplier' => max($normal, 2.0),
                'holiday_multiplier' => max($normal, 3.0),
                'max_daily_hours' => self::MAX_DAILY_HOURS,
                'max_weekly_hours' => self::MAX_WEEKLY_HOURS,
                'max_monthly_hours' => self::MAX_MONTHLY_HOURS,
                'minimum_rest_hours' => self::MINIMUM_REST_HOURS,
                'require_gps' => true, 'require_qr' => false, 'require_photo' => false,
                'employee_can_request' => true, 'require_employee_acceptance' => true,
            ];
        }

        return $policy->only([
            'id', 'name', 'normal_multiplier', 'night_multiplier', 'rest_day_multiplier',
            'holiday_multiplier', 'max_daily_hours', 'max_weekly_hours', 'max_monthly_hours',
            'minimum_rest_hours', 'require_gps', 'require_qr', 'require_photo',
            'employee_can_request', 'require_employee_acceptance',
        ]);
    }

    public function multiplier(Restaurant $restaurant, string $type, ?Employee $employee = null, ?string $date = null): float
    {
        $settings = $employee ? $this->policyFor($employee, $date ?: today()->toDateString()) : null;
        $base = max(1.0, (float) ($settings['normal_multiplier'] ?? $restaurant->ot_multiplier ?? 1.50));

        if ($type === 'holiday' && $employee && $date) {
            $holiday = $this->holidayFor($employee, $date);
            if ($holiday?->multiplier) {
                return max($base, (float) $holiday->multiplier);
            }
        }

        return match ($type) {
            'night' => max($base, (float) ($settings['night_multiplier'] ?? 2.0)),
            'rest_day' => max($base, (float) ($settings['rest_day_multiplier'] ?? 2.0)),
            'holiday' => max($base, (float) ($settings['holiday_multiplier'] ?? 3.0)),
            default => $base,
        };
    }

    public function hourlyRate(Employee $employee): float
    {
        return match ($employee->compensation_type ?? 'fixed') {
            'hourly' => (float) ($employee->pay_rate ?? 0),
            'shift' => round((float) ($employee->pay_rate ?? 0) / 8, 2),
            default => round((float) ($employee->base_salary ?? 0) / 26 / 8, 2),
        };
    }

    /** @return array{hourly_rate: float, multiplier: float, estimated_amount: float} */
    public function quote(Employee $employee, Restaurant $restaurant, float $hours, string $type, ?string $date = null): array
    {
        $hourlyRate = $this->hourlyRate($employee);
        $multiplier = $this->multiplier($restaurant, $type, $employee, $date);

        return ['hourly_rate' => $hourlyRate, 'multiplier' => $multiplier, 'estimated_amount' => round($hours * $hourlyRate * $multiplier, 2)];
    }

    /** @return array{start: Carbon, end: Carbon} */
    public function window(string $date, ?string $startTime, ?string $endTime, float $hours): array
    {
        $startTime ??= '18:00';
        $endTime ??= Carbon::parse($date.' '.$startTime)->addMinutes((int) round($hours * 60))->format('H:i');
        $start = Carbon::parse($date.' '.$startTime);
        $end = Carbon::parse($date.' '.$endTime);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    public function holidayFor(Employee $employee, string $date): ?OvertimeHoliday
    {
        return OvertimeHoliday::withoutGlobalScopes()
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('is_active', true)
            ->whereDate('holiday_date', $date)
            ->where(function ($query) use ($employee): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id);
            })
            ->orderByDesc('branch_id')
            ->first();
    }

    public function validateRequest(Employee $employee, string $date, Carbon $start, Carbon $end, float $hours, ?int $ignoreId = null): void
    {
        $settings = $this->policyFor($employee, $date);
        $dailyLimit = (float) ($settings['max_daily_hours'] ?? self::MAX_DAILY_HOURS);
        $weeklyLimit = (float) ($settings['max_weekly_hours'] ?? self::MAX_WEEKLY_HOURS);
        $monthlyLimit = (float) ($settings['max_monthly_hours'] ?? self::MAX_MONTHLY_HOURS);

        if ($hours > $dailyLimit) {
            throw ValidationException::withMessages(['hours_requested' => "Mỗi ngày chỉ được đăng ký tối đa {$dailyLimit} giờ OT."]);
        }

        $approvedLeave = LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
        if ($approvedLeave) {
            throw ValidationException::withMessages(['scheduled_date' => 'Nhân viên đang có đơn nghỉ đã được duyệt trong ngày này, không thể đăng ký OT.']);
        }

        $assignments = ScheduleAssignment::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', ['absent', 'leave_approved'])
            ->with('shift')
            ->get();
        foreach ($assignments as $assignment) {
            if (! $assignment->shift) {
                continue;
            }
            $shiftStart = Carbon::parse($date.' '.$assignment->shift->start_time);
            $shiftEnd = Carbon::parse($date.' '.$assignment->shift->end_time);
            if ($assignment->shift->is_overnight || $shiftEnd->lessThanOrEqualTo($shiftStart)) {
                $shiftEnd->addDay();
            }
            if ($start->lt($shiftEnd) && $end->gt($shiftStart)) {
                throw ValidationException::withMessages(['start_time' => 'Khung giờ OT đang chồng lên ca đã xếp, vui lòng chọn thời gian ngoài ca.']);
            }
        }

        $query = OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotIn('workflow_status', ['cancelled', 'withdrawn', 'rejected', 'paid'])
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId));

        $dailyHours = (float) (clone $query)->whereDate('scheduled_date', $date)->sum('hours_requested');
        if ($dailyHours + $hours > $dailyLimit) {
            throw ValidationException::withMessages(['hours_requested' => "Tổng OT trong ngày không được vượt quá {$dailyLimit} giờ."]);
        }

        $day = Carbon::parse($date);
        $weeklyHours = (float) (clone $query)->whereBetween('scheduled_date', [$day->copy()->startOfWeek()->toDateString(), $day->copy()->endOfWeek()->toDateString()])->sum('hours_requested');
        if ($weeklyHours + $hours > $weeklyLimit) {
            throw ValidationException::withMessages(['hours_requested' => "Tổng OT trong tuần không được vượt quá {$weeklyLimit} giờ."]);
        }

        $monthlyHours = (float) (clone $query)->whereBetween('scheduled_date', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])->sum('hours_requested');
        if ($monthlyHours + $hours > $monthlyLimit) {
            throw ValidationException::withMessages(['hours_requested' => "Tổng OT trong tháng không được vượt quá {$monthlyLimit} giờ."]);
        }

        $minimumRest = (float) ($settings['minimum_rest_hours'] ?? self::MINIMUM_REST_HOURS);
        $previousEnd = $this->previousWorkEnd($employee, $start, $ignoreId);
        if ($previousEnd && $previousEnd->diffInMinutes($start) / 60 < $minimumRest) {
            throw ValidationException::withMessages(['start_time' => "Khung giờ này không bảo đảm tối thiểu {$minimumRest} giờ nghỉ giữa hai ca."]);
        }
    }

    private function previousWorkEnd(Employee $employee, Carbon $start, ?int $ignoreId): ?Carbon
    {
        $ends = collect();
        ScheduleAssignment::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereDate('scheduled_date', '>=', $start->copy()->subDays(3)->toDateString())
            ->whereDate('scheduled_date', '<=', $start->toDateString())
            ->whereNotIn('status', ['absent', 'leave_approved'])
            ->with('shift')
            ->get()
            ->each(function (ScheduleAssignment $assignment) use ($ends): void {
                if (! $assignment->shift) {
                    return;
                }
                $date = Carbon::parse($assignment->scheduled_date)->toDateString();
                $end = Carbon::parse($date.' '.$assignment->shift->end_time);
                if ($assignment->shift->is_overnight || $end->lessThanOrEqualTo(Carbon::parse($date.' '.$assignment->shift->start_time))) {
                    $end->addDay();
                }
                // OT nối tiếp ngay sau ca chính là trường hợp hợp lệ phổ biến;
                // quy tắc nghỉ tối thiểu chỉ áp dụng giữa hai ca tách biệt.
                if ($date === $start->toDateString()) {
                    return;
                }
                $ends->push($end);
            });

        OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotIn('workflow_status', ['cancelled', 'withdrawn', 'rejected', 'paid'])
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->whereDate('scheduled_date', '>=', $start->copy()->subDays(3)->toDateString())
            ->whereNotNull('scheduled_end_at')
            ->where('scheduled_end_at', '<=', $start)
            ->get(['scheduled_end_at'])
            ->each(fn (OvertimeRequest $request) => $ends->push($request->scheduled_end_at));

        return $ends->filter(fn ($end) => $end instanceof Carbon && $end->lte($start))->sortDesc()->first();
    }
}
