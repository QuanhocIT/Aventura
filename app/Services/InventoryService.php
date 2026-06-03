<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\InventoryReservation;
use App\Models\Ingredient;
use App\Models\User;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Khấu trừ kho vật lý (BOM) cho đơn hàng, áp dụng khóa bi quan.
     */
    public function deductInventoryForOrder(Order $order, User $user): void
    {
        $order->load(['items.product.recipes.ingredient.unit']);

        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $product->track_inventory) {
                foreach ($product->recipes as $recipe) {
                    $recipeQuantity = (float) $recipe->quantity;
                    $itemQuantity = (float) $item->quantity;
                    $wasteRate = (float) $recipe->waste_rate;

                    // Lượng dùng = (định lượng * số lượng bán) * (1 + tỉ lệ hao hụt / 100)
                    $totalUsed = ($recipeQuantity * $itemQuantity) * (1 + ($wasteRate / 100));

                    // Sử dụng khóa bi quan (Pessimistic Locking) để tránh Race Condition
                    $inventory = Inventory::where('restaurant_id', $order->restaurant_id)
                        ->where('branch_id', $order->branch_id)
                        ->where('ingredient_id', $recipe->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory) {
                        $inventory = Inventory::create([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => $recipe->ingredient->average_cost ?? 0,
                        ]);
                    }

                    $oldQty = (float) $inventory->quantity_on_hand;
                    $oldTheoretical = (float) $inventory->theoretical_quantity;

                    // Trừ kho vật lý và tồn lý thuyết (clamping max(0, ...))
                    $inventory->update([
                        'quantity_on_hand' => max(0.0, $oldQty - $totalUsed),
                        'theoretical_quantity' => max(0.0, $oldTheoretical - $totalUsed),
                    ]);

                    // Tạo giao dịch nhập/xuất kho (loại usage, hướng out)
                    InventoryTransaction::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'ingredient_id' => $recipe->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'order_id' => $order->id,
                        'performed_by' => $user->id,
                        'type' => 'usage',
                        'direction' => 'out',
                        'quantity' => $totalUsed,
                        'unit_cost' => $recipe->ingredient->average_cost ?? 0,
                        'total_cost' => $totalUsed * ($recipe->ingredient->average_cost ?? 0),
                        'notes' => "Khấu hao nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                        'occurred_at' => now(),
                    ]);

                    // Cập nhật trạng thái bản ghi kho đệm (inventory_reservations) từ holding sang committed
                    InventoryReservation::where('order_id', $order->id)
                        ->where('ingredient_id', $recipe->ingredient_id)
                        ->where('status', 'holding')
                        ->update(['status' => 'committed']);
                }
            }
        }
    }

    /**
     * Giải phóng các bản ghi giữ kho đệm (inventory_reservations)
     */
    public function releaseInventoryReservations(Order $order): void
    {
        InventoryReservation::where('order_id', $order->id)
            ->where('status', 'holding')
            ->update(['status' => 'released']);
    }

    /**
     * Thực hiện cập nhật kho khi mua hàng (Purchase), áp dụng khóa bi quan.
     */
    public function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);

        // Áp dụng lockForUpdate để tránh tranh chấp khi cộng kho mua hàng đồng thời
        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('ingredient_id', $ingredient->id)
            ->lockForUpdate()
            ->first();

        if (!$inventory) {
            $inventory = Inventory::create([
                'restaurant_id' => $restaurantId,
                'ingredient_id' => $ingredient->id,
                'quantity_on_hand' => 0,
                'theoretical_quantity' => 0,
                'last_cost' => 0
            ]);
        }

        $newQty  = (float) $data['quantity'];
        $newCost = (float) $data['unit_cost'];
        $oldQty  = (float) $inventory->quantity_on_hand;

        InventoryTransaction::create([
            'restaurant_id'    => $restaurantId,
            'ingredient_id'    => $ingredient->id,
            'inventory_id'     => $inventory->id,
            'supplier_id'      => $data['supplier_id'] ?? null,
            'performed_by'     => $performedBy,
            'type'             => 'purchase',
            'direction'        => 'in',
            'quantity'         => $newQty,
            'unit_cost'        => $newCost,
            'total_cost'       => $newQty * $newCost,
            'invoice_file_url' => $data['invoice_file_url'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'occurred_at'      => $data['occurred_at'] ?? now(),
        ]);

        $inventory->update([
            'quantity_on_hand'     => $oldQty + $newQty,
            'theoretical_quantity' => $inventory->theoretical_quantity + $newQty,
            'last_cost'            => $newCost,
        ]);

        // Đẩy tác vụ tính toán lại average_cost sang Queue ngầm để tối ưu hiệu năng
        dispatch(new \App\Jobs\RecalculateAverageCostJob($restaurantId, $ingredient->id, $oldQty, $newQty, $newCost));
    }

    /**
     * Thực hiện cập nhật kho khi ghi nhận hao hụt (Waste), áp dụng khóa bi quan.
     */
    public function executeWaste(array $data, int $restaurantId, int $performedBy): ?InventoryTransaction
    {
        $ingredient = Ingredient::withoutGlobalScopes()->findOrFail($data['ingredient_id']);

        // Áp dụng lockForUpdate khi trừ kho hao hụt
        $inventory = Inventory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('ingredient_id', $ingredient->id)
            ->lockForUpdate()
            ->first();

        $wasteQty  = (float) $data['quantity'];
        $wasteCost = $wasteQty * (float) $ingredient->average_cost;

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'ingredient_id' => $ingredient->id,
            'inventory_id'  => $inventory?->id,
            'performed_by'  => $performedBy,
            'type'          => 'waste',
            'direction'     => 'out',
            'quantity'      => $wasteQty,
            'unit_cost'     => (float) $ingredient->average_cost,
            'total_cost'    => $wasteCost,
            'notes'         => $data['notes'] ?? null,
            'occurred_at'   => now(),
        ]);

        if ($inventory) {
            $inventory->update([
                'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
            ]);
        }

        return $transaction;
    }
}
