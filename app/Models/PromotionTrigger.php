<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionTrigger extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_generate_code' => 'boolean',
        'send_email' => 'boolean',
        'send_notification' => 'boolean',
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function customerCoupons(): HasMany
    {
        return $this->hasMany(CustomerCoupon::class, 'trigger_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
