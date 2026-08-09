<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequest extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'central_supply_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'discrepancy_flag' => 'boolean',
            'requested_delivery_date' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'to_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class, 'supply_request_id');
    }
}
