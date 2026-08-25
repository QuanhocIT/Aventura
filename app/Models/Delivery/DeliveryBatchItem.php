<?php

namespace App\Models\Delivery;

use App\Models\Concerns\HasBranch;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryBatchItem extends Model
{
    use HasBranch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'eta' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DeliveryBatch::class, 'batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryDetail(): HasOne
    {
        return $this->hasOne(DeliveryDetail::class, 'order_id', 'order_id');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['delivered', 'failed']);
    }
}
