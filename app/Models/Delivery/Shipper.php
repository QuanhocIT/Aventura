<?php

namespace App\Models\Delivery;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipper extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_capacity_kg' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(ShipperLocationLog::class);
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(ShipperLocationLog::class)->latestOfMany('logged_at');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DeliveryBatch::class);
    }

    public function activeBatch(): HasOne
    {
        return $this->hasOne(DeliveryBatch::class)
            ->whereIn('status', ['dispatched', 'in_progress'])
            ->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getCurrentLoad(): array
    {
        $activeBatch = $this->activeBatch;

        if (! $activeBatch) {
            return ['orders' => 0, 'weight_kg' => 0];
        }

        $pendingItems = $activeBatch->items()->whereIn('status', ['pending', 'picked_up'])->count();

        return [
            'orders' => $pendingItems,
            'weight_kg' => (float) $activeBatch->total_weight_kg,
        ];
    }
}
