<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    protected static function newFactory(): Factory
    {
        return IngredientFactory::new();
    }
}

