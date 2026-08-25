<?php

namespace App\Models;

use App\Jobs\SendLowStockAlertEmail;
use App\Models\Concerns\BelongsToRestaurant;
use App\Services\NegativeInventoryService;
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

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_counted_at' => 'datetime',
            'opening_balance_reconciled_at' => 'datetime',
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

    /**
     * Các reservation đang còn hiệu lực cho inventory record này.
     * Chỉ tính theo (branch_id + ingredient_id) qua scope.
     */
    public function activeReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'ingredient_id', 'ingredient_id')
            ->where('branch_id', $this->branch_id)
            ->whereNull('released_at');
    }

    /**
     * Tổng số lượng đang được giữ chỗ (chưa giải phóng, chưa hết hạn).
     */
    public function getQuantityReservedAttribute(): float
    {
        return (float) InventoryReservation::where('ingredient_id', $this->ingredient_id)
            ->where('branch_id', $this->branch_id)
            ->whereNull('released_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('quantity');
    }

    /**
     * Tồn khả dụng = quantity_on_hand - quantity_reserved.
     * Đây là số lượng thực sự có thể cấp phát thêm.
     */
    public function getQuantityAvailableAttribute(): float
    {
        return max(0, (float) $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Legacy inventory rows often have the database default 0 for the
     * theoretical balance while carrying a real opening on-hand quantity.
     * Treat that un-reconciled zero as the opening balance before applying a
     * new movement; otherwise the first movement creates a false variance.
     */
    public function effectiveTheoreticalQuantity(): float
    {
        $theoretical = (float) $this->theoretical_quantity;
        $onHand = (float) $this->quantity_on_hand;

        return $this->opening_balance_reconciled_at === null
            && abs($theoretical) < 0.0005
            && abs($onHand) > 0.0005
            ? $onHand
            : $theoretical;
    }

    protected static function booted()
    {
        static::created(function (Inventory $inventory) {
            if ((float) $inventory->quantity_on_hand < -0.0005) {
                app(NegativeInventoryService::class)->sync($inventory);
            }
        });

        static::updated(function (Inventory $inventory) {
            if ($inventory->wasChanged('quantity_on_hand')) {
                app(NegativeInventoryService::class)->sync($inventory);

                try {
                    $ingredient = Ingredient::withoutGlobalScopes()->find($inventory->ingredient_id);
                    if ($ingredient && (float) $inventory->quantity_on_hand < (float) $ingredient->min_stock_level) {
                        $restaurantId = $inventory->restaurant_id;

                        $branchId = $inventory->branch_id;
                        $cooldown = Cache::has("low_stock_cooldown:{$restaurantId}:".($branchId ?? 'all'));
                        $pending = Cache::has("low_stock_pending:{$restaurantId}:".($branchId ?? 'all'));

                        if (! $cooldown && ! $pending) {
                            Cache::put("low_stock_pending:{$restaurantId}:".($branchId ?? 'all'), true, 1800);
                            SendLowStockAlertEmail::dispatch($restaurantId, $branchId)->delay(now()->addMinutes(30));
                            Log::info("InventoryObserver: Dispatched SendLowStockAlertEmail with 30m delay for restaurant {$restaurantId}, branch ".($branchId ?? 'all'));
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
