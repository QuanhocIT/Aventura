<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentralBom extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'standard_output_qty' => 'decimal:4',
            'expected_yield_percent' => 'decimal:2',
            'allowed_wastage_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function outputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'output_ingredient_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CentralBomItem::class, 'central_bom_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
