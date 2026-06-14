<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Hr\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Delivery\Shipper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;
    use HasRoles;

    protected $guarded = [];

    protected string $guard_name = 'web';

    protected static function booted(): void
    {
        static::saved(function (self $employee): void {
            // Keep Spatie pivot table consistent with employees.role_id.
            if ($employee->role_id) {
                $employee->syncRoles([$employee->role_id]);

                return;
            }

            $employee->syncRoles([]);
        });

        static::deleted(function (self $employee): void {
            $employee->syncRoles([]);
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'base_salary' => 'decimal:2',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isWithinScheduledShift(): bool
    {
        $now = now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $tomorrow = $now->copy()->addDay()->toDateString();

        $assignments = $this->schedules()
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->with('shift')
            ->get();

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (!$shift || $shift->status !== 'active') {
                continue;
            }

            $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                ? $assignment->scheduled_date->toDateString()
                : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

            if (!in_array($dateStr, [$yesterday, $today, $tomorrow])) {
                continue;
            }

            $start = \Carbon\Carbon::parse($dateStr . ' ' . $shift->start_time);
            
            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $end = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay();
            } else {
                $end = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time);
            }

            $allowedStart = $start->copy()->subMinutes(30);
            $allowedEnd = $end->copy()->addMinutes(30);

            if ($now->between($allowedStart, $allowedEnd)) {
                return true;
            }
        }

        return false;
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    public function violationReports(): HasMany
    {
        return $this->hasMany(ViolationReport::class);
    }

    public function shipper(): HasOne
    {
        return $this->hasOne(Shipper::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\MediaAsset::class, 'attachable');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'employee_avatar');
    }

    public function citizenIdFront(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'citizen_id_front');
    }

    public function citizenIdBack(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'citizen_id_back');
    }

    protected static function newFactory(): Factory
    {
        return EmployeeFactory::new();
    }
}

