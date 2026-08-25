<?php

namespace App\Services;

use App\Models\CentralBom;
use App\Models\CentralBomItem;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CentralKitchenService
{
    /**
     * Create a new Central Kitchen BOM.
     */
    public function createBom(int $restaurantId, array $data, User $creator): CentralBom
    {
        if (! $creator->isOwner() && ! $creator->isSuperAdmin()) {
            throw new InvalidArgumentException('Chỉ Chủ doanh nghiệp mới được thiết lập BOM.');
        }

        if ((int) $creator->restaurant_id !== $restaurantId) {
            throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của BOM.');
        }

        return DB::transaction(function () use ($restaurantId, $data, $creator) {
            $centralBranchId = $this->centralBranchId($restaurantId);
            $ingredientIds = collect([$data['output_ingredient_id']])
                ->merge(collect($data['items'])->pluck('input_ingredient_id'))
                ->map(fn ($id) => (int) $id)
                ->unique();
            if ($this->centralIngredientQuery($restaurantId, $centralBranchId)->whereIn('id', $ingredientIds)->count() !== $ingredientIds->count()) {
                throw new InvalidArgumentException('Nguyên liệu BOM không thuộc nhà hàng hiện tại.');
            }

            $bomCode = 'BOM-'.strtoupper(substr(md5(uniqid()), 0, 6));

            $bom = CentralBom::create([
                'restaurant_id' => $restaurantId,
                'output_ingredient_id' => $data['output_ingredient_id'],
                'bom_code' => $data['bom_code'] ?? $bomCode,
                'name' => $data['name'],
                'standard_output_qty' => $data['standard_output_qty'] ?? 1.0,
                'expected_yield_percent' => $data['expected_yield_percent'] ?? 100.0,
                'allowed_wastage_percent' => $data['allowed_wastage_percent'] ?? 5.0,
                'instructions' => $data['instructions'] ?? null,
                'created_by' => $creator->id,
            ]);

            foreach ($data['items'] as $itemData) {
                CentralBomItem::create([
                    'central_bom_id' => $bom->id,
                    'input_ingredient_id' => $itemData['input_ingredient_id'],
                    'required_quantity' => $itemData['required_quantity'],
                    'unit_symbol' => $itemData['unit_symbol'] ?? 'kg',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            return $bom->load(['outputIngredient', 'items.inputIngredient']);
        });
    }

    /**
     * Create a Work Order (Lệnh sơ chế sản xuất).
     */
    public function createWorkOrder(int $restaurantId, int $branchId, array $data, User $creator): WorkOrder
    {
        if ((int) $creator->restaurant_id !== $restaurantId) {
            throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của lệnh sơ chế.');
        }

        return DB::transaction(function () use ($restaurantId, $branchId, $data, $creator) {
            $centralBranchId = $this->centralBranchId($restaurantId);
            if ($branchId !== $centralBranchId) {
                throw new InvalidArgumentException('Lệnh sơ chế chỉ được thực hiện tại Kho Tổng.');
            }

            if (! $this->centralIngredientQuery($restaurantId, $centralBranchId)->whereKey($data['output_ingredient_id'])->exists()) {
                throw new InvalidArgumentException('Thành phẩm sơ chế không thuộc nhà hàng hiện tại.');
            }

            $code = 'WO-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (WorkOrder::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);

            $bom = null;
            if (! empty($data['central_bom_id'])) {
                $bom = CentralBom::where('restaurant_id', $restaurantId)->findOrFail($data['central_bom_id']);
            }

            $workOrder = WorkOrder::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'central_bom_id' => $bom?->id,
                'work_order_code' => $code,
                'output_ingredient_id' => $data['output_ingredient_id'],
                'target_quantity' => (float) $data['target_quantity'],
                'status' => WorkOrder::STATUS_DRAFT,
                'production_date' => $data['production_date'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? now()->addDays(7)->toDateString(),
                'produced_by' => $creator->id,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($bom) {
                $bomIngredientIds = collect([$bom->output_ingredient_id])
                    ->merge($bom->items->pluck('input_ingredient_id'))
                    ->map(fn ($id) => (int) $id)
                    ->unique();
                if ($this->centralIngredientQuery($restaurantId, $centralBranchId)->whereIn('id', $bomIngredientIds)->count() !== $bomIngredientIds->count()) {
                    throw new InvalidArgumentException('BOM được chọn có nguyên liệu ngoài phạm vi Kho Tổng.');
                }
                $multiplier = (float) $data['target_quantity'] / (float) ($bom->standard_output_qty > 0 ? $bom->standard_output_qty : 1);
                foreach ($bom->items as $bomItem) {
                    $plannedQty = round((float) $bomItem->required_quantity * $multiplier, 4);
                    WorkOrderItem::create([
                        'work_order_id' => $workOrder->id,
                        'input_ingredient_id' => $bomItem->input_ingredient_id,
                        'planned_quantity' => $plannedQty,
                        'actual_used_quantity' => $plannedQty,
                    ]);
                }
            } elseif (! empty($data['items'])) {
                $manualIngredientIds = collect($data['items'])
                    ->pluck('input_ingredient_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique();
                if ($this->centralIngredientQuery($restaurantId, $centralBranchId)
                    ->whereIn('id', $manualIngredientIds)
                    ->count() !== $manualIngredientIds->count()) {
                    throw new InvalidArgumentException('Nguyên liệu lệnh sơ chế không thuộc nhà hàng hiện tại.');
                }

                foreach ($data['items'] as $itemData) {
                    WorkOrderItem::create([
                        'work_order_id' => $workOrder->id,
                        'input_ingredient_id' => $itemData['input_ingredient_id'],
                        'planned_quantity' => (float) $itemData['planned_quantity'],
                        'actual_used_quantity' => (float) ($itemData['actual_used_quantity'] ?? $itemData['planned_quantity']),
                        'batch_id' => $itemData['batch_id'] ?? null,
                    ]);
                }
            }

            return $workOrder->load(['outputIngredient', 'items.inputIngredient', 'branch']);
        });
    }

    /**
     * Execute Work Order (Trừ kho nguyên liệu thô & Cộng kho Bán thành phẩm mới kèm Lô HSD).
     */
    public function executeWorkOrder(WorkOrder $workOrder, User $user, float $actualYieldQty, ?float $actualWastageQty = null, ?array $usedItems = null): WorkOrder
    {
        if ((int) $workOrder->restaurant_id !== (int) $user->restaurant_id) {
            throw new InvalidArgumentException('Tài khoản không thuộc nhà hàng của lệnh sơ chế.');
        }

        $centralBranchId = $this->centralBranchId($workOrder->restaurant_id);
        if ((int) $workOrder->branch_id !== $centralBranchId) {
            throw new InvalidArgumentException('Lá»‡nh sÆ¡ cháº¿ khÃ´ng thuá»™c Kho Tá»•ng.');
        }

        if ($workOrder->status === WorkOrder::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Đơn sản xuất này đã hoàn thành trước đó.');
        }

        $workOrder->loadMissing('items');
        $workOrderIngredientIds = collect([$workOrder->output_ingredient_id])
            ->merge($workOrder->items->pluck('input_ingredient_id'))
            ->map(fn ($id) => (int) $id)
            ->unique();
        if ($this->centralIngredientQuery($workOrder->restaurant_id, $centralBranchId)
            ->whereIn('id', $workOrderIngredientIds)
            ->count() !== $workOrderIngredientIds->count()) {
            throw new InvalidArgumentException('Lệnh sơ chế chứa nguyên liệu ngoài phạm vi Kho Tổng.');
        }

        return DB::transaction(function () use ($workOrder, $user, $actualYieldQty, $actualWastageQty, $usedItems) {
            $restaurantId = $workOrder->restaurant_id;
            $branchId = $workOrder->branch_id;

            // 1. Trừ tồn kho nguyên liệu thô theo các items
            $itemsToProcess = $usedItems ?: $workOrder->items;
            $totalRawCost = 0.0;

            foreach ($itemsToProcess as $itemInput) {
                $item = $itemInput instanceof WorkOrderItem
                    ? $itemInput
                    : WorkOrderItem::where('work_order_id', $workOrder->id)->where('id', $itemInput['id'])->firstOrFail();

                $actualUsed = is_array($itemInput) ? (float) ($itemInput['actual_used_quantity'] ?? $item->planned_quantity) : (float) $item->actual_used_quantity;
                $batchId = is_array($itemInput) ? ($itemInput['batch_id'] ?? $item->batch_id) : $item->batch_id;

                $ingredient = Ingredient::where('restaurant_id', $restaurantId)->findOrFail($item->input_ingredient_id);
                $unitCost = (float) ($ingredient->average_cost ?? 0);
                $lineCost = round($unitCost * $actualUsed, 2);
                $totalRawCost += $lineCost;

                $item->update([
                    'actual_used_quantity' => $actualUsed,
                    'batch_id' => $batchId,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                ]);

                // Deduction from Central Inventory
                $inventory = Inventory::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $item->input_ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $branchId,
                        'ingredient_id' => $item->input_ingredient_id,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => $unitCost,
                    ]);
                }

                $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
                $inventoryBefore = (float) $inventory->quantity_on_hand;
                $inventoryAfter = $inventoryBefore - $actualUsed;
                $theoreticalBefore = $inventory->effectiveTheoreticalQuantity();
                $inventory->update([
                    'quantity_on_hand' => $inventoryAfter,
                    'theoretical_quantity' => $theoreticalBefore - $actualUsed,
                ]);
                $shortageQuantity = max(0, -$inventoryAfter);

                if ($batchId) {
                    $batch = InventoryBatch::where('restaurant_id', $restaurantId)
                        ->where('branch_id', $branchId)
                        ->where('ingredient_id', $item->input_ingredient_id)
                        ->lockForUpdate()
                        ->find($batchId);
                    if ($batch) {
                        $batchAfter = (float) $batch->quantity_remaining - $actualUsed;
                        $batch->update([
                            'quantity_remaining' => $batchAfter,
                            'status' => $batchAfter <= 0 ? 'depleted' : $batch->status,
                        ]);
                    }
                }

                // Ledger Transaction (Raw Deduction)
                $transaction = InventoryTransaction::createWithIdempotency([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'ingredient_id' => $item->input_ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $user->id,
                    'type' => 'usage',
                    'direction' => 'out',
                    'quantity' => $actualUsed,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'source_type' => 'work_order',
                    'source_id' => $workOrder->id,
                    'idempotency_key' => "wo_{$workOrder->id}_deduct_item_{$item->id}",
                    'notes' => "Xuất sơ chế sản xuất theo đơn {$workOrder->work_order_code}"
                        .($shortageQuantity > 0 ? " | Tồn âm sau xuất: {$shortageQuantity}" : ''),
                    'quantity_before' => $inventoryBefore,
                    'quantity_after' => $inventoryAfter,
                    'occurred_at' => now(),
                ]);
                app(NegativeInventoryService::class)->sync($inventory, $transaction);
            }

            // 2. Tính Tỷ lệ Thu hồi (Yield %)
            $yieldPercent = $workOrder->target_quantity > 0 ? round(($actualYieldQty / $workOrder->target_quantity) * 100, 2) : 100;
            $unitCostOutput = $actualYieldQty > 0 ? round($totalRawCost / $actualYieldQty, 2) : 0;

            // 3. Tạo Lô Bán Thành Phẩm Mới (New WIP Batch)
            $batchCode = 'WIP-'.Carbon::now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 4));
            $newBatch = InventoryBatch::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'ingredient_id' => $workOrder->output_ingredient_id,
                'batch_number' => $batchCode,
                'quantity_remaining' => $actualYieldQty,
                'unit_cost' => $unitCostOutput,
                'purchased_at' => now(),
                'expiry_date' => $workOrder->expiry_date ?: now()->addDays(7),
                'status' => 'active',
            ]);

            // 4. Cộng tồn kho Bán Thành Phẩm
            $outputInventory = Inventory::firstOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'ingredient_id' => $workOrder->output_ingredient_id,
                ],
                [
                    'quantity_on_hand' => 0,
                ]
            );
            $outputInventory = Inventory::whereKey($outputInventory->id)->lockForUpdate()->firstOrFail();
            $outputBefore = (float) $outputInventory->quantity_on_hand;
            $outputTheoreticalBefore = $outputInventory->effectiveTheoreticalQuantity();
            $outputAfter = $outputBefore + $actualYieldQty;
            $outputInventory->update([
                'quantity_on_hand' => $outputAfter,
                'theoretical_quantity' => $outputTheoreticalBefore + $actualYieldQty,
            ]);

            // Ledger Transaction (WIP Addition)
            InventoryTransaction::createWithIdempotency([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'ingredient_id' => $workOrder->output_ingredient_id,
                'inventory_id' => $outputInventory->id,
                'performed_by' => $user->id,
                'type' => 'purchase', // Hoặc type production
                'direction' => 'in',
                'quantity' => $actualYieldQty,
                'unit_cost' => $unitCostOutput,
                'total_cost' => round($unitCostOutput * $actualYieldQty, 2),
                'source_type' => 'work_order',
                'source_id' => $workOrder->id,
                'idempotency_key' => "wo_{$workOrder->id}_in_wip",
                'notes' => "Nhập kho Bán thành phẩm từ đơn sơ chế {$workOrder->work_order_code} (Lô: {$batchCode})",
                'quantity_before' => $outputBefore,
                'quantity_after' => $outputAfter,
                'occurred_at' => now(),
            ]);

            // 5. Cập nhật WorkOrder
            $workOrder->update([
                'status' => WorkOrder::STATUS_COMPLETED,
                'actual_yield_quantity' => $actualYieldQty,
                'actual_wastage_quantity' => $actualWastageQty ?? 0,
                'actual_yield_percent' => $yieldPercent,
                'created_batch_code' => $batchCode,
                'created_batch_id' => $newBatch->id,
                'approved_by' => $user->id,
            ]);

            return $workOrder->fresh(['outputIngredient', 'createdBatch', 'items.inputIngredient']);
        });
    }

    private function centralBranchId(int $restaurantId): int
    {
        $centralBranchId = app(CentralWarehouseService::class)
            ->getCentralWarehouse($restaurantId)?->id;

        if (! $centralBranchId) {
            throw new InvalidArgumentException('ChÆ°a thiáº¿t láº­p Kho Tá»•ng.');
        }

        return (int) $centralBranchId;
    }

    private function centralIngredientQuery(int $restaurantId, int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->where(fn ($query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $centralBranchId));
    }
}
