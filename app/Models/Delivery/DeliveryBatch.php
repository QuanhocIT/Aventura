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
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['dispatched', 'in_progress', 'in_transit']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['dispatched', 'in_progress', 'in_transit']);
    }

    public function getProgressPercent(): int
    {
        if ($this->relationLoaded('items')) {
            $total = $this->items->count();
            if ($total === 0) {
                return 0;
            }
            $done = $this->items->whereIn('status', ['delivered', 'failed'])->count();

            return (int) round(($done / $total) * 100);
        }
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->items()->whereIn('status', ['delivered', 'failed'])->count();

        return (int) round(($done / $total) * 100);
    }
}
