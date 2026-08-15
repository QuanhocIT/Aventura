<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductBranchStockStatus;
use App\Models\User;
use App\Notifications\ProductSoldOutNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryAvailabilityService
{
    public function __construct(private UnitConversionService $unitConversion) {}

    /**
     * Return the number of complete portions that can be produced now.
     * Stock is limited to non-expired lots and excludes active reservations.
     *
     * @return Collection<int, array<string, mixed>> keyed by product ID
     */
    public function forProducts(iterable $products, int $restaurantId, int $branchId): Collection
    {
        $products = collect($products);
        if ($products->isEmpty()) {
            return collect();
        }

        $ingredientIds = $products
            ->filter(fn (Product $product): bool => (bool) $product->track_inventory)
            ->flatMap(fn (Product $product) => $product->recipes->pluck('ingredient_id'))
            ->unique()
            ->values();

        $stock = $this->ingredientStock($restaurantId, $branchId, $ingredientIds->all());
        $reservations = $this->holdingReservations($restaurantId, $branchId, $ingredientIds->all());

        return $products->mapWithKeys(function (Product $product) use ($stock, $reservations): array {
            if (! $product->track_inventory) {
                return [$product->id => [
                    'available_portions' => null,
                    'is_sold_out' => false,
                    'bottleneck_ingredient_id' => null,
                    'bottleneck_ingredient_name' => null,
                ]];
            }

            // A tracked/sellable item without a BOM must never be presented as
            // available. It would otherwise create revenue with zero COGS and
            // silently skip inventory deduction.
            if ($product->recipes->isEmpty()) {
                return [$product->id => [
                    'available_portions' => 0,
                    'is_sold_out' => true,
                    'bottleneck_ingredient_id' => null,
                    'bottleneck_ingredient_name' => null,
                    'missing_recipe' => true,
                ]];
            }

            $requirements = $product->recipes
                ->groupBy('ingredient_id')
                ->map(fn (Collection $recipes): float => $recipes->sum(
                    fn ($recipe): float => $this->unitConversion->recipeQuantityInIngredientUnit($recipe)
                        * (1 + ((float) $recipe->waste_rate / 100))
                ));

            $availablePortions = null;
            $bottleneck = null;
            foreach ($requirements as $ingredientId => $requiredPerPortion) {
                if ($requiredPerPortion <= 0) {
                    continue;
                }

                $available = max(0.0, (float) ($stock[$ingredientId] ?? 0.0) - (float) ($reservations[$ingredientId] ?? 0.0));
                $portions = (int) floor(($available + 0.0005) / $requiredPerPortion);

                if ($availablePortions === null || $portions < $availablePortions) {
                    $ingredient = $product->recipes->firstWhere('ingredient_id', (int) $ingredientId)?->ingredient;
                    $availablePortions = $portions;
                    $bottleneck = [
                        'ingredient_id' => (int) $ingredientId,
                        'ingredient_name' => $ingredient?->name,
                        'available_quantity' => $available,
                        'required_per_portion' => $requiredPerPortion,
                    ];
                }
            }

            $availablePortions ??= 0;

            return [$product->id => [
                'available_portions' => $availablePortions,
                'is_sold_out' => $availablePortions <= 0,
                'bottleneck_ingredient_id' => $bottleneck['ingredient_id'] ?? null,
                'bottleneck_ingredient_name' => $bottleneck['ingredient_name'] ?? null,
                'available_quantity' => $bottleneck['available_quantity'] ?? 0.0,
                'required_per_portion' => $bottleneck['required_per_portion'] ?? 0.0,
            ]];
        });
    }

    /**
     * Validate a new order before reservations are created.
     * This is the final server-side guard behind the menu's displayed count.
     */
    public function assertItemsAvailable(
        int $restaurantId,
        int $branchId,
        array $items,
        ?int $ignoreOrderId = null,
        bool $enforceRecipes = false,
    ): void {
        $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->whereIn('id', $productIds)
            ->with(['recipes.ingredient.unit'])
            ->get()
            ->keyBy('id');

        $requirements = [];
        $ingredientNames = [];
        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            if (! $product) {
                continue;
            }

            if ($product->out_of_stock_until?->isFuture()) {
                $reason = trim((string) ($product->out_of_stock_reason ?? ''));
                throw new \RuntimeException(
                    'Món "'.$product->name.'" đang tạm ngưng phục vụ đến hết ngày hôm nay.'
                    .($reason !== '' ? ' Lý do: '.$reason : '')
                );
            }

            if (! $product->track_inventory) {
                continue;
            }

            if ($product->recipes->isEmpty()) {
                // Pending/offline orders may be created before the recipe is
                // completed. InventoryService enforces this at payment so
                // revenue can never be recorded without a BOM.
                if (! $enforceRecipes) {
                    continue;
                }

                throw new \RuntimeException(
                    'Món "'.$product->name.'" chưa có công thức định lượng. Không thể bán cho đến khi thiết lập đầy đủ BOM.'
                );
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            foreach ($product->recipes as $recipe) {
                $required = $this->unitConversion->recipeQuantityInIngredientUnit($recipe)
                    * $quantity
                    * (1 + ((float) $recipe->waste_rate / 100));
                $requirements[$recipe->ingredient_id] = ($requirements[$recipe->ingredient_id] ?? 0.0) + $required;
                $ingredientNames[$recipe->ingredient_id] = $recipe->ingredient?->name ?? 'nguyên liệu';
            }
        }

        if ($requirements === []) {
            return;
        }

        $stock = $this->ingredientStock($restaurantId, $branchId, array_keys($requirements), true);
        $reservations = $this->holdingReservations($restaurantId, $branchId, array_keys($requirements), $ignoreOrderId, true);

        foreach ($requirements as $ingredientId => $required) {
            $available = max(0.0, (float) ($stock[$ingredientId] ?? 0.0) - (float) ($reservations[$ingredientId] ?? 0.0));
            if ($available + 0.0005 >= $required) {
                continue;
            }

            $ingredientName = $ingredientNames[$ingredientId] ?? 'nguyên liệu';
            $ingredient = Ingredient::withoutGlobalScopes()->with('unit')->find($ingredientId);
            $unit = $ingredient?->unit?->symbol ?? 'đơn vị';
            $shortage = max(0.0, $required - $available);

            throw new \RuntimeException(
                'Món đang chọn không đủ nguyên liệu "'.$ingredientName.'": cần '
                .number_format($required, 3).' '.$unit.', còn khả dụng '
                .number_format($available, 3).' '.$unit.', thiếu '
                .number_format($shortage, 3).' '.$unit.'.'
            );
        }
    }

    /**
     * Dispatch background job to recalculate and persist branch menu status asynchronously.
     */
    public function refreshBranchAsync(int $restaurantId, int $branchId, bool $notify = true): void
    {
        \App\Jobs\RefreshBranchInventoryJob::dispatch($restaurantId, $branchId, $notify);
    }

    /**
     * Recalculate and persist branch menu status. Notifications are only sent
     * when a product transitions from available to sold out.
     */
    public function refreshBranch(int $restaurantId, int $branchId, bool $notify = true): void
    {
        $products = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->with(['recipes.ingredient.unit'])
            ->get();

        $availability = $this->forProducts($products, $restaurantId, $branchId);
        $newlySoldOut = [];

        DB::transaction(function () use ($products, $availability, $restaurantId, $branchId, $notify, &$newlySoldOut): void {
            foreach ($products as $product) {
                $data = $availability->get($product->id, []);
                $isSoldOut = (bool) ($data['is_sold_out'] ?? false);
                $portions = (int) ($data['available_portions'] ?? 0);

                $status = ProductBranchStockStatus::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $wasSoldOut = $status?->is_sold_out;
                if ($isSoldOut && $notify && ($status === null || ! $wasSoldOut)) {
                    $newlySoldOut[] = $product;
                }

                $values = [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'available_portions' => $portions,
                    'is_sold_out' => $isSoldOut,
                ];
                if ($isSoldOut && ! $wasSoldOut) {
                    $values['sold_out_at'] = now();
                } elseif (! $isSoldOut && $wasSoldOut) {
                    $values['recovered_at'] = now();
                    $values['sold_out_at'] = null;
                }

                if ($status) {
                    $status->update($values);
                } else {
                    ProductBranchStockStatus::create($values);
                }
            }
        });

        if ($notify && $newlySoldOut !== []) {
            DB::afterCommit(function () use ($newlySoldOut, $branchId, $restaurantId): void {
                $branchName = (string) (\App\Models\RestaurantBranch::withoutGlobalScopes()->whereKey($branchId)->value('name') ?? 'hiện tại');
                $recipients = User::where('restaurant_id', $restaurantId)
                    ->where(function ($query) use ($branchId): void {
                        $query->where('branch_id', $branchId)
                            ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'owner'));
                    })
                    ->get();

                foreach ($newlySoldOut as $product) {
                    $notification = new ProductSoldOutNotification(
                        (int) $product->id,
                        (string) $product->name,
                        $branchId,
                        $branchName,
                    );
                    foreach ($recipients as $recipient) {
                        $recipient->notify($notification);
                    }
                }
            });
        }
    }

    /**
     * @param array<int, int> $ingredientIds
     * @return array<int, float>
     */
    private function ingredientStock(int $restaurantId, int $branchId, array $ingredientIds, bool $lock = false): array
    {
        if ($ingredientIds === []) {
            return [];
        }

        $batchesQuery = InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereIn('status', ['active', 'expired'])
            ->where('quantity_remaining', '>', 0);
        if ($lock) {
            $batchesQuery->lockForUpdate();
        }
        $batches = $batchesQuery->get();

        $inventoryQuery = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('ingredient_id', $ingredientIds);
        if ($lock) {
            $inventoryQuery->lockForUpdate();
        }
        $inventories = $inventoryQuery->get()->keyBy('ingredient_id');
        $today = now()->startOfDay();

        $result = [];
        foreach ($ingredientIds as $ingredientId) {
            $ingredientBatches = $batches->where('ingredient_id', $ingredientId);
            if ($ingredientBatches->isEmpty()) {
                $result[$ingredientId] = (float) ($inventories->get($ingredientId)?->quantity_on_hand ?? 0.0);
                continue;
            }

            $result[$ingredientId] = (float) $ingredientBatches
                ->filter(fn (InventoryBatch $batch): bool => $batch->status === 'active'
                    && (! $batch->expiry_date || $batch->expiry_date->gte($today)))
                ->sum(fn (InventoryBatch $batch): float => (float) $batch->quantity_remaining);
        }

        return $result;
    }

    /**
     * @param array<int, int> $ingredientIds
     * @return array<int, float>
     */
    private function holdingReservations(
        int $restaurantId,
        int $branchId,
        array $ingredientIds,
        ?int $ignoreOrderId = null,
        bool $lock = false,
    ): array {
        if ($ingredientIds === []) {
            return [];
        }

        $query = InventoryReservation::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('ingredient_id', $ingredientIds)
            ->where('status', 'holding')
            ->where('expires_at', '>', now())
            ->when($ignoreOrderId !== null, fn ($q) => $q->where('order_id', '!=', $ignoreOrderId));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->selectRaw('ingredient_id, SUM(reserved_quantity) as reserved_quantity')
            ->groupBy('ingredient_id')
            ->pluck('reserved_quantity', 'ingredient_id')
            ->map(fn ($quantity): float => (float) $quantity)
            ->all();
    }
}
