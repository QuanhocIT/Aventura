<?php

namespace App\Services;

use App\Events\Customer\ProductStockUpdated;
use App\Jobs\RecalculateAverageCostJob;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\RequestForProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

                    // Tự động chào thầu RFP nếu tồn kho của chi nhánh rơi xuống dưới reorder_level
                    $ingredient = $recipe->ingredient;
                    if ($ingredient && $inventory->quantity_on_hand < $ingredient->reorder_level) {
                        $this->createAutoRfpIfNecessary($order->restaurant_id, $ingredient, (float) $inventory->quantity_on_hand);
                    }
                }
            }
        }
        event(new ProductStockUpdated($order->restaurant_id));
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

        if (! $inventory) {
            $inventory = Inventory::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $ingredient->branch_id,
                'ingredient_id' => $ingredient->id,
                'quantity_on_hand' => 0,
                'theoretical_quantity' => 0,
                'last_cost' => 0,
            ]);
        }

        $newQty = (float) $data['quantity'];
        $newCost = (float) $data['unit_cost'];
        $oldQty = (float) $inventory->quantity_on_hand;

        InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $ingredient->branch_id,
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

        // Đẩy tác vụ tính toán lại average_cost sang Queue ngầm để tối ưu hiệu năng
        dispatch(new RecalculateAverageCostJob($restaurantId, $ingredient->id, $oldQty, $newQty, $newCost));
        event(new ProductStockUpdated($restaurantId));
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

        // Nếu chưa có inventory record, tạo record rỗng để đảm bảo tính nhất quán
        // (không tạo orphaned transaction với inventory_id = null)
        if (! $inventory) {
            $inventory = Inventory::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $ingredient->branch_id,
                'ingredient_id' => $ingredient->id,
                'quantity_on_hand' => 0,
                'theoretical_quantity' => 0,
                'last_cost' => (float) $ingredient->average_cost,
            ]);
        }

        $wasteQty = (float) $data['quantity'];
        $wasteCost = $wasteQty * (float) $ingredient->average_cost;

        $transaction = InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $ingredient->branch_id,
            'ingredient_id' => $ingredient->id,
            'inventory_id' => $inventory->id,
            'performed_by' => $performedBy,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => $wasteQty,
            'unit_cost' => (float) $ingredient->average_cost,
            'total_cost' => $wasteCost,
            'notes' => $data['notes'] ?? null,
            'occurred_at' => now(),
        ]);

        // Cập nhật tồn kho (không cho xuống âm)
        $inventory->update([
            'quantity_on_hand' => max(0, (float) $inventory->quantity_on_hand - $wasteQty),
        ]);

        event(new ProductStockUpdated($restaurantId));

        return $transaction;
    }

    /**
     * Tự động tạo RFP nếu tồn kho chạm ngưỡng tái đặt thầu.
     */
    protected function createAutoRfpIfNecessary(int $restaurantId, Ingredient $ingredient, float $currentStock): void
    {
        $today = now()->format('Y-m-d');
        $title = "AI Tự động gom hàng {$today}";

        // Kiểm tra xem đã có RFP tự động nào được tạo trong ngày cho nguyên liệu này chưa
        $existingRfp = RequestForProposal::where('restaurant_id', $restaurantId)
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

                        $inventory->update([
                            'quantity_on_hand' => $oldQty + $totalUsed,
                            'theoretical_quantity' => $oldTheoretical + $totalUsed,
                        ]);

                        InventoryTransaction::create([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'inventory_id' => $inventory->id,
                            'order_id' => $order->id,
                            'performed_by' => $userId,
                            'type' => 'adjustment',
                            'direction' => 'in',
                            'quantity' => $totalUsed,
                            'unit_cost' => $recipe->ingredient->average_cost ?? 0,
                            'total_cost' => $totalUsed * ($recipe->ingredient->average_cost ?? 0),
                            'notes' => "Hoàn kho nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                            'occurred_at' => now(),
                        ]);

                        InventoryReservation::where('order_id', $order->id)
                            ->where('ingredient_id', $recipe->ingredient_id)
                            ->where('status', 'committed')
                            ->update(['status' => 'released']);
                    }
                }
            }
        }
        event(new ProductStockUpdated($order->restaurant_id));
    }
}
