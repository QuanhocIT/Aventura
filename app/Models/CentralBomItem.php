<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralBomItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:4',
        ];
    }

    public function centralBom(): BelongsTo
    {
        return $this->belongsTo(CentralBom::class, 'central_bom_id');
    }

    public function inputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'input_ingredient_id');
    }
}
