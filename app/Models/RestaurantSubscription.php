<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantSubscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewal_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'last_paid_at' => 'datetime',
            'meta' => 'array',
            'billing_meta' => 'array',
            'price' => 'decimal:0',
            'original_price' => 'decimal:0',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id')->withTrashed();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']);
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->ended_at && $this->ended_at->isPast());
    }
}
