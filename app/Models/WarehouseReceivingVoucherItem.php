<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceivingVoucherItem extends Model
{
    use HasFactory;

    protected $table = 'warehouse_receiving_voucher_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:3',
            'actual_qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'manufactured_date' => 'date',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceivingVoucher::class, 'voucher_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function discrepancyQty(): float
    {
        return (float) $this->actual_qty - (float) $this->expected_qty;
    }

    public function isOk(): bool
    {
        return $this->item_status === 'ok';
    }
}
