<?php

namespace App\Services;

use App\Models\CentralBom;
use App\Models\CentralBomItem;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
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
        return DB::transaction(function () use ($restaurantId, $data, $creator) {
            $bomCode = 'BOM-'.strtoupper(substr(md5(uniqid()), 0, 6));

            $bom = CentralBom::create([
                'restaurant_id'           => $restaurantId,
                'output_ingredient_id'   => $data['output_ingredient_id'],
                'bom_code'                => $data['bom_code'] ?? $bomCode,
                'name'                    => $data['name'],
                'standard_output_qty'     => $data['standard_output_qty'] ?? 1.0,
                'expected_yield_percent'  => $data['expected_yield_percent'] ?? 100.0,
                'allowed_wastage_percent' => $data['allowed_wastage_percent'] ?? 5.0,
                'instructions'            => $data['instructions'] ?? null,
                'created_by'              => $creator->id,
            ]);

            foreach ($data['items'] as $itemData) {
                CentralBomItem::create([
                    'central_bom_id'       => $bom->id,
                    'input_ingredient_id' => $itemData['input_ingredient_id'],
                    'required_quantity'   => $itemData['required_quantity'],
                    'unit_symbol'         => $itemData['unit_symbol'] ?? 'kg',
                    'notes'               => $itemData['notes'] ?? null,
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
        return DB::transaction(function () use ($restaurantId, $branchId, $data, $creator) {
            $code = 'WO-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (WorkOrder::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);

            $bom = null;
            if (! empty($data['central_bom_id'])) {
                $bom = CentralBom::where('restaurant_id', $restaurantId)->findOrFail($data['central_bom_id']);
            }

            $workOrder = WorkOrder::create([
                'restaurant_id'        => $restaurantId,
                'branch_id'            => $branchId,
                'central_bom_id'       => $bom?->id,
                'work_order_code'      => $code,
                'output_ingredient_id' => $data['output_ingredient_id'],
                'target_quantity'      => (float) $data['target_quantity'],
                'status'               => WorkOrder::STATUS_DRAFT,
                'production_date'      => $data['production_date'] ?? now()->toDateString(),
                'expiry_date'          => $data['expiry_date'] ?? now()->addDays(7)->toDateString(),
                'produced_by'          => $creator->id,
                'notes'                => $data['notes'] ?? null,
            ]);

            if ($bom) {
                $multiplier = (float) $data['target_quantity'] / (float) ($bom->standard_output_qty > 0 ? $bom->standard_output_qty : 1);
                foreach ($bom->items as $bomItem) {
                    $plannedQty = round((float) $bomItem->required_quantity * $multiplier, 4);
                    WorkOrderItem::create([
                        'work_order_id'       => $workOrder->id,
                        'input_ingredient_id' => $bomItem->input_ingredient_id,
                        'planned_quantity'    => $plannedQty,
                        'actual_used_quantity' => $plannedQty,
                    ]);
                }
            } elseif (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    WorkOrderItem::create([
                        'work_order_id'        => $workOrder->id,
                        'input_ingredient_id'  => $itemData['input_ingredient_id'],
                        'planned_quantity'     => (float) $itemData['planned_quantity'],
                        'actual_used_quantity'  => (float) ($itemData['actual_used_quantity'] ?? $itemData['planned_quantity']),
                        'batch_id'             => $itemData['batch_id'] ?? null,
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
        if ($workOrder->status === WorkOrder::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Đơn sản xuất này đã hoàn thành trước đó.');
        }

        return DB::transaction(function () use ($workOrder, $user, $actualYieldQty, $actualWastageQty, $usedItems) {
            $restaurantId = $workOrder->restaurant_id;
            $branchId     = $workOrder->branch_id;

            // 1. Trừ tồn kho nguyên liệu thô theo các items
            $itemsToProcess = $usedItems ?: $workOrder->items;
            $totalRawCost = 0.0;

            foreach ($itemsToProcess as $itemInput) {
                $item = $itemInput instanceof WorkOrderItem
                    ? $itemInput
                    : WorkOrderItem::where('work_order_id', $workOrder->id)->where('id', $itemInput['id'])->firstOrFail();

                $actualUsed = is_array($itemInput) ? (float) ($itemInput['actual_used_quantity'] ?? $item->planned_quantity) : (float) $item->actual_used_quantity;
                $batchId    = is_array($itemInput) ? ($itemInput['batch_id'] ?? $item->batch_id) : $item->batch_id;

                $ingredient = Ingredient::findOrFail($item->input_ingredient_id);
                $unitCost   = (float) ($ingredient->average_cost ?? 0);
                $lineCost   = round($unitCost * $actualUsed, 2);
                $totalRawCost += $lineCost;

                $item->update([
                    'actual_used_quantity' => $actualUsed,
                    'batch_id'             => $batchId,
                    'unit_cost'            => $unitCost,
                    'total_cost'           => $lineCost,
                ]);

                // Deduction from Central Inventory
                $inventory = Inventory::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $item->input_ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || (float) $inventory->quantity_on_hand < $actualUsed) {
                    $onHand = $inventory ? $inventory->quantity_on_hand : 0;
                    throw new InvalidArgumentException("Tồn kho nguyên liệu #{$ingredient->name} chỉ còn {$onHand}, không đủ để sơ chế {$actualUsed}.");
                }

                $inventory->decrement('quantity_on_hand', $actualUsed);

                if ($batchId) {
                    $batch = InventoryBatch::lockForUpdate()->find($batchId);
                    if ($batch) {
                        $batch->decrement('quantity_remaining', $actualUsed);
                    }
                }

                // Ledger Transaction (Raw Deduction)
                InventoryTransaction::createWithIdempotency([
                    'restaurant_id'   => $restaurantId,
                    'branch_id'       => $branchId,
                    'ingredient_id'   => $item->input_ingredient_id,
                    'inventory_id'    => $inventory->id,
                    'performed_by'    => $user->id,
                    'type'            => 'use',
                    'direction'       => 'out',
                    'quantity'        => $actualUsed,
                    'unit_cost'       => $unitCost,
                    'total_cost'      => $lineCost,
                    'source_type'     => 'work_order',
                    'source_id'       => $workOrder->id,
                    'idempotency_key' => "wo_{$workOrder->id}_deduct_item_{$item->id}",
                    'notes'           => "Xuất sơ chế sản xuất theo đơn {$workOrder->work_order_code}",
                    'occurred_at'     => now(),
                ]);
            }

            // 2. Tính Tỷ lệ Thu hồi (Yield %)
            $yieldPercent = $workOrder->target_quantity > 0 ? round(($actualYieldQty / $workOrder->target_quantity) * 100, 2) : 100;
            $unitCostOutput = $actualYieldQty > 0 ? round($totalRawCost / $actualYieldQty, 2) : 0;

            // 3. Tạo Lô Bán Thành Phẩm Mới (New WIP Batch)
            $batchCode = 'WIP-'.Carbon::now()->format('Ymd').'-'.strtoupper(substr(md5(uniqid()), 0, 4));
            $newBatch  = InventoryBatch::create([
                'restaurant_id'      => $restaurantId,
                'branch_id'          => $branchId,
                'ingredient_id'      => $workOrder->output_ingredient_id,
                'batch_code'         => $batchCode,
                'quantity_remaining' => $actualYieldQty,
                'unit_cost'          => $unitCostOutput,
                'purchased_at'       => now(),
                'expiry_date'        => $workOrder->expiry_date ?: now()->addDays(7),
                'status'             => 'active',
            ]);

            // 4. Cộng tồn kho Bán Thành Phẩm
            $outputInventory = Inventory::firstOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'branch_id'     => $branchId,
                    'ingredient_id' => $workOrder->output_ingredient_id,
                ],
                [
                    'quantity_on_hand' => 0,
                ]
            );
            $outputInventory->lockForUpdate();
            $outputInventory->increment('quantity_on_hand', $actualYieldQty);

            // Ledger Transaction (WIP Addition)
            InventoryTransaction::createWithIdempotency([
                'restaurant_id'   => $restaurantId,
                'branch_id'       => $branchId,
                'ingredient_id'   => $workOrder->output_ingredient_id,
                'inventory_id'    => $outputInventory->id,
                'performed_by'    => $user->id,
                'type'            => 'purchase', // Hoặc type production
                'direction'       => 'in',
                'quantity'        => $actualYieldQty,
                'unit_cost'       => $unitCostOutput,
                'total_cost'      => round($unitCostOutput * $actualYieldQty, 2),
                'source_type'     => 'work_order',
                'source_id'       => $workOrder->id,
                'idempotency_key' => "wo_{$workOrder->id}_in_wip",
                'notes'           => "Nhập kho Bán thành phẩm từ đơn sơ chế {$workOrder->work_order_code} (Lô: {$batchCode})",
                'occurred_at'     => now(),
            ]);

            // 5. Cập nhật WorkOrder
            $workOrder->update([
                'status'                 => WorkOrder::STATUS_COMPLETED,
                'actual_yield_quantity'  => $actualYieldQty,
                'actual_wastage_quantity' => $actualWastageQty ?? 0,
                'actual_yield_percent'   => $yieldPercent,
                'created_batch_code'     => $batchCode,
                'created_batch_id'       => $newBatch->id,
                'approved_by'            => $user->id,
            ]);

            return $workOrder->fresh(['outputIngredient', 'createdBatch', 'items.inputIngredient']);
        });
    }
}
