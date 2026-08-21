<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReturn extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(InventoryReturnItem::class, 'return_id'); }
    public function fromBranch(): BelongsTo { return $this->belongsTo(RestaurantBranch::class, 'from_branch_id'); }
    public function toBranch(): BelongsTo { return $this->belongsTo(RestaurantBranch::class, 'to_branch_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
