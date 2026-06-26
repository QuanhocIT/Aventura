<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'expiry_date' => 'date',
            'quantity_remaining' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
