<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryNegativeCase;
use App\Models\Product;

class InventoryReadinessService
{
    /**
     * Operational checks that must be green before a branch goes live.
     * These are deliberately read-only; reconciliation remains an audited
     * write operation in InventoryManagementController.
     *
     * @return array<string, mixed>
     */
    public function forBranch(int $restaurantId, int $branchId): array
    {
        app(NegativeInventoryService::class)->ensureCasesForNegativeBalances($restaurantId, $branchId);

        $productsWithoutRecipes = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('track_inventory', true)
            ->whereDoesntHave('recipes')
            ->count();

        $negativeStocks = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('quantity_on_hand', '<', 0)
            ->count();

        $negativeCasesOpen = InventoryNegativeCase::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['open', 'in_progress', 'pending_owner_approval', 'pending_verification'])
            ->count();

        $trackedIngredientIds = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('track_inventory', true)
            ->with('recipes:id,product_id,ingredient_id')
            ->get()
            ->flatMap(fn (Product $product) => $product->recipes->pluck('ingredient_id'))
            ->unique()
            ->values();

        $openingBalancePending = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('id', $trackedIngredientIds)
            ->where(function ($query): void {
                $query->whereDoesntHave('inventories', function ($inventoryQuery): void {
                    $inventoryQuery->whereColumn('inventories.branch_id', 'ingredients.branch_id');
                })->orWhereHas('inventories', function ($inventoryQuery): void {
                    $inventoryQuery->whereColumn('inventories.branch_id', 'ingredients.branch_id')
                        ->whereNull('opening_balance_reconciled_at');
                });
            })
            ->count();

        $legacyBatchesPending = InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('batch_number', 'like', 'LEGACY-%')
            ->where('quantity_remaining', '>', 0)
            ->whereNull('reconciled_at')
            ->count();

        return [
            'ready' => $productsWithoutRecipes === 0
                && $negativeStocks === 0
                && $negativeCasesOpen === 0
                && $openingBalancePending === 0
                && $legacyBatchesPending === 0,
            'products_without_recipes' => $productsWithoutRecipes,
            'negative_stocks' => $negativeStocks,
            'negative_cases_open' => $negativeCasesOpen,
            'opening_balance_pending' => $openingBalancePending,
            'legacy_batches_pending' => $legacyBatchesPending,
        ];
    }
}
