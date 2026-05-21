<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAdjustment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(RestaurantSubscription::class, 'restaurant_subscription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}