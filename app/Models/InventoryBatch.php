<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'stored_at' => 'datetime',
            'expiry_date' => 'date',
            'reconciled_at' => 'datetime',
            'locked_at' => 'datetime',
            'recall_requested_at' => 'datetime',
            'quantity_remaining' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryBatchAllocation::class, 'inventory_batch_id');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 3): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());
    }

    protected static function booted(): void
    {
        $cleaner = function (InventoryBatch $batch) {
            if (array_key_exists('batch_code', $batch->attributes)) {
                $code = $batch->attributes['batch_code'];
                if (! empty($code) && empty($batch->attributes['batch_number'])) {
                    $batch->attributes['batch_number'] = $code;
                }
                unset($batch->attributes['batch_code']);
            }
        };

        static::creating($cleaner);
        static::saving($cleaner);
    }

    protected function batchCode(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['batch_number'] ?? null,
            set: fn ($value) => ['batch_number' => $value],
        );
    }

    public function getBatchCodeAttribute(): ?string
    {
        return $this->attributes['batch_number'] ?? null;
    }

    public function getBatchNumberAttribute(): ?string
    {
        return $this->attributes['batch_number'] ?? null;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(now()->startOfDay());
    }
}
