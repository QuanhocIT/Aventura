<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = [
        'inventory_status',
        'system_negative',
        'system_negative_quantity',
        'system_negative_value',
    ];

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
            'revision' => 'integer',
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

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryCountEvent::class, 'count_item_id');
    }

    public function getSystemNegativeAttribute(): bool
    {
        return (float) $this->expected_quantity < -0.0005;
    }

    public function getSystemNegativeQuantityAttribute(): float
    {
        return $this->system_negative
            ? round(abs((float) $this->expected_quantity), 3)
            : 0.0;
    }

    public function getSystemNegativeValueAttribute(): float
    {
        return $this->system_negative
            ? round($this->system_negative_quantity * (float) $this->unit_cost, 2)
            : 0.0;
    }

    public function getInventoryStatusAttribute(): string
    {
        if ($this->reconciliation_status === 'pending') {
            return 'recount_required';
        }

        if ($this->final_quantity === null) {
            return 'uncounted';
        }

        if ($this->system_negative) {
            return 'negative_stock';
        }

        $variance = (float) $this->variance_quantity;
        if ($variance < -0.0005) {
            return 'shortage';
        }

        if ($variance > 0.0005) {
            return 'surplus';
        }

        return 'matched';
    }
}
