<?php

namespace App\Services;

use App\Events\Customer\ProductStockUpdated;
use App\Jobs\RecalculateAverageCostJob;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\RequestForProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryService
{
    /**
     * Khấu trừ kho vật lý (BOM) cho đơn hàng, áp dụng khóa bi quan.
     */
    public function deductInventoryForOrder(Order $order, User $user): void
    {
        $order->load(['items.product.recipes.ingredient.unit']);

        $ingredientIds = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $ingredientIds[] = $recipe->ingredient_id;
                }
            }
        }
        $ingredientIds = array_unique($ingredientIds);
        sort($ingredientIds);

        $lockedInventories = collect();
        if (! empty($ingredientIds)) {
            $lockedInventories = Inventory::where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('ingredient_id');
        }

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $recipeQuantity = (float) $recipe->quantity;
                    $itemQuantity = (float) $item->quantity;
                    $wasteRate = (float) $recipe->waste_rate;

                    // Lượng dùng = (định lượng * số lượng bán) * (1 + tỉ lệ hao hụt / 100)
                    $totalUsed = ($recipeQuantity * $itemQuantity) * (1 + ($wasteRate / 100));

                    // Sử dụng khóa bi quan (Pessimistic Locking) từ bộ sưu tập đã được khóa hàng loạt
                    $inventory = $lockedInventories->get($recipe->ingredient_id);

                    if (! $inventory) {
                        $inventory = Inventory::create([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => $recipe->ingredient->average_cost ?? 0,
                        ]);
                        $lockedInventories->put($recipe->ingredient_id, $inventory);
                    }

                    $oldQty = (float) $inventory->quantity_on_hand;
                    $oldTheoretical = (float) $inventory->theoretical_quantity;

                    // Do not allow an order to consume more than the aggregate stock.
                    // The surrounding order transaction will roll back all payment/order
                    // changes when this exception is raised.
                    $reservedByOtherOrders = (float) InventoryReservation::withoutGlobalScopes()
                        ->where('restaurant_id', $order->restaurant_id)
                        ->where('branch_id', $order->branch_id)
                        ->where('ingredient_id', $recipe->ingredient_id)
                        ->where('status', 'holding')
                        ->where('expires_at', '>', now())
                        ->where('order_id', '!=', $order->id)
                        ->sum('reserved_quantity');
                    $this->assertSufficientStock(
                        $recipe->ingredient,
                        max(0.0, $oldQty - $reservedByOtherOrders),
                        $totalUsed,
                    );

                    $this->ensureLegacyBatchForInventory($inventory);
                    $batchConsumption = $this->consumeBatches(
                        $order->restaurant_id,
                        $order->branch_id,
                        $recipe->ingredient_id,
                        $totalUsed,
                        false,
                        $recipe->ingredient->name,
                    );
                    $totalCost = $batchConsumption['total_cost'];
                    $unitCost = $totalUsed > 0
                        ? $totalCost / $totalUsed
                        : (float) ($recipe->ingredient->average_cost ?? 0);

                    // Trừ kho vật lý và tồn lý thuyết (clamping max(0, ...))
                    $inventory->update([
                        'quantity_on_hand' => max(0.0, $oldQty - $totalUsed),
                        'theoretical_quantity' => max(0.0, $oldTheoretical - $totalUsed),
                    ]);

                    // Tạo giao dịch nhập/xuất kho (loại usage, hướng out)
                    $transaction = InventoryTransaction::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'ingredient_id' => $recipe->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'order_id' => $order->id,
                        'performed_by' => $user->id,
                        'type' => 'usage',
                        'direction' => 'out',
                        'quantity' => $totalUsed,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'notes' => "Khấu hao nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                        'occurred_at' => now(),
                    ]);
                    $this->recordBatchAllocations($transaction, $batchConsumption['allocations'], 'out');

                    // Cập nhật trạng thái bản ghi kho đệm (inventory_reservations) từ holding sang committed
                    InventoryReservation::where('order_id', $order->id)
                        ->where('branch_id', $order->branch_id)
                        ->where('ingredient_id', $recipe->ingredient_id)
                        ->where('status', 'holding')
                        ->update(['status' => 'committed']);

                    // Tự động chào thầu RFP nếu tồn kho của chi nhánh rơi xuống dưới reorder_level
                    $ingredient = $recipe->ingredient;
                    if ($ingredient && $inventory->quantity_on_hand < $ingredient->reorder_level) {
                        $this->createAutoRfpIfNecessary($order->restaurant_id, $ingredient, (float) $inventory->quantity_on_hand);
                    }
                }
            }
        }
        event(new ProductStockUpdated($order->restaurant_id));
        app(InventoryAvailabilityService::class)->refreshBranch($order->restaurant_id, (int) $order->branch_id);
    }

    /**
     * Giải phóng các bản ghi giữ kho đệm (inventory_reservations)
     */
    public function releaseInventoryReservations(Order $order): void
    {
        InventoryReservation::where('order_id', $order->id)
            ->where('branch_id', $order->branch_id)
            ->where('status', 'holding')
            ->update(['status' => 'released']);

        app(InventoryAvailabilityService::class)->refreshBranch($order->restaurant_id, (int) $order->branch_id);
    }

    /**
     * Thực hiện cập nhật kho khi mua hàng (Purchase), áp dụng khóa bi quan.
     */
    public function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) $ingredient->branch_id;
        if (! $branchId || (int) $ingredient->branch_id !== $branchId) {
            throw new \RuntimeException('Nguyên liệu không thuộc chi nhánh nghiệp vụ hiện tại.');
        }

        // Áp dụng lockForUpdate để tránh tranh chấp khi cộng kho mua hàng đồng thời
        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('ingredient_id', $ingredient->id)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $inventory = Inventory::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'quantity_on_hand' => 0,
                'theoretical_quantity' => 0,
                'last_cost' => 0,
            ]);
        }

        $newQty = (float) $data['quantity'];
        $newCost = (float) $data['unit_cost'];
        $oldQty = (float) $inventory->quantity_on_hand;
        $this->ensureLegacyBatchForInventory($inventory);

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredient->id,
            'inventory_id' => $inventory->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'performed_by' => $performedBy,
            'type' => 'purchase',
            'direction' => 'in',
            'quantity' => $newQty,
            'unit_cost' => $newCost,
            'total_cost' => $newQty * $newCost,
            'invoice_file_url' => $data['invoice_file_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        $inventory->update([
            'quantity_on_hand' => $oldQty + $newQty,
            'theoretical_quantity' => $inventory->theoretical_quantity + $newQty,
            'last_cost' => $newCost,
        ]);

        $batch = InventoryBatch::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredient->id,
            'batch_number' => $this->resolveBatchNumber($data),
            'quantity_remaining' => $newQty,
            'unit_cost' => $newCost,
            'purchased_at' => $data['occurred_at'] ?? now(),
            'expiry_date' => $data['expiry_date'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'status' => 'active',
        ]);
        $this->recordBatchAllocations($transaction, [[
            'batch_id' => $batch->id,
            'quantity' => $newQty,
            'unit_cost' => $newCost,
        ]], 'in');

        // Đẩy tác vụ tính toán lại average_cost sang Queue ngầm để tối ưu hiệu năng
        dispatch(new RecalculateAverageCostJob($restaurantId, $ingredient->id, $oldQty, $newQty, $newCost));
        event(new ProductStockUpdated($restaurantId));
        app(InventoryAvailabilityService::class)->refreshBranch($restaurantId, $branchId);
    }

    /**
     * Thực hiện cập nhật kho khi ghi nhận hao hụt (Waste), áp dụng khóa bi quan.
     */
    public function executeWaste(array $data, int $restaurantId, int $performedBy): ?InventoryTransaction
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) $ingredient->branch_id;
        if (! $branchId || (int) $ingredient->branch_id !== $branchId) {
            throw new \RuntimeException('Nguyên liệu không thuộc chi nhánh nghiệp vụ hiện tại.');
        }

        // Áp dụng lockForUpdate khi trừ kho hao hụt
        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('ingredient_id', $ingredient->id)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        // Nếu chưa có inventory record, tạo record rỗng để đảm bảo tính nhất quán
        // (không tạo orphaned transaction với inventory_id = null)
        if (! $inventory) {
            $inventory = Inventory::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'quantity_on_hand' => 0,
                'theoretical_quantity' => 0,
                'last_cost' => (float) $ingredient->average_cost,
            ]);
        }

        $wasteQty = (float) $data['quantity'];
        $this->assertSufficientStock($ingredient, (float) $inventory->quantity_on_hand, $wasteQty);
        $this->ensureLegacyBatchForInventory($inventory);
        $batchConsumption = $this->consumeBatches(
            $restaurantId,
            $branchId,
            $ingredient->id,
            $wasteQty,
            ($data['waste_category'] ?? null) === 'expired',
            $ingredient->name,
        );
        $wasteCost = $batchConsumption['total_cost'];
        $wasteUnitCost = $wasteQty > 0
            ? $wasteCost / $wasteQty
            : (float) $ingredient->average_cost;

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredient->id,
            'inventory_id' => $inventory->id,
            'performed_by' => $performedBy,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => $wasteQty,
            'unit_cost' => $wasteUnitCost,
            'total_cost' => $wasteCost,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => now(),
        ]);
        $this->recordBatchAllocations($transaction, $batchConsumption['allocations'], 'out');

        // Cập nhật tồn kho (không cho xuống âm)
        $inventory->update([
            'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
        ]);

        event(new ProductStockUpdated($restaurantId));
        app(InventoryAvailabilityService::class)->refreshBranch($restaurantId, $branchId);

        return $transaction;
    }

    /**
     * Tự động tạo RFP nếu tồn kho chạm ngưỡng tái đặt thầu.
     */
    /**
     * Convert any pre-lot aggregate balance into a visible, undated legacy lot.
     * This prevents a new dated purchase from hiding older stock outside FEFO.
     */
    public function ensureLegacyBatchForInventory(Inventory $inventory): void
    {
        $batchQuery = InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $inventory->restaurant_id)
            ->where('ingredient_id', $inventory->ingredient_id);

        if ($inventory->branch_id === null) {
            $batchQuery->whereNull('branch_id');
        } else {
            $batchQuery->where('branch_id', $inventory->branch_id);
        }

        $batchedQuantity = (float) $batchQuery
            ->whereIn('status', ['active', 'expired'])
            ->sum('quantity_remaining');
        $missingQuantity = round((float) $inventory->quantity_on_hand - $batchedQuantity, 3);

        if ($missingQuantity <= 0) {
            return;
        }

        InventoryBatch::create([
            'restaurant_id' => $inventory->restaurant_id,
            'branch_id' => $inventory->branch_id,
            'ingredient_id' => $inventory->ingredient_id,
            'batch_number' => 'LEGACY-'.$inventory->id,
            'quantity_remaining' => $missingQuantity,
            'unit_cost' => (float) $inventory->last_cost,
            'purchased_at' => now()->toDateString(),
            'expiry_date' => null,
            'supplier_id' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Return a stable lot identifier when the supplier did not provide one.
     */
    protected function resolveBatchNumber(array $data): string
    {
        $provided = trim((string) ($data['batch_number'] ?? ''));

        return $provided !== ''
            ? $provided
            : 'LOT-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(4));
    }

    /**
     * Consume stock by FEFO: dated lots first, then undated lots, with the
     * purchase date as the FIFO tie-breaker. Expired lots are never used for
     * sales; they can only be consumed by an explicitly expired waste entry.
     *
     * Empty allocations mean the ingredient is legacy aggregate stock and the
     * caller should keep its existing aggregate fallback behaviour.
     *
     * @return array{allocations: array<int, array{batch_id:int, quantity:float, unit_cost:float}>, total_cost:float}
     */
    protected function consumeBatches(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        float $quantity,
        bool $allowExpired = false,
        ?string $ingredientName = null,
    ): array {
        $result = ['allocations' => [], 'total_cost' => 0.0];

        if ($quantity <= 0) {
            return $result;
        }

        $batches = InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->whereIn('status', ['active', 'expired'])
            ->where('quantity_remaining', '>', 0)
            ->lockForUpdate()
            ->get();

        $today = now()->startOfDay();
        foreach ($batches as $batch) {
            if ($batch->status === 'active' && $batch->expiry_date?->lt($today)) {
                $batch->update(['status' => 'expired']);
                $batch->status = 'expired';
            }
        }

        $eligible = $batches->filter(function (InventoryBatch $batch) use ($allowExpired, $today): bool {
            $isExpired = $batch->status === 'expired'
                || $batch->expiry_date?->lt($today);

            return $allowExpired || ! $isExpired;
        })->sortBy(function (InventoryBatch $batch) use ($allowExpired, $today): string {
            $isExpired = $batch->status === 'expired'
                || $batch->expiry_date?->lt($today);
            $expiryPriority = $allowExpired && $isExpired ? 0 : 1;
            $hasExpiryPriority = $batch->expiry_date ? 0 : 1;

            return sprintf(
                '%d|%d|%s|%s|%010d',
                $expiryPriority,
                $hasExpiryPriority,
                $batch->expiry_date?->toDateString() ?? '9999-12-31',
                $batch->purchased_at?->toDateString() ?? '9999-12-31',
                $batch->id,
            );
        })->values();

        // No batch rows means this is pre-lot legacy stock. Preserve the old
        // aggregate behaviour until the backfill migration has run.
        if ($batches->isEmpty()) {
            return $result;
        }

        $available = (float) $eligible->sum(fn (InventoryBatch $batch): float => (float) $batch->quantity_remaining);
        if ($available + 0.0005 < $quantity) {
            $ingredientLabel = $ingredientName !== null && trim($ingredientName) !== ''
                ? ' nguyên liệu "'.$ingredientName.'"'
                : '';

            throw new \RuntimeException(
                'Không đủ tồn kho'.$ingredientLabel.' ở các lô còn phù hợp. Có '.number_format($available, 3).' đơn vị khả dụng, cần '.number_format($quantity, 3).'.'
            );
        }

        $remaining = $quantity;
        foreach ($eligible as $batch) {
            if ($remaining <= 0.0005) {
                break;
            }

            $take = min($remaining, (float) $batch->quantity_remaining);
            $newRemaining = round((float) $batch->quantity_remaining - $take, 3);
            $batch->update([
                'quantity_remaining' => max(0, $newRemaining),
                'status' => $newRemaining <= 0.0005
                    ? 'depleted'
                    : ($batch->status === 'expired' ? 'expired' : 'active'),
            ]);

            $result['allocations'][] = [
                'batch_id' => (int) $batch->id,
                'quantity' => $take,
                'unit_cost' => (float) $batch->unit_cost,
            ];
            $result['total_cost'] += $take * (float) $batch->unit_cost;
            $remaining -= $take;
        }

        return $result;
    }

    protected function assertSufficientStock(Ingredient $ingredient, float $available, float $required): void
    {
        if ($available + 0.0005 >= $required) {
            return;
        }

        $unit = $ingredient->unit?->symbol ?? 'đơn vị';
        $shortage = max(0.0, $required - $available);

        throw new \RuntimeException(
            'Không đủ nguyên liệu "'.$ingredient->name.'": cần '
            .number_format($required, 3).' '.$unit.', tồn '
            .number_format($available, 3).' '.$unit.', thiếu '
            .number_format($shortage, 3).' '.$unit.'.'
        );
    }

    /**
     * Persist the exact lot split behind an aggregate inventory transaction.
     */
    protected function recordBatchAllocations(InventoryTransaction $transaction, array $allocations, string $direction): void
    {
        foreach ($allocations as $allocation) {
            if (($allocation['quantity'] ?? 0) <= 0) {
                continue;
            }

            InventoryBatchAllocation::create([
                'restaurant_id' => $transaction->restaurant_id,
                'branch_id' => $transaction->branch_id,
                'inventory_batch_id' => $allocation['batch_id'],
                'inventory_transaction_id' => $transaction->id,
                'direction' => $direction,
                'quantity' => $allocation['quantity'],
                'unit_cost' => $allocation['unit_cost'],
            ]);
        }
    }

    protected function createAutoRfpIfNecessary(int $restaurantId, Ingredient $ingredient, float $currentStock): void
    {
        $today = now()->format('Y-m-d');
        $title = "AI Tự động gom hàng {$today}";

        // Kiểm tra xem đã có RFP tự động nào được tạo trong ngày cho nguyên liệu này chưa
        $existingRfp = RequestForProposal::where('restaurant_id', $restaurantId)
            ->where('branch_id', $ingredient->branch_id)
            ->whereDate('created_at', now()->toDateString())
            ->where('title', 'like', 'AI Tự động gom hàng%')
            ->whereHas('items', function ($query) use ($ingredient) {
                $query->where('ingredient_name', $ingredient->name);
            })
            ->first();

        if ($existingRfp) {
            return;
        }

        // Lượng yêu cầu: (min_stock_level * 2) - current_stock
        $qtyRequired = ((float) $ingredient->min_stock_level * 2) - $currentStock;
        if ($qtyRequired <= 0) {
            return;
        }

        DB::transaction(function () use ($restaurantId, $ingredient, $title, $qtyRequired) {
            $rfp = RequestForProposal::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $ingredient->branch_id,
                'title' => $title,
                'description' => "Yêu cầu chào thầu tự động từ AI do tồn kho nguyên liệu '{$ingredient->name}' dưới ngưỡng an toàn.",
                'due_date' => now()->addDays(3),
                'status' => 'open',
            ]);

            $rfp->items()->create([
                'ingredient_name' => $ingredient->name,
                'quantity_required' => round($qtyRequired, 3),
                'unit_symbol' => $ingredient->unit?->symbol ?? 'kg',
                'notes' => 'Hệ thống tự động kích hoạt khi tồn kho chạm mức tái đặt thầu.',
            ]);
        });

        Log::info("createAutoRfpIfNecessary: Auto RFP created for ingredient {$ingredient->name}", [
            'restaurant_id' => $restaurantId,
            'ingredient_id' => $ingredient->id,
            'quantity_required' => $qtyRequired,
        ]);
    }

    /**
     * Hoàn kho nguyên vật liệu khi hủy/refund đơn hàng.
     */
    /**
     * Put an order's consumed quantities back into their original lots.
     * Expired lots remain expired after a refund and therefore cannot be sold.
     *
     * @return array{allocations: array<int, array{batch_id:int, quantity:float, unit_cost:float}>, quantity:float, total_cost:float}
     */
    protected function restoreBatches($sourceAllocations): array
    {
        $result = ['allocations' => [], 'quantity' => 0.0, 'total_cost' => 0.0];

        foreach ($sourceAllocations as $sourceAllocation) {
            $batch = InventoryBatch::withoutGlobalScopes()
                ->lockForUpdate()
                ->find($sourceAllocation->inventory_batch_id);

            if (! $batch) {
                continue;
            }

            $quantity = (float) $sourceAllocation->quantity;
            $newQuantity = round((float) $batch->quantity_remaining + $quantity, 3);
            $isExpired = $batch->expiry_date?->lt(now()->startOfDay()) || $batch->status === 'expired';
            $batch->update([
                'quantity_remaining' => $newQuantity,
                'status' => $isExpired ? 'expired' : 'active',
            ]);

            $unitCost = (float) $sourceAllocation->unit_cost;
            $result['allocations'][] = [
                'batch_id' => (int) $batch->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ];
            $result['quantity'] += $quantity;
            $result['total_cost'] += $quantity * $unitCost;
        }

        return $result;
    }

    public function restoreStockForOrder(Order $order): void
    {
        $order->load(['items.product.recipes.ingredient.unit']);

        $ingredientIds = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $ingredientIds[] = $recipe->ingredient_id;
                }
            }
        }
        $ingredientIds = array_unique($ingredientIds);

        $lockedInventories = collect();
        if (! empty($ingredientIds)) {
            $lockedInventories = Inventory::where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->whereIn('ingredient_id', $ingredientIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('ingredient_id');
        }

        $systemUser = User::where('restaurant_id', $order->restaurant_id)->first() ?? User::first();
        $userId = $systemUser ? $systemUser->id : 1;
        $usageTransactions = InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('branch_id', $order->branch_id)
            ->where('order_id', $order->id)
            ->where('type', 'usage')
            ->where('direction', 'out')
            ->get()
            ->groupBy('ingredient_id');
        $restoredUsageTransactionIds = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $recipeQuantity = (float) $recipe->quantity;
                    $itemQuantity = (float) $item->quantity;
                    $wasteRate = (float) $recipe->waste_rate;

                    $totalUsed = ($recipeQuantity * $itemQuantity) * (1 + ($wasteRate / 100));

                    $inventory = $lockedInventories->get($recipe->ingredient_id);

                    if ($inventory) {
                        $oldQty = (float) $inventory->quantity_on_hand;
                        $oldTheoretical = (float) $inventory->theoretical_quantity;
                        $sourceTransaction = $usageTransactions
                            ->get($recipe->ingredient_id, collect())
                            ->first(fn (InventoryTransaction $candidate): bool => ! in_array($candidate->id, $restoredUsageTransactionIds, true));
                        $restoreAllocations = [];

                        if ($sourceTransaction) {
                            $sourceAllocations = InventoryBatchAllocation::withoutGlobalScopes()
                                ->where('restaurant_id', $order->restaurant_id)
                                ->where('inventory_transaction_id', $sourceTransaction->id)
                                ->where('direction', 'out')
                                ->get();
                            if ($sourceAllocations->isNotEmpty()) {
                                $restored = $this->restoreBatches($sourceAllocations);
                                $totalUsed = $restored['quantity'];
                                $restoreCost = $restored['total_cost'];
                                $restoreAllocations = $restored['allocations'];
                            } else {
                                $restoreCost = $totalUsed * (float) ($recipe->ingredient->average_cost ?? 0);
                            }
                            $restoredUsageTransactionIds[] = $sourceTransaction->id;
                        } else {
                            $restoreCost = $totalUsed * (float) ($recipe->ingredient->average_cost ?? 0);
                        }

                        $inventory->update([
                            'quantity_on_hand' => $oldQty + $totalUsed,
                            'theoretical_quantity' => $oldTheoretical + $totalUsed,
                        ]);

                        $transaction = InventoryTransaction::create([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'inventory_id' => $inventory->id,
                            'order_id' => $order->id,
                            'performed_by' => $userId,
                            'type' => 'adjustment',
                            'direction' => 'in',
                            'quantity' => $totalUsed,
                            'unit_cost' => $totalUsed > 0 ? $restoreCost / $totalUsed : 0,
                            'total_cost' => $restoreCost,
                            'notes' => "Hoàn kho nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                            'occurred_at' => now(),
                        ]);
                        $this->recordBatchAllocations($transaction, $restoreAllocations, 'in');

                        InventoryReservation::where('order_id', $order->id)
                            ->where('branch_id', $order->branch_id)
                            ->where('ingredient_id', $recipe->ingredient_id)
                            ->where('status', 'committed')
                            ->update(['status' => 'released']);
                    }
                }
            }
        }
        event(new ProductStockUpdated($order->restaurant_id));
        app(InventoryAvailabilityService::class)->refreshBranch($order->restaurant_id, (int) $order->branch_id);
    }
}
