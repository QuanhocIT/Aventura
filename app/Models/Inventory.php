<?php

namespace App\Models;

use App\Jobs\SendLowStockAlertEmail;
use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Inventory extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_counted_at' => 'datetime',
            'quantity_on_hand' => 'float',
            'theoretical_quantity' => 'float',
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

    protected static function booted()
    {
        static::updated(function (Inventory $inventory) {
            if ($inventory->wasChanged('quantity_on_hand')) {
                try {
                    $ingredient = Ingredient::withoutGlobalScopes()->find($inventory->ingredient_id);
                    if ($ingredient && (float) $inventory->quantity_on_hand < (float) $ingredient->min_stock_level) {
                        $restaurantId = $inventory->restaurant_id;

                        $cooldown = Cache::has("low_stock_cooldown:{$restaurantId}");
                        $pending = Cache::has("low_stock_pending:{$restaurantId}");

                        if (! $cooldown && ! $pending) {
                            Cache::put("low_stock_pending:{$restaurantId}", true, 1800);
                            SendLowStockAlertEmail::dispatch($restaurantId)->delay(now()->addMinutes(30));
                            Log::info("InventoryObserver: Dispatched SendLowStockAlertEmail with 30m delay for restaurant {$restaurantId}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('InventoryObserver error: '.$e->getMessage());
                }
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return InventoryFactory::new();
    }
}
