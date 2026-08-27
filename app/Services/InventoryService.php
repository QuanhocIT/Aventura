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
use App\Models\OrderItem;
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
        // Queue retries and webhook retries must be idempotent. A committed
        // usage ledger means this order has already consumed its BOM.
        if (InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('order_id', $order->id)
            ->where('type', 'usage')
            ->where('direction', 'out')
            ->exists()) {
            return;
        }

        $order->load(['items.product.recipes.unit', 'items.product.recipes.ingredient.unit']);

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($item->status === 'cancelled' || ! $product?->track_inventory) {
                continue;
            }
            if ($product->recipes->isEmpty()) {
                throw new \RuntimeException(
                    'Món "'.$product->name.'" chưa có công thức định lượng. Không thể ghi nhận bán hàng.'
                );
            }
        }

        $ingredientIds = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($item->status !== 'cancelled' && $product && $product->track_inventory) {
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
            if ($item->status !== 'cancelled' && $product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $recipeQuantity = app(UnitConversionService::class)
                        ->recipeQuantityInIngredientUnit($recipe);
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
                    $oldTheoretical = $inventory->effectiveTheoreticalQuantity();

                    // POS records the actual sale even when physical stock is
                    // insufficient. The negative balance is tracked by the
                    // Inventory observer and must be resolved through the
                    // negative-stock workflow.
                    $this->ensureLegacyBatchForInventory($inventory);
                    $batchConsumption = $this->consumeBatches(
                        $order->restaurant_id,
                        $order->branch_id,
                        $recipe->ingredient_id,
                        $totalUsed,
                        false,
                        $recipe->ingredient->name,
                        true,
                    );
                    $totalCost = $batchConsumption['total_cost'];
                    $unitCost = $totalUsed > 0
                        ? $totalCost / $totalUsed
                        : (float) ($recipe->ingredient->average_cost ?? 0);

                    $shortageNote = $batchConsumption['shortage_quantity'] > 0
                        ? " | Thiếu ghi nhận: {$batchConsumption['shortage_quantity']}"
                        : '';

                    // Giữ nguyên số âm để hệ thống có thể truy vết và xử lý.
                    $inventory->update([
                        'quantity_on_hand' => $oldQty - $totalUsed,
                        'theoretical_quantity' => $oldTheoretical - $totalUsed,
                    ]);

                    // Tạo giao dịch nhập/xuất kho (loại usage, hướng out)
                    $transaction = InventoryTransaction::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'ingredient_id' => $recipe->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'performed_by' => $user->id,
                        'type' => 'usage',
                        'direction' => 'out',
                        'quantity' => $totalUsed,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'notes' => "Khấu hao nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name}){$shortageNote}",
                        'quantity_before' => $oldQty,
                        'quantity_after' => $oldQty - $totalUsed,
                        'occurred_at' => now(),
                    ]);
                    app(NegativeInventoryService::class)->sync($inventory, $transaction);
                    $this->recordBatchAllocations($transaction, $batchConsumption['allocations'], 'out');

                    // Cập nhật trạng thái bản ghi kho đệm (inventory_reservations) từ holding sang committed
                    InventoryReservation::where('order_id', $order->id)
                        ->where('branch_id', $order->branch_id)
                        ->where('ingredient_id', $recipe->ingredient_id)
                        ->where(function ($query) use ($item): void {
                            $query->where('order_item_id', $item->id)
                                ->orWhereNull('order_item_id');
                        })
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

        $usageCost = (float) InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('order_id', $order->id)
            ->where('type', 'usage')
            ->where('direction', 'out')
            ->sum('total_cost');

        if ($usageCost > 0) {
            app(FinancialPostingService::class)->post([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'entry_date' => $order->completed_at ?? today(),
                'source_type' => Order::class,
                'source_id' => $order->id,
                'idempotency_key' => 'order:cogs:'.$order->id,
                'description' => 'Ghi nhận giá vốn đơn hàng '.$order->order_number,
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'lines' => [
                    ['account' => '6211', 'debit' => $usageCost, 'credit' => 0],
                    ['account' => '1521', 'debit' => 0, 'credit' => $usageCost],
                ],
            ]);
        }
        $this->broadcastStockUpdatedSafely($order->restaurant_id);
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
        if (! empty($data['idempotency_key']) && InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('idempotency_key', $data['idempotency_key'])
            ->exists()) {
            return;
        }

        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when(array_key_exists('branch_id', $data) && $data['branch_id'] !== null, function ($query) use ($data): void {
                $branchId = $data['branch_id'] ?? null;
                $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', (int) $branchId));
            })
            ->findOrFail($data['ingredient_id']);
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) $ingredient->branch_id;
        if (! $branchId || ($ingredient->branch_id !== null && (int) $ingredient->branch_id !== $branchId)) {
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
        if (! empty($data['expiry_date'])) {
            $expiry = \Carbon\Carbon::parse($data['expiry_date']);
            $occurredAt = ! empty($data['occurred_at']) ? \Carbon\Carbon::parse($data['occurred_at']) : now();
            if ($expiry->diffInDays($occurredAt, false) > -3) {
                throw new \InvalidArgumentException('Expiry date must be at least 3 days after receiving date.');
            }
        }

        $transactionData = [
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
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ];
        $transaction = ! empty($data['idempotency_key'])
            ? InventoryTransaction::createWithIdempotency($transactionData)
            : InventoryTransaction::create($transactionData);

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
        dispatch(new RecalculateAverageCostJob($restaurantId, $ingredient->id, $oldQty, $newQty, $newCost))->afterCommit();
        $this->broadcastStockUpdatedSafely($restaurantId);
        app(InventoryAvailabilityService::class)->refreshBranch($restaurantId, $branchId);
    }

    /**
     * Thực hiện cập nhật kho khi ghi nhận hao hụt (Waste), áp dụng khóa bi quan.
     */
    /**
     * Record all lines of a multi-ingredient purchase atomically.
     * Both the direct entry form and the approval executor use this method so
     * every line gets the same inventory, batch and allocation guarantees.
     */
    public function executePurchaseBatch(array $data, int $restaurantId, int $performedBy): void
    {
        $items = array_values($data['items'] ?? []);
        if ($items === []) {
            throw new \InvalidArgumentException('Purchase must contain at least one ingredient.');
        }

        DB::transaction(function () use ($data, $items, $restaurantId, $performedBy): void {
            foreach ($items as $lineIndex => $item) {
                $line = array_merge($data, $item, [
                    'branch_id' => $data['branch_id'] ?? null,
                    'notes' => $item['notes'] ?? $data['notes'] ?? null,
                    'idempotency_key' => ! empty($data['idempotency_key'])
                        ? trim((string) $data['idempotency_key']).':line:'.$lineIndex
                        : null,
                ]);
                unset($line['items']);

                if (($line['branch_id'] ?? null) === null) {
                    throw new \InvalidArgumentException('Purchase branch is required.');
                }

                $this->executePurchase($line, $restaurantId, $performedBy);
            }
        });
    }

    /**
     * Allocate transfer quantity across locked FEFO batches.
     * This is intentionally public so every transfer workflow uses the same
     * expired-lot and multi-batch rules as sales and waste.
     *
     * @return array{allocations: array<int, array{batch_id:int, quantity:float, unit_cost:float}>, total_cost:float, shortage_quantity:float}
     */
    public function allocateBatchesForTransfer(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        float $quantity,
        ?string $ingredientName = null,
    ): array {
        return $this->consumeBatches($restaurantId, $branchId, $ingredientId, $quantity, false, $ingredientName);
    }

    public function executeWaste(array $data, int $restaurantId, int $performedBy): ?InventoryTransaction
    {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when(array_key_exists('branch_id', $data) && $data['branch_id'] !== null, function ($query) use ($data): void {
                $branchId = $data['branch_id'] ?? null;
                $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', (int) $branchId));
            })
            ->findOrFail($data['ingredient_id']);
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) $ingredient->branch_id;
        if (! $branchId || ($ingredient->branch_id !== null && (int) $ingredient->branch_id !== $branchId)) {
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
            'waste_category' => $data['waste_category'] ?? null,
            'direction' => 'out',
            'quantity' => $wasteQty,
            'unit_cost' => $wasteUnitCost,
            'total_cost' => $wasteCost,
            'notes' => $data['notes'] ?? null,
            // Ảnh hàng hủy (bằng chứng) — tái dùng slot tệp đính kèm của giao dịch.
            'invoice_file_url' => $data['photo_url'] ?? null,
            'occurred_at' => now(),
        ]);
        $this->recordBatchAllocations($transaction, $batchConsumption['allocations'], 'out');

        // Cập nhật tồn kho (không cho xuống âm)
        $inventory->update([
            'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
            'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $wasteQty),
        ]);

        $this->broadcastStockUpdatedSafely($restaurantId);
        app(InventoryAvailabilityService::class)->refreshBranch($restaurantId, $branchId);

        return $transaction;
    }

    /**
     * Apply the stock consequence of cancelling one order item.
     *
     * Before the kitchen starts, a paid item's usage is reversed (or its
     * holding reservation is released). Once preparation has started, the
     * consumed usage is classified as order-cancellation waste so the stock
     * is not deducted twice but remains visible in loss reports.
     */
    public function handleCancelledItem(OrderItem $item, User $user, bool $wasStarted, string $reason): void
    {
        $item->loadMissing(['order', 'product.recipes.unit', 'product.recipes.ingredient.unit']);
        $order = $item->order;
        $product = $item->product;

        if (! $order || ! $product?->track_inventory || $product->recipes->isEmpty()) {
            $this->releaseItemReservations($item);

            return;
        }

        $usageTransactions = InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('branch_id', $order->branch_id)
            ->where('order_id', $order->id)
            ->where('order_item_id', $item->id)
            ->where('direction', 'out')
            ->whereIn('type', ['usage', 'waste'])
            ->lockForUpdate()
            ->get();

        // Orders paid before per-item inventory links were introduced can
        // still be reversed safely when their ledger note identifies the
        // product. New transactions always use order_item_id above.
        if ($usageTransactions->isEmpty()) {
            $usageTransactions = InventoryTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $order->restaurant_id)
                ->where('branch_id', $order->branch_id)
                ->where('order_id', $order->id)
                ->where('direction', 'out')
                ->where('type', 'usage')
                ->where('notes', 'like', '%(Món: '.$product->name.')%')
                ->lockForUpdate()
                ->get();
        }

        if (! $wasStarted) {
            foreach ($usageTransactions->where('type', 'usage') as $usageTransaction) {
                $this->restoreUsageTransaction($usageTransaction, $user, $item, $reason);
            }
            $this->releaseItemReservations($item);

            return;
        }

        // Payment may already have consumed the BOM. Reclassify that exact
        // transaction instead of creating a second outbound movement.
        $hadOutboundUsage = $usageTransactions->isNotEmpty();
        foreach ($usageTransactions->where('type', 'usage') as $usageTransaction) {
            $usageTransaction->update([
                'type' => 'waste',
                'waste_category' => 'order_cancellation',
                'notes' => trim(($usageTransaction->notes ? $usageTransaction->notes.' ' : '')
                    ."[Hủy món: {$reason}]"),
            ]);
        }

        if (! $hadOutboundUsage) {
            $this->recordCancelledItemWaste($item, $user, $reason);
        }

        $this->releaseItemReservations($item);
    }

    private function releaseItemReservations(OrderItem $item): void
    {
        InventoryReservation::where('order_id', $item->order_id)
            ->where('branch_id', $item->order?->branch_id)
            ->where('order_item_id', $item->id)
            ->whereIn('status', ['holding', 'committed'])
            ->update(['status' => 'released']);

        if ($item->order) {
            app(InventoryAvailabilityService::class)->refreshBranch(
                $item->order->restaurant_id,
                (int) $item->order->branch_id,
            );
        }
    }

    private function restoreUsageTransaction(
        InventoryTransaction $sourceTransaction,
        User $user,
        OrderItem $item,
        string $reason,
    ): void {
        $marker = "order-item-cancel-return:{$item->id}";
        if (InventoryTransaction::withoutGlobalScopes()
            ->where('order_item_id', $item->id)
            ->where('reference_code', $marker)
            ->exists()) {
            return;
        }

        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $sourceTransaction->restaurant_id)
            ->where('branch_id', $sourceTransaction->branch_id)
            ->where('ingredient_id', $sourceTransaction->ingredient_id)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            return;
        }

        $sourceAllocations = InventoryBatchAllocation::withoutGlobalScopes()
            ->where('restaurant_id', $sourceTransaction->restaurant_id)
            ->where('inventory_transaction_id', $sourceTransaction->id)
            ->where('direction', 'out')
            ->get();
        $restored = $sourceAllocations->isNotEmpty()
            ? $this->restoreBatches($sourceAllocations)
            : [
                'allocations' => [],
                'quantity' => (float) $sourceTransaction->quantity,
                'total_cost' => (float) $sourceTransaction->total_cost,
            ];

        $inventory->update([
            'quantity_on_hand' => (float) $inventory->quantity_on_hand + $restored['quantity'],
            'theoretical_quantity' => (float) $inventory->theoretical_quantity + $restored['quantity'],
        ]);

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $sourceTransaction->restaurant_id,
            'branch_id' => $sourceTransaction->branch_id,
            'ingredient_id' => $sourceTransaction->ingredient_id,
            'inventory_id' => $inventory->id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'performed_by' => $user->id,
            'type' => 'return',
            'direction' => 'in',
            'quantity' => $restored['quantity'],
            'unit_cost' => $restored['quantity'] > 0 ? $restored['total_cost'] / $restored['quantity'] : 0,
            'total_cost' => $restored['total_cost'],
            'reference_code' => $marker,
            'notes' => "Hoàn kho do hủy món {$item->product?->name} ({$reason})",
            'occurred_at' => now(),
        ]);
        $this->recordBatchAllocations($transaction, $restored['allocations'], 'in');

        $this->broadcastStockUpdatedSafely($sourceTransaction->restaurant_id);
    }

    private function recordCancelledItemWaste(OrderItem $item, User $user, string $reason): void
    {
        $order = $item->order;
        $product = $item->product;
        $ingredientIds = $product->recipes->pluck('ingredient_id')->unique()->sort()->values();
        $inventories = Inventory::where('restaurant_id', $order->restaurant_id)
            ->where('branch_id', $order->branch_id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('ingredient_id');

        foreach ($product->recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            if (! $ingredient) {
                continue;
            }

            $inventory = $inventories->get($recipe->ingredient_id);
            if (! $inventory) {
                $inventory = Inventory::create([
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'ingredient_id' => $recipe->ingredient_id,
                    'quantity_on_hand' => 0,
                    'theoretical_quantity' => 0,
                    'last_cost' => (float) $ingredient->average_cost,
                ]);
                $inventories->put($recipe->ingredient_id, $inventory);
            }

            $quantity = app(UnitConversionService::class)->recipeQuantityInIngredientUnit($recipe)
                * (float) $item->quantity
                * (1 + ((float) $recipe->waste_rate / 100));
            $this->assertSufficientStock($ingredient, (float) $inventory->quantity_on_hand, $quantity);
            $this->ensureLegacyBatchForInventory($inventory);
            $consumed = $this->consumeBatches(
                $order->restaurant_id,
                (int) $order->branch_id,
                $ingredient->id,
                $quantity,
                false,
                $ingredient->name,
            );

            $cost = $consumed['total_cost'] ?: $quantity * (float) $ingredient->average_cost;
            $transaction = InventoryTransaction::create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'ingredient_id' => $ingredient->id,
                'inventory_id' => $inventory->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'performed_by' => $user->id,
                'type' => 'waste',
                'waste_category' => 'order_cancellation',
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost' => $quantity > 0 ? $cost / $quantity : 0,
                'total_cost' => $cost,
                'reference_code' => "order-item-cancel-waste:{$item->id}",
                'notes' => "Hủy món đã bắt đầu chế biến: {$product->name} ({$reason})",
                'occurred_at' => now(),
            ]);
            $this->recordBatchAllocations($transaction, $consumed['allocations'], 'out');
            $inventory->update([
                'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $quantity),
                'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $quantity),
            ]);
        }

        $this->broadcastStockUpdatedSafely($order->restaurant_id);
    }

    /**
     * Keep the lot ledger aligned with a physical stocktake adjustment.
     * Without this, the aggregate inventory and FEFO lots could disagree
     * after an inventory count.
     */
    public function reconcileBatchesForStocktake(
        Inventory $inventory,
        float $currentQuantity,
        float $physicalQuantity,
        InventoryTransaction $transaction,
        int $performedBy,
    ): void {
        $difference = round($physicalQuantity - $currentQuantity, 3);
        if (abs($difference) <= 0.0005) {
            return;
        }

        $this->ensureLegacyBatchForInventory($inventory);

        if ($difference < 0) {
            $consumed = $this->consumeBatches(
                (int) $inventory->restaurant_id,
                (int) $inventory->branch_id,
                (int) $inventory->ingredient_id,
                abs($difference),
                true,
                $inventory->ingredient?->name,
            );
            $this->recordBatchAllocations($transaction, $consumed['allocations'], 'out');
            $quantity = abs($difference);
            $transaction->update([
                'unit_cost' => $quantity > 0 ? $consumed['total_cost'] / $quantity : 0,
                'total_cost' => $consumed['total_cost'],
            ]);

            return;
        }

        $batch = InventoryBatch::create([
            'restaurant_id' => $inventory->restaurant_id,
            'branch_id' => $inventory->branch_id,
            'ingredient_id' => $inventory->ingredient_id,
            'batch_number' => 'ADJ-'.$transaction->id,
            'quantity_remaining' => $difference,
            'unit_cost' => (float) $inventory->last_cost,
            'purchased_at' => now()->toDateString(),
            'expiry_date' => null,
            'status' => 'active',
            'reconciled_at' => now(),
            'reconciled_by' => $performedBy,
        ]);

        $this->recordBatchAllocations($transaction, [[
            'batch_id' => $batch->id,
            'quantity' => $difference,
            'unit_cost' => (float) $inventory->last_cost,
        ]], 'in');
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
     * @return array{allocations: array<int, array{batch_id:int, quantity:float, unit_cost:float}>, total_cost:float, shortage_quantity:float}
     */
    protected function consumeBatches(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        float $quantity,
        bool $allowExpired = false,
        ?string $ingredientName = null,
        bool $allowShortage = false,
    ): array {
        $result = ['allocations' => [], 'total_cost' => 0.0, 'shortage_quantity' => 0.0];

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
        if ($available + 0.0005 < $quantity && ! $allowShortage) {
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

        $result['shortage_quantity'] = round(max(0, $remaining), 3);

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
        $order->load(['items.product.recipes.unit', 'items.product.recipes.ingredient.unit']);

        if (InventoryTransaction::withoutGlobalScopes()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('order_id', $order->id)
            ->where('type', 'adjustment')
            ->where('direction', 'in')
            ->where('notes', 'like', 'Hoàn kho nguyên vật liệu%')
            ->exists()) {
            return;
        }

        $ingredientIds = [];
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($item->status !== 'cancelled' && $product && $product->track_inventory) {
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
            if ($item->status !== 'cancelled' && $product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $recipeQuantity = app(UnitConversionService::class)
                        ->recipeQuantityInIngredientUnit($recipe);
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
        $this->broadcastStockUpdatedSafely($order->restaurant_id);
        app(InventoryAvailabilityService::class)->refreshBranch($order->restaurant_id, (int) $order->branch_id);
    }

    /**
     * Realtime is an optional notification channel. It must never roll back
     * a successful inventory/payment transaction when Reverb is unavailable.
     */
    private function broadcastStockUpdatedSafely(int $restaurantId): void
    {
        try {
            event(new ProductStockUpdated($restaurantId));
        } catch (\Throwable $e) {
            Log::warning('Realtime stock broadcast skipped.', [
                'restaurant_id' => $restaurantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
