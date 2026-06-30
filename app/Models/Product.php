<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'attachable');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(MediaAsset::class, 'attachable')
            ->where('collection', 'product_image');
    }

    public function checkStockAtBranch(int $branchId): bool
    {
        if (! $this->track_inventory) {
            return true;
        }

        $recipes = $this->recipes()->get();
        if ($recipes->isEmpty()) {
            return true;
        }

        foreach ($recipes as $recipe) {
            $required = (float) $recipe->quantity * (1 + (float) $recipe->waste_rate / 100);

            $inventory = \App\Models\Inventory::where('branch_id', $branchId)
                ->where('ingredient_id', $recipe->ingredient_id)
                ->first();

            $qtyOnHand = $inventory ? (float) $inventory->quantity_on_hand : 0.0;

            if ($qtyOnHand < $required) {
                return false;
            }
        }

        return true;
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
