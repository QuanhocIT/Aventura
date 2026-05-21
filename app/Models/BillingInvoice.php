<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'meta' => 'array',
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
}