<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationReport extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at'    => 'datetime',
            'penalty_amount' => 'decimal:2',
            'is_anonymous'   => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
