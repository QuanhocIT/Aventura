<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryManifest extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    const STATUS_DRAFT      = 'draft';
    const STATUS_PREPARING  = 'preparing';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';

    protected function casts(): array
    {
        return [
            'scheduled_dispatch_at' => 'datetime',
            'dispatched_at'         => 'datetime',
            'completed_at'          => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'from_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryManifestItem::class, 'delivery_manifest_id');
    }
}
