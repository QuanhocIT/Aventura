<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionAnalyticsSnapshot extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'snapshot_date' => 'date',
        'total_discount_given' => 'decimal:2',
        'total_revenue_with_promo' => 'decimal:2',
        'repeat_rate' => 'decimal:2',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
