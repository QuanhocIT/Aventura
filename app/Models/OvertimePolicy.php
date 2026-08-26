<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimePolicy extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'normal_multiplier' => 'float',
            'night_multiplier' => 'float',
            'rest_day_multiplier' => 'float',
            'holiday_multiplier' => 'float',
            'max_daily_hours' => 'float',
            'max_weekly_hours' => 'float',
            'max_monthly_hours' => 'float',
            'minimum_rest_hours' => 'float',
            'require_gps' => 'boolean',
            'require_qr' => 'boolean',
            'require_photo' => 'boolean',
            'employee_can_request' => 'boolean',
            'require_employee_acceptance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
