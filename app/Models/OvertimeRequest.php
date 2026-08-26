<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'hours_requested' => 'float',
            'hours_approved' => 'float',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'worked_hours' => 'float',
            'hourly_rate' => 'decimal:2',
            'overtime_multiplier' => 'float',
            'estimated_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'employee_responded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'payroll_included_at' => 'datetime',
            'manager_adjusted_hours' => 'float',
            'manager_adjusted_amount' => 'decimal:2',
            'attendance_verified_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'gps_distance_meters' => 'float',
            'last_action_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attendanceVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_verified_by');
    }

    public function lastActionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_action_by');
    }
}
