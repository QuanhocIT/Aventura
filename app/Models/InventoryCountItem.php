<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'opening_quantity' => 'decimal:3',
            'inbound_quantity' => 'decimal:3',
            'outbound_quantity' => 'decimal:3',
            'inbound_value' => 'decimal:2',
            'outbound_value' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'counted_quantity_1' => 'decimal:3',
            'counted_quantity_2' => 'decimal:3',
            'final_quantity' => 'decimal:3',
            'variance_quantity' => 'decimal:3',
            'variance_percent' => 'decimal:2',
            'variance_value' => 'decimal:2',
            'reconciled_at' => 'datetime',
        ];
    }

    public function countSession(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'count_session_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
