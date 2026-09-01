<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequestReceivingReportItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dispatched_quantity' => 'decimal:3',
            'submitted_received_quantity' => 'decimal:3',
            'submitted_good_quantity' => 'decimal:3',
            'submitted_damaged_quantity' => 'decimal:3',
            'submitted_expired_quantity' => 'decimal:3',
            'submitted_wrong_item_quantity' => 'decimal:3',
            'submitted_shortage_quantity' => 'decimal:3',
            'confirmed_received_quantity' => 'decimal:3',
            'confirmed_good_quantity' => 'decimal:3',
            'confirmed_damaged_quantity' => 'decimal:3',
            'confirmed_expired_quantity' => 'decimal:3',
            'confirmed_wrong_item_quantity' => 'decimal:3',
            'confirmed_shortage_quantity' => 'decimal:3',
        ];
    }

    public function receivingReport(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestReceivingReport::class, 'receiving_report_id');
    }

    public function supplyRequestItem(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestItem::class, 'supply_request_item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    public function quarantine(): BelongsTo
    {
        return $this->belongsTo(InventoryQuarantine::class, 'quarantine_id');
    }
}
