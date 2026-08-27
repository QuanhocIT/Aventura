<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientSupplier;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CentralWarehouseSupplyChainService
{
    public function __construct(protected CentralWarehouseService $warehouseService) {}

    public function alerts(int $restaurantId): array
    {
        $central = $this->warehouseService->getCentralWarehouse($restaurantId);
        if (! $central) {
            return ['critical' => 0, 'warning' => 0, 'items' => []];
        }

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $central->id))
            ->with(['supplier', 'supplierOptions.supplier'])
            ->orderBy('name')
            ->get();
        $inventory = Inventory::where('restaurant_id', $restaurantId)
            ->where('branch_id', $central->id)
            ->get()
            ->keyBy('ingredient_id');
        $reserved = InventoryReservation::where('restaurant_id', $restaurantId)
            ->where('branch_id', $central->id)
            ->whereNull('released_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->selectRaw('ingredient_id, SUM(quantity) AS quantity_reserved')
            ->groupBy('ingredient_id')
            ->pluck('quantity_reserved', 'ingredient_id');

        $items = collect();
        foreach ($ingredients as $ingredient) {
            $stock = (float) ($inventory->get($ingredient->id)?->quantity_on_hand ?? 0);
            $reservedQty = (float) ($reserved->get($ingredient->id, 0));
            $available = max(0, $stock - $reservedQty);
            $safetyStock = (float) ($ingredient->safety_stock_quantity ?? 0);
            $reorderLevel = (float) ($ingredient->reorder_level ?? 0);
            $minimum = (float) ($ingredient->min_stock_level ?? 0);
            $target = max($minimum, $reorderLevel + $safetyStock);

            $options = $ingredient->supplierOptions->where('is_active', true);
            if ($options->isEmpty() && $ingredient->supplier) {
                $options = collect([(object) ['supplier_id' => $ingredient->supplier_id, 'is_primary' => true]]);
            }
            $hasBackup = $options->contains(fn ($option): bool => ! (bool) ($option->is_primary ?? false));

            if ($available < $target && $target > 0) {
                $items->push([
                    'type' => 'low_stock',
                    'severity' => $available <= 0 ? 'critical' : 'warning',
                    'ingredient_id' => $ingredient->id,
                    'ingredient_name' => $ingredient->name,
                    'sku' => $ingredient->sku,
                    'available' => round($available, 3),
                    'target' => round($target, 3),
                    'shortage' => round(max(0, $target - $available), 3),
                    'lead_time_days' => (int) ($ingredient->lead_time_days ?? 0),
                    'message' => $available <= 0
                        ? "{$ingredient->name} đã hết khả dụng tại Kho Tổng."
                        : "{$ingredient->name} đã xuống dưới mức đặt hàng lại.",
                ]);
            }

            // Tắt cảnh báo nhà cung cấp theo yêu cầu người dùng
            // if ($options->isEmpty()) {
            //     $items->push([
            //         'type' => 'no_supplier',
            //         'severity' => 'critical',
            //         'ingredient_id' => $ingredient->id,
            //         'ingredient_name' => $ingredient->name,
            //         'message' => "{$ingredient->name} chưa có nhà cung cấp hoạt động.",
            //     ]);
            // } elseif (! $hasBackup && ($ingredient->batch_tracking_required || in_array($ingredient->storage_type, ['fresh', 'daily', 'short_shelf'], true))) {
            //     $items->push([
            //         'type' => 'no_backup_supplier',
            //         'severity' => 'warning',
            //         'ingredient_id' => $ingredient->id,
            //         'ingredient_name' => $ingredient->name,
            //         'message' => "{$ingredient->name} chưa có nhà cung cấp dự phòng.",
            //     ]);
            // }
        }

        $lateOrders = PurchaseOrder::where('restaurant_id', $restaurantId)
            ->when($central, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $central->id)))
            ->whereIn('status', ['approved', 'preparing', 'shipping'])
            ->whereNotNull('delivery_due_date')
            ->where('delivery_due_date', '<', now())
            ->with('supplier:id,name')
            ->orderBy('delivery_due_date')
            ->limit(50)
            ->get();

        foreach ($lateOrders as $order) {
            $items->push([
                'type' => 'late_purchase_order',
                'severity' => 'critical',
                'purchase_order_id' => $order->id,
                'purchase_order_number' => $order->po_number,
                'supplier_name' => $order->supplier?->name,
                'message' => "PO {$order->po_number} đã quá hạn giao hàng.",
            ]);
        }

        return [
            'critical' => $items->where('severity', 'critical')->count(),
            'warning' => $items->where('severity', 'warning')->count(),
            'items' => $items->values()->all(),
        ];
    }

    public function reconciliation(int $restaurantId): array
    {
        $central = $this->warehouseService->getCentralWarehouse($restaurantId);
        if (! $central) {
            return ['has_variance' => false, 'items' => []];
        }

        $inventories = Inventory::where('restaurant_id', $restaurantId)
            ->where('branch_id', $central->id)
            ->with('ingredient:id,name,sku')
            ->get();
        $items = $inventories->map(function (Inventory $inventory) use ($restaurantId, $central): array {
            $batchQty = (float) InventoryBatch::where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('ingredient_id', $inventory->ingredient_id)
                ->where('status', 'active')
                ->sum('quantity_remaining');
            $quarantineQty = (float) InventoryBatch::where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('ingredient_id', $inventory->ingredient_id)
                ->whereIn('status', ['locked', 'expired'])
                ->sum('quantity_remaining');
            $ledgerIn = (float) InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('ingredient_id', $inventory->ingredient_id)
                ->where('direction', 'in')
                ->sum('quantity');
            $ledgerOut = (float) InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('ingredient_id', $inventory->ingredient_id)
                ->where('direction', 'out')
                ->sum('quantity');
            $onHand = (float) $inventory->quantity_on_hand;
            $variance = round($onHand - $batchQty, 3);
            $ledgerBalance = round($ledgerIn - $ledgerOut, 3);

            return [
                'ingredient_id' => $inventory->ingredient_id,
                'ingredient_name' => $inventory->ingredient?->name,
                'sku' => $inventory->ingredient?->sku,
                'on_hand' => round($onHand, 3),
                'batch_quantity' => round($batchQty, 3),
                'quarantine_quantity' => round($quarantineQty, 3),
                'ledger_balance' => $ledgerBalance,
                'batch_variance' => $variance,
                'ledger_variance' => round($onHand - $ledgerBalance, 3),
                'status' => abs($variance) > 0.001 || abs($onHand - $ledgerBalance) > 0.001 ? 'review' : 'matched',
            ];
        })->values();

        return [
            'has_variance' => $items->contains(fn (array $item): bool => $item['status'] === 'review'),
            'review_count' => $items->where('status', 'review')->count(),
            'items' => $items->all(),
        ];
    }

    public function syncSupplierOptions(int $restaurantId, int $ingredientId, array $options): Collection
    {
        $ingredient = Ingredient::where('restaurant_id', $restaurantId)->findOrFail($ingredientId);
        $supplierIds = collect($options)->pluck('supplier_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $validSupplierIds = Supplier::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->whereIn('id', $supplierIds)
            ->pluck('id');
        if ($validSupplierIds->count() !== $supplierIds->count()) {
            throw new InvalidArgumentException('Danh sách nhà cung cấp có nhà cung cấp không hợp lệ hoặc không hoạt động.');
        }

        return DB::transaction(function () use ($restaurantId, $ingredient, $options): Collection {
            IngredientSupplier::where('restaurant_id', $restaurantId)
                ->where('ingredient_id', $ingredient->id)
                ->delete();

            $hasPrimary = false;
            foreach (array_values($options) as $index => $option) {
                $isPrimary = (bool) ($option['is_primary'] ?? false);
                if ($isPrimary && $hasPrimary) {
                    $isPrimary = false;
                }
                $hasPrimary = $hasPrimary || $isPrimary;

                IngredientSupplier::create([
                    'restaurant_id' => $restaurantId,
                    'ingredient_id' => $ingredient->id,
                    'supplier_id' => (int) $option['supplier_id'],
                    'priority' => (int) ($option['priority'] ?? $index + 1),
                    'is_primary' => $isPrimary,
                    'is_active' => (bool) ($option['is_active'] ?? true),
                    'lead_time_days' => (int) ($option['lead_time_days'] ?? 0),
                    'minimum_order_quantity' => (float) ($option['minimum_order_quantity'] ?? 0),
                    'notes' => $option['notes'] ?? null,
                ]);
            }

            $primary = IngredientSupplier::where('ingredient_id', $ingredient->id)
                ->where('is_primary', true)
                ->first();
            $ingredient->update(['supplier_id' => $primary?->supplier_id]);

            return IngredientSupplier::where('ingredient_id', $ingredient->id)
                ->with('supplier:id,name,phone')
                ->orderBy('priority')
                ->get();
        });
    }
}
