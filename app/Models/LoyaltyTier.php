<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyTier extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_spent' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'points_multiplier' => 'decimal:2',
            'benefits_json' => 'array',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'loyalty_tier_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('min_spent');
    }
}
