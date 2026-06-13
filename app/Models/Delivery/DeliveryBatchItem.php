<?php

namespace App\Models\Delivery;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBatchItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeliveryBatch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryDetail(): BelongsTo
    {
        return $this->belongsTo(DeliveryDetail::class);
    }
}
