<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReturnItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function returnOrder(): BelongsTo { return $this->belongsTo(InventoryReturn::class, 'return_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function batch(): BelongsTo { return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id'); }
    public function quarantine(): BelongsTo { return $this->belongsTo(InventoryQuarantine::class, 'quarantine_id'); }
}
