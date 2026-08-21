<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryQuarantine extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'evidence_paths' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo { return $this->belongsTo(RestaurantBranch::class, 'branch_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function batch(): BelongsTo { return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function returnItems(): HasMany { return $this->hasMany(InventoryReturnItem::class, 'quarantine_id'); }
}
