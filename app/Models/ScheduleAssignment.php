<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Hr\ScheduleAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAssignment extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        $lockCheck = function (self $model) {
            $date = $model->scheduled_date instanceof \Carbon\Carbon
                ? $model->scheduled_date->toDateString()
                : \Carbon\Carbon::parse($model->scheduled_date)->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, $model->employee_id, $date)) {
                throw new \Exception("Dữ liệu lịch trực đã bị khóa do bảng lương của kỳ này đã được phê duyệt.");
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'is_shift_leader' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public static function findEmployeesOnShiftAt($timestamp, $restaurantId)
    {
        $dateTime = \Carbon\Carbon::parse($timestamp);
        $targetDate = $dateTime->toDateString();
        $prevDate = $dateTime->copy()->subDay()->toDateString();
        $nextDate = $dateTime->copy()->addDay()->toDateString();

        $assignments = self::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
            ->whereBetween('scheduled_date', [$prevDate, $nextDate])
            ->with(['employee', 'shift'])
            ->get();

        $activeAssignments = collect();

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (!$shift || $shift->status !== 'active') {
                continue;
            }

            $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                ? $assignment->scheduled_date->toDateString()
                : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

            if (!in_array($dateStr, [$prevDate, $targetDate, $nextDate])) {
                continue;
            }

            $start = \Carbon\Carbon::parse($dateStr . ' ' . $shift->start_time);
            
            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $end = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
            } else {
                $end = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time);
            }

            if ($dateTime->between($start, $end)) {
                $activeAssignments->push($assignment);
            }
        }

        return $activeAssignments;
    }

    public static function findEmployeeOnShiftAt($timestamp, $restaurantId)
    {
        $active = self::findEmployeesOnShiftAt($timestamp, $restaurantId);
        return $active->first()?->employee;
    }

    protected static function newFactory(): Factory
    {
        return ScheduleAssignmentFactory::new();
    }
}

