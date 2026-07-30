<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiMetric extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'weight' => 'decimal:2',
        'target' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
    ];

    public function employeeKpiMetrics(): HasMany
    {
        return $this->hasMany(EmployeeKpiMetric::class);
    }
}
