<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CentralWarehouseService
{
    /**
     * Get or set the Central Warehouse branch for a restaurant.
     */
    public function getCentralWarehouse(int $restaurantId): ?RestaurantBranch
    {
        $warehouse = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('is_central_warehouse', true)
            ->first();

        if (! $warehouse) {
            // Fallback: Pick the first branch as central warehouse if none marked yet
            $warehouse = RestaurantBranch::where('restaurant_id', $restaurantId)->first();
            if ($warehouse) {
                $warehouse->update(['is_central_warehouse' => true]);
            }
        }

        return $warehouse;
    }

    /**
     * Set a specific branch as the central warehouse.
     */
    public function setCentralWarehouse(int $restaurantId, int $branchId): RestaurantBranch
    {
        $branch = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('id', $branchId)
            ->firstOrFail();

        DB::transaction(function () use ($restaurantId, $branch): void {
            RestaurantBranch::where('restaurant_id', $restaurantId)
                ->update(['is_central_warehouse' => false]);

            $branch->update(['is_central_warehouse' => true]);
        });

        return $branch->fresh();
    }

    /**
     * Create a new Supply Request from a branch to Central Warehouse.
     */
    public function createSupplyRequest(int $restaurantId, int $toBranchId, User $creator, array $items, ?string $requestedDate = null, ?string $notes = null): SupplyRequest
    {
        $central = $this->getCentralWarehouse($restaurantId);
        if (! $central) {
            throw new InvalidArgumentException('Chưa thiết lập Tổng Kho cho nhà hàng.');
        }

        return DB::transaction(function () use ($restaurantId, $central, $toBranchId, $creator, $items, $requestedDate, $notes) {
            $requestCode = 'SR-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (SupplyRequest::where('restaurant_id', $restaurantId)->count() + 1), 4, '0', STR_PAD_LEFT);

            $supplyRequest = SupplyRequest::create([
                'restaurant_id' => $restaurantId,
                'request_code' => $requestCode,
                'from_branch_id' => $central->id,
                'to_branch_id' => $toBranchId,
                'created_by' => $creator->id,
                'status' => 'pending',
                'requested_delivery_date' => $requestedDate ? Carbon::parse($requestedDate) : null,
                'notes' => $notes,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($items as $itemData) {
                $ingredient = Ingredient::where('restaurant_id', $restaurantId)
                    ->where('id', $itemData['ingredient_id'])
                    ->firstOrFail();

                $unitCost = (float) ($ingredient->average_cost ?? 0);
                $qty = (float) $itemData['quantity'];
                $lineCost = round($unitCost * $qty, 2);
                $totalAmount += $lineCost;

                SupplyRequestItem::create([
                    'supply_request_id' => $supplyRequest->id,
                    'ingredient_id' => $ingredient->id,
                    'requested_quantity' => $qty,
                    'approved_quantity' => $qty, // Default to requested qty
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'unit_symbol' => $ingredient->unit?->symbol ?? 'kg',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $supplyRequest->update(['total_amount' => $totalAmount]);

            return $supplyRequest->load(['items.ingredient', 'fromBranch', 'toBranch', 'creator']);
        });
    }

    /**
     * Approve or adjust item quantities for a Supply Request by Central Warehouse.
     */
    public function approveSupplyRequest(SupplyRequest $request, User $approver, ?array $approvedItems = null, ?string $notes = null): SupplyRequest
    {
        if (! in_array($request->status, ['pending', 'draft'])) {
            throw new InvalidArgumentException('Chỉ có thể duyệt đơn ở trạng thái chờ duyệt.');
        }

        return DB::transaction(function () use ($request, $approver, $approvedItems, $notes) {
            $totalAmount = 0;

            if (! empty($approvedItems)) {
                foreach ($approvedItems as $itemApproval) {
                    $item = SupplyRequestItem::where('supply_request_id', $request->id)
                        ->where('id', $itemApproval['id'])
                        ->first();

                    if ($item) {
                        $approvedQty = (float) ($itemApproval['approved_quantity'] ?? $item->requested_quantity);
                        $lineCost = round((float) $item->unit_cost * $approvedQty, 2);
                        $item->update([
                            'approved_quantity' => $approvedQty,
                            'total_cost' => $lineCost,
                        ]);
                        $totalAmount += $lineCost;
                    }
                }
            } else {
                foreach ($request->items as $item) {
                    $approvedQty = (float) ($item->approved_quantity ?? $item->requested_quantity);
                    $lineCost = round((float) $item->unit_cost * $approvedQty, 2);
                    $item->update([
                        'approved_quantity' => $approvedQty,
                        'total_cost' => $lineCost,
                    ]);
                    $totalAmount += $lineCost;
                }
            }

            $request->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'total_amount' => $totalAmount,
                'notes' => $notes ? ($request->notes."\n[Duyệt]: ".$notes) : $request->notes,
            ]);

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'approver']);
        });
    }

    /**
     * Dispatch items from Central Warehouse (deducts central stock).
     */
    public function dispatchSupplyRequest(SupplyRequest $request, User $dispatcher, ?string $sealCode = null): SupplyRequest
    {
        if ($request->status !== 'approved') {
            throw new InvalidArgumentException('Chỉ đơn đã duyệt mới có thể xuất kho giao hàng.');
        }

        $rules = app(WarehouseGovernanceService::class)->getRules($request->restaurant_id);
        if ($rules->require_seal_code_on_dispatch && blank($sealCode) && blank($request->seal_code)) {
            throw new InvalidArgumentException('Vui lòng nhập mã niêm phong trước khi xuất kho giao hàng.');
        }

        return DB::transaction(function () use ($request, $dispatcher, $sealCode) {
            foreach ($request->items as $item) {
                $qtyToDeduct = (float) ($item->approved_quantity ?? $item->requested_quantity);

                // Deduct from Central Warehouse Inventory
                if ($request->from_branch_id) {
                    $centralInventory = Inventory::firstOrCreate(
                        [
                            'restaurant_id' => $request->restaurant_id,
                            'branch_id' => $request->from_branch_id,
                            'ingredient_id' => $item->ingredient_id,
                        ],
                        [
                            'quantity_on_hand' => 0,
                        ]
                    );

                    if ((float) $centralInventory->quantity_on_hand < $qtyToDeduct) {
                        throw new InvalidArgumentException("Tồn Kho Tổng không đủ để xuất {$qtyToDeduct} {$item->unit_symbol} cho nguyên liệu #{$item->ingredient_id}.");
                    }

                    $centralInventory->decrement('quantity_on_hand', $qtyToDeduct);

                    InventoryTransaction::create([
                        'restaurant_id' => $request->restaurant_id,
                        'branch_id' => $request->from_branch_id,
                        'ingredient_id' => $item->ingredient_id,
                        'inventory_id' => $centralInventory->id,
                        'performed_by' => $dispatcher->id,
                        'type' => 'adjustment',
                        'direction' => 'out',
                        'quantity' => $qtyToDeduct,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => $item->total_cost,
                        'notes' => "Xuất cấp phát cho chi nhánh theo đơn {$request->request_code}",
                        'occurred_at' => now(),
                    ]);
                }
            }

            $request->update([
                'status' => 'dispatched',
                'seal_code' => $sealCode ?: $request->seal_code,
                'dispatched_by' => $dispatcher->id,
                'dispatched_at' => now(),
            ]);

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'dispatcher']);
        });
    }

    /**
     * Confirm receipt of goods by Branch Manager (adds stock to Branch inventory).
     */
    public function receiveSupplyRequest(SupplyRequest $request, User $receiver, ?array $receivedItems = null): SupplyRequest
    {
        if ($request->status !== 'dispatched') {
            throw new InvalidArgumentException('Chỉ đơn đang giao mới có thể xác nhận nhận hàng.');
        }

        return DB::transaction(function () use ($request, $receiver, $receivedItems) {
            foreach ($request->items as $item) {
                $recQty = $item->approved_quantity ?? $item->requested_quantity;

                if (! empty($receivedItems)) {
                    foreach ($receivedItems as $recItem) {
                        if ($recItem['id'] == $item->id && isset($recItem['received_quantity'])) {
                            $recQty = (float) $recItem['received_quantity'];
                            break;
                        }
                    }
                }

                $approvedQty = (float) ($item->approved_quantity ?? $item->requested_quantity);
                if ($recQty > $approvedQty) {
                    throw new InvalidArgumentException("Số lượng nhận không được vượt quá số lượng Kho Tổng đã xuất cho nguyên liệu #{$item->ingredient_id}.");
                }

                $item->update(['received_quantity' => $recQty]);

                // Add to Destination Branch Inventory
                $branchInventory = Inventory::firstOrCreate(
                    [
                        'restaurant_id' => $request->restaurant_id,
                        'branch_id' => $request->to_branch_id,
                        'ingredient_id' => $item->ingredient_id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                    ]
                );

                $branchInventory->increment('quantity_on_hand', $recQty);

                InventoryTransaction::create([
                    'restaurant_id' => $request->restaurant_id,
                    'branch_id' => $request->to_branch_id,
                    'ingredient_id' => $item->ingredient_id,
                    'inventory_id' => $branchInventory->id,
                    'performed_by' => $receiver->id,
                    'type' => 'adjustment',
                    'direction' => 'in',
                    'quantity' => $recQty,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => round($item->unit_cost * $recQty, 2),
                    'notes' => "Nhập kho cấp phát từ Tổng Kho theo đơn {$request->request_code}",
                    'occurred_at' => now(),
                ]);
            }

            $request->update([
                'status' => 'completed',
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            // Trigger Governance Check to auto create disputes if goods received < dispatched
            if (! empty($receivedItems)) {
                app(WarehouseGovernanceService::class)->checkAndCreateDisputesFromSupplyRequest($request, $receivedItems);
            }

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'receiver']);
        });
    }

    /**
     * Reject a Supply Request.
     */
    public function rejectSupplyRequest(SupplyRequest $request, User $user, string $reason): SupplyRequest
    {
        if (in_array($request->status, ['completed', 'dispatched'])) {
            throw new InvalidArgumentException('Không thể từ chối đơn hàng đã xuất kho hoặc hoàn thành.');
        }

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $user->id,
        ]);

        return $request->fresh();
    }
}
