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
            'requested_quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
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
}
