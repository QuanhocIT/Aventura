<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequestItem extends Model
{
    use HasFactory;

    protected $table = 'central_supply_request_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_quantity'         => 'decimal:3',
            'approved_quantity'          => 'decimal:3',
            'actual_dispatched_quantity' => 'decimal:3',
            'received_quantity'          => 'decimal:3',
            'unit_cost'                  => 'decimal:2',
            'total_cost'                 => 'decimal:2',
        ];
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * Lô hàng được chọn khi xuất kho theo FEFO.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * Vị trí lưu trữ trong kho.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    /**
     * Số lượng thực xuất (dùng actual nếu có, fallback approved).
     */
    public function getEffectiveDispatchedQuantityAttribute(): float
    {
        return (float) ($this->actual_dispatched_quantity ?? $this->approved_quantity ?? $this->requested_quantity);
    }

    /**
     * Tính sai lệch giữa xuất và nhận.
     */
    public function getShortageQuantityAttribute(): float
    {
        $dispatched = $this->effective_dispatched_quantity;
        $received   = (float) ($this->received_quantity ?? $dispatched);

        return max(0, $dispatched - $received);
    }
}
