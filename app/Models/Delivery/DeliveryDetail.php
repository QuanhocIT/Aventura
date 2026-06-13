<?php

namespace App\Models\Delivery;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryDetail extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'delivery_fee' => 'float',
            'estimated_distance_km' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function batchItem(): HasOne
    {
        return $this->hasOne(DeliveryBatchItem::class);
    }
}
