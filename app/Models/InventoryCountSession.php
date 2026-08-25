<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCountSession extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blind_count' => 'boolean',
            'requires_owner_approval' => 'boolean',
            'total_variance_value' => 'decimal:2',
            'total_expected_quantity' => 'decimal:3',
            'total_counted_quantity' => 'decimal:3',
            'total_expected_value' => 'decimal:2',
            'total_counted_value' => 'decimal:2',
            'total_shortage_quantity' => 'decimal:3',
            'total_surplus_quantity' => 'decimal:3',
            'total_shortage_value' => 'decimal:2',
            'total_surplus_value' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function secondCountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_counted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'count_session_id');
    }
}
