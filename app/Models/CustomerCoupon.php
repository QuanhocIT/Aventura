<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCoupon extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(PromotionTrigger::class, 'trigger_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'used_on_order_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && ! $this->isExpired();
    }
}
