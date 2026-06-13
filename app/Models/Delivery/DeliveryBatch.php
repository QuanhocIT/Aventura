<?php

namespace App\Models\Delivery;

use App\Models\Concerns\BelongsToRestaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryBatch extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'optimized_route' => 'array',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_weight_kg' => 'float',
            'estimated_distance_km' => 'float',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryBatchItem::class, 'batch_id')->orderBy('sequence_order');
    }
}
