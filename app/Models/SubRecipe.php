<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubRecipe extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    public function parentIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'parent_ingredient_id');
    }

    public function childIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'child_ingredient_id');
    }
}
