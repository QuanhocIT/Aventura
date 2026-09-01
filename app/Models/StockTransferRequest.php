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
        'request_group_id' => 'string',
        'quantity_requested' => 'decimal:3',
        'quantity_dispatched' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'quantity_received_good' => 'decimal:3',
        'quantity_received_damaged' => 'decimal:3',
        'quantity_received_expired' => 'decimal:3',
        'discrepancy_quantity' => 'decimal:3',
        'dispatch_unit_cost' => 'decimal:2',
        'routed_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'discrepancy_resolved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'disposition_at' => 'datetime',
        'transport_temperature_min_c' => 'decimal:2',
        'transport_temperature_max_c' => 'decimal:2',
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

    public function routedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'routed_by');
    }

    public function discrepancyResolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discrepancy_resolved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'source_batch_id');
    }

    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'destination_batch_id');
    }

    public function quarantine(): BelongsTo
    {
        return $this->belongsTo(InventoryQuarantine::class, 'quarantine_id');
    }

    public function dispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by');
    }
}
