<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yêu cầu điều chuyển hàng liên chi nhánh (định tuyến bởi Chủ, bàn giao hai bước).
 */
class StockTransferRequest extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_dispatched' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'routed_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'to_branch_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'from_branch_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
