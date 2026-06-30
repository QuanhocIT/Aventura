<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_counted_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    protected static function booted(): void
    {
        static::saved(function (self $inventory): void {
            $recipes = \App\Models\ProductRecipe::where('ingredient_id', $inventory->ingredient_id)->get();

            foreach ($recipes as $recipe) {
                $product = $recipe->product;
                if ($product) {
                    $isAvailable = $product->checkStockAtBranch($inventory->branch_id);
                    event(new \App\Events\ProductStockUpdated(
                        $inventory->restaurant_id,
                        $inventory->branch_id,
                        $product->id,
                        $isAvailable
                    ));
                }
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return InventoryFactory::new();
    }
}
