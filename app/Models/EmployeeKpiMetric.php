<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeKpiMetric extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'actual_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'score' => 'decimal:2',
        'is_achieved' => 'boolean',
        'bonus_earned' => 'decimal:2',
        'commission_earned' => 'decimal:2',
    ];

    public function employeeKpi(): BelongsTo
    {
        return $this->belongsTo(EmployeeKpi::class);
    }

    public function kpiMetric(): BelongsTo
    {
        return $this->belongsTo(KpiMetric::class);
    }
}
