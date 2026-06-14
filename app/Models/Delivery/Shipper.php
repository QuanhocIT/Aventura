<?php

namespace App\Models\Delivery;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipper extends Model
{
    use BelongsToRestaurant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'last_seen_at'       => 'datetime',
            'current_lat'        => 'decimal:7',
            'current_lng'        => 'decimal:7',
            'max_capacity_kg'    => 'decimal:2',
        ];
    }

    /** Vehicle speed in km/h for ETA calculations */
    public const SPEED_PROFILES = [
        'bike'      => 13,
        'motorbike' => 32,
        'car'       => 38,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(ShipperLocationLog::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DeliveryBatch::class);
    }

    public function activeBatch(): HasOne
    {
        return $this->hasOne(DeliveryBatch::class)
            ->whereIn('status', ['dispatched', 'in_progress'])
            ->with('items')
            ->latest();
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(ShipperLocationLog::class)->latestOfMany('logged_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getSpeedKmh(): int
    {
        return self::SPEED_PROFILES[$this->vehicle_type] ?? 32;
    }

    public function getCurrentLoad(): array
    {
        $batch = $this->relationLoaded('activeBatch')
            ? $this->activeBatch
            : $this->activeBatch()->first();

        if (!$batch) {
            return ['orders' => 0, 'active_batch_id' => null];
        }

        $orders = $batch->relationLoaded('items')
            ? $batch->items->count()
            : $batch->items()->count();

        return [
            'orders'          => $orders,
            'active_batch_id' => $batch->id,
        ];
    }

    public function hasGps(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInMinutes(now()) <= 5;
    }
}
