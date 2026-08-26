<?php

namespace App\Services;

use App\Models\DeliveryManifest;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryCountSession;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Notifications\SupplyRequestCreatedNotification;
use App\Notifications\SupplyRequestStatusNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CentralWarehouseService
{
    /**
     * Get the active Central Warehouse branch for a restaurant.
     */
    public function getCentralWarehouse(int $restaurantId): ?RestaurantBranch
    {
        $assignment = DB::table('central_warehouse_assignments')
            ->where('restaurant_id', $restaurantId)
            ->first();

        if ($assignment) {
            return RestaurantBranch::where('restaurant_id', $restaurantId)
                ->whereKey($assignment->branch_id)
                ->where('status', 'active')
                ->first();
        }

        return RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('is_central_warehouse', true)
                    ->orWhere('warehouse_type', 'central');
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Ensure a Central Warehouse branch exists for a restaurant.
     */
    public function ensureCentralWarehouse(int $restaurantId): RestaurantBranch
    {
        $warehouse = $this->getCentralWarehouse($restaurantId);
        if ($warehouse) {
            $this->ensureCentralWarehouseAssignment($restaurantId, (int) $warehouse->id);

            return $warehouse;
        }

        $restaurant = Restaurant::findOrFail($restaurantId);

        return DB::transaction(function () use ($restaurantId, $restaurant): RestaurantBranch {
            $existing = $this->getCentralWarehouse($restaurantId);
            if ($existing) {
                $this->ensureCentralWarehouseAssignment($restaurantId, (int) $existing->id);

                return $existing;
            }

            $warehouse = RestaurantBranch::create([
                'restaurant_id' => $restaurantId,
                'code' => 'WH-CENTRAL-'.$restaurantId,
                'name' => 'Kho Tổng '.$restaurant->name,
                'status' => 'active',
                'is_central_warehouse' => true,
                'warehouse_type' => 'central',
            ]);
            $this->ensureCentralWarehouseAssignment($restaurantId, (int) $warehouse->id);

            return $warehouse;
        });
    }

    /**
     * Set a specific branch as the central warehouse and ensure uniqueness.
     */
    public function setCentralWarehouse(int $restaurantId, int $branchId): RestaurantBranch
    {
        $branch = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('id', $branchId)
            ->firstOrFail();

        if ($branch->status !== 'active') {
            throw new InvalidArgumentException('Chỉ có thể thiết lập chi nhánh đang hoạt động (active) làm Kho Tổng.');
        }

        $currentCentral = $this->getCentralWarehouse($restaurantId);
        if ($currentCentral && (int) $currentCentral->id === (int) $branch->id) {
            return $branch->fresh();
        }
        if ($currentCentral && (int) $currentCentral->id !== (int) $branch->id) {
            $this->assertBranchHasNoOperationalData($currentCentral, 'chuyển Kho Tổng sang chi nhánh khác');
        }
        $this->assertBranchHasNoOperationalData($branch, 'thiết lập làm Kho Tổng');

        DB::transaction(function () use ($restaurantId, $branch): void {
            // Reset central status for all other branches
            RestaurantBranch::where('restaurant_id', $restaurantId)
                ->where('id', '!=', $branch->id)
                ->update([
                    'is_central_warehouse' => false,
                    'warehouse_type' => 'business',
                ]);

            $branch->update([
                'is_central_warehouse' => true,
                'warehouse_type' => 'central',
            ]);

            $this->ensureCentralWarehouseAssignment($restaurantId, (int) $branch->id);
        });

        return $branch->fresh();
    }

    public function assertBranchCanBeDeactivated(RestaurantBranch $branch): void
    {
        if ($branch->is_central_warehouse || $branch->warehouse_type === 'central') {
            throw new InvalidArgumentException('Không thể vô hiệu hóa Kho Tổng đang hoạt động. Vui lòng thiết lập Kho Tổng sang chi nhánh khác trước.');
        }

        $this->assertBranchHasNoOperationalData($branch, 'vô hiệu hóa chi nhánh');
    }

    /**
     * Sinh mã yêu cầu cấp phát duy nhất chống xung đột (race condition).
     */
    public function generateUniqueRequestCode(int $restaurantId): string
    {
        $prefix = 'SR-'.$restaurantId.'-'.Carbon::now()->format('Ymd').'-';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $count = SupplyRequest::where('restaurant_id', $restaurantId)
                ->whereDate('created_at', Carbon::today())
                ->count() + 1 + $attempt;
            $candidate = $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            if (! SupplyRequest::where('restaurant_id', $restaurantId)->where('request_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Create a new Supply Request from a branch to Central Warehouse.
     */
    public function createSupplyRequest(
        int $restaurantId,
        int $toBranchId,
        User $creator,
        array $items,
        ?string $requestedDate = null,
        ?string $notes = null,
        ?string $overlimitReason = null
    ): SupplyRequest {
        if (! $creator->isSuperAdmin() && (int) $creator->restaurant_id !== $restaurantId) {
            throw new InvalidArgumentException('Bạn không thể tạo đơn cấp phát cho nhà hàng khác.');
        }

        if (! $creator->isSuperAdmin()
            && ! $creator->isOwner()
            && ! $creator->canAccessBranch($toBranchId)
            && ! $creator->can('supply_requests.create')) {
            throw new InvalidArgumentException('Tài khoản không thuộc phạm vi chi nhánh nhận hàng của đơn cấp phát.');
        }

        $central = $this->getCentralWarehouse($restaurantId);
        if (! $central) {
            throw new InvalidArgumentException('Chưa thiết lập Tổng Kho cho nhà hàng.');
        }

        if ($toBranchId === (int) $central->id) {
            throw new InvalidArgumentException('Kho Tổng độc lập không thể là chi nhánh nhận hàng.');
        }

        $branch = RestaurantBranch::where('restaurant_id', $restaurantId)->findOrFail($toBranchId);
        if ($branch->status !== 'active') {
            throw new InvalidArgumentException("Chi nhánh nhận '{$branch->name}' đang không ở trạng thái hoạt động.");
        }

        $this->assertCentralIngredients(
            $restaurantId,
            (int) $central->id,
            collect($items)->pluck('ingredient_id')
        );

        // ── Anti-Duplicate Check ───────────────────────────────────────────────
        $recentDuplicate = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('to_branch_id', $toBranchId)
            ->where('created_by', $creator->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereIn('status', ['pending', 'approved', 'preparing'])
            ->first();

        if ($recentDuplicate) {
            throw new InvalidArgumentException("Đã có yêu cầu cấp phát #{$recentDuplicate->request_code} vừa tạo trong vòng 10 phút qua. Vui lòng kiểm tra lại để tránh trùng lặp.");
        }

        // ── Kiểm tra định mức cấp phát tháng của chi nhánh ─────────────────────
        $startOfMonth = now()->startOfMonth();
        $monthlySpent = (float) SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('to_branch_id', $toBranchId)
            ->where('created_at', '>=', $startOfMonth)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->sum('total_amount');

        return DB::transaction(function () use ($restaurantId, $central, $toBranchId, $creator, $items, $requestedDate, $notes, $branch, $monthlySpent, $overlimitReason) {
            $requestCode = $this->generateUniqueRequestCode($restaurantId);

            $supplyRequest = SupplyRequest::create([
                'restaurant_id' => $restaurantId,
                'request_code' => $requestCode,
                'from_branch_id' => $central->id,
                'to_branch_id' => $toBranchId,
                'created_by' => $creator->id,
                'status' => SupplyRequest::STATUS_PENDING,
                'requested_delivery_date' => $requestedDate ? Carbon::parse($requestedDate) : null,
                'notes' => $notes,
                'total_amount' => 0,
                'branch_monthly_limit_snapshot' => $branch->monthly_supply_limit,
                'branch_monthly_total_before' => $monthlySpent,
                'overlimit_reason' => $overlimitReason,
            ]);

            $totalAmount = 0;

            foreach ($items as $itemData) {
                $ingredient = $this->centralIngredientQuery($restaurantId, (int) $central->id)
                    ->whereKey($itemData['ingredient_id'])
                    ->firstOrFail();

                $unitCost = (float) ($ingredient->average_cost ?? 0);
                $qty = (float) $itemData['quantity'];

                $centralInventory = Inventory::where('restaurant_id', $restaurantId)
                    ->where('branch_id', $central->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->first();

                // Kiểm tra TỒN KHẢ DỤNG (quantity_available = quantity_on_hand - reserved)
                $available = $centralInventory ? $centralInventory->quantity_available : 0.0;
                if ($available < $qty) {
                    $unitSymbol = $ingredient->unit?->symbol ?? 'đv';
                    throw new InvalidArgumentException("Tổng Kho chỉ còn khả dụng {$available} {$unitSymbol} {$ingredient->name} (đã trừ giữ chỗ cho các đơn khác), không đủ để yêu cầu {$qty}.");
                }

                $lineCost = round($unitCost * $qty, 2);
                $totalAmount += $lineCost;

                SupplyRequestItem::create([
                    'supply_request_id' => $supplyRequest->id,
                    'ingredient_id' => $ingredient->id,
                    'requested_quantity' => $qty,
                    'approved_quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'unit_symbol' => $ingredient->unit?->symbol ?? 'kg',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            // Kiểm tra tổng giá trị có vượt định mức tháng không
            if ($branch->monthly_supply_limit > 0 && ($monthlySpent + $totalAmount) > (float) $branch->monthly_supply_limit) {
                if (blank($overlimitReason)) {
                    $overAmount = number_format(($monthlySpent + $totalAmount) - (float) $branch->monthly_supply_limit, 0, ',', '.');
                    throw new InvalidArgumentException("Yêu cầu này làm tổng cấp phát tháng vượt định mức {$overAmount} VNĐ. Vui lòng nhập lý do giải trình vượt hạn mức.");
                }
            }

            $supplyRequest->update(['total_amount' => $totalAmount]);

            // Gửi thông báo cho Ban Quản Lý Kho
            $this->notifyStakeholders($supplyRequest, 'created');

            return $supplyRequest->load(['items.ingredient', 'fromBranch', 'toBranch', 'creator']);
        });
    }

    /**
     * Duyệt đơn cấp phát và GIỮ CHỐ TỒN KHẢ DỤNG (Hold Inventory Reservation).
     */
    public function approveSupplyRequest(SupplyRequest $request, User $approver, ?array $approvedItems = null, ?string $notes = null): SupplyRequest
    {
        $this->assertSameRestaurant($request, $approver);
        $this->assertActorCan($approver, ['warehouse_manager'], ['supply_requests.approve', 'warehouse.manage'], 'Bạn không có quyền duyệt đơn cấp phát.');
        $this->assertCentralWarehouseActor($approver, (int) $request->restaurant_id);
        $this->assertNotSelfApproval($request, $approver, 'created_by', 'Bạn không thể tự duyệt đơn cấp phát do chính mình tạo.');

        if (! in_array($request->status, [SupplyRequest::STATUS_PENDING, 'draft'])) {
            throw new InvalidArgumentException('Chỉ có thể duyệt đơn ở trạng thái chờ duyệt.');
        }

        if ($approvedItems !== null && ! empty($approvedItems)) {
            $this->assertCompleteItemSet($request, $approvedItems, 'duyệt đơn');
        }

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }
        $this->assertCentralIngredients(
            $request->restaurant_id,
            (int) $central->id,
            $request->items->pluck('ingredient_id')
        );

        return DB::transaction(function () use ($request, $approver, $approvedItems, $notes) {
            $totalAmount = 0;
            $itemsToProcess = ! empty($approvedItems) ? $approvedItems : $request->items;

            foreach ($itemsToProcess as $itemApproval) {
                $item = $itemApproval instanceof SupplyRequestItem
                    ? $itemApproval
                    : SupplyRequestItem::where('supply_request_id', $request->id)->where('id', $itemApproval['id'])->first();

                if (! $item) {
                    continue;
                }

                $approvedQty = is_array($itemApproval)
                    ? (float) ($itemApproval['approved_quantity'] ?? $item->requested_quantity)
                    : (float) $item->requested_quantity;

                if ($approvedQty < 0) {
                    throw new InvalidArgumentException('Số lượng duyệt không được âm.');
                }

                // Check stock availability again at approval time
                $centralInventory = Inventory::where('restaurant_id', $request->restaurant_id)
                    ->where('branch_id', $request->from_branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->first();

                $available = $centralInventory ? $centralInventory->quantity_available : 0.0;
                if ($available < $approvedQty) {
                    throw new InvalidArgumentException("Tổng Kho không đủ tồn khả dụng ({$available}) cho nguyên liệu #{$item->ingredient_id} để duyệt {$approvedQty}.");
                }

                $lineCost = round((float) $item->unit_cost * $approvedQty, 2);
                $item->update([
                    'approved_quantity' => $approvedQty,
                    'total_cost' => $lineCost,
                ]);
                $totalAmount += $lineCost;

                // ── TẠO GIỮ CHỐ TỒN (Inventory Reservation) ────────────────────────
                InventoryReservation::create([
                    'restaurant_id' => $request->restaurant_id,
                    'branch_id' => $request->from_branch_id,
                    'ingredient_id' => $item->ingredient_id,
                    'order_id' => null,
                    'supply_request_id' => $request->id,
                    'reservation_type' => 'supply_request',
                    'quantity' => $approvedQty,
                    'expires_at' => now()->addDays(7),
                    'created_by' => $approver->id,
                ]);
            }

            $request->update([
                'status' => SupplyRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'total_amount' => $totalAmount,
                'notes' => $notes ? ($request->notes."\n[Duyệt]: ".$notes) : $request->notes,
            ]);

            $this->notifyStakeholders($request, 'approved');

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'approver', 'reservations']);
        });
    }

    /**
     * Soạn hàng (Layer 1 - Warehouse Staff pick FEFO batches and scan barcodes).
     */
    public function prepareDispatch(SupplyRequest $request, User $picker, array $pickedItems): SupplyRequest
    {
        $this->assertSameRestaurant($request, $picker);
        $this->assertActorCan($picker, ['warehouse_staff', 'warehouse_manager'], ['warehouse.manage'], 'Bạn không có quyền soạn hàng Kho Tổng.');
        $this->assertCentralWarehouseActor($picker, (int) $request->restaurant_id);
        $this->assertCompleteItemSet($request, $pickedItems, 'soạn hàng');

        if (! in_array($request->status, [SupplyRequest::STATUS_APPROVED, SupplyRequest::STATUS_PREPARING])) {
            throw new InvalidArgumentException('Chỉ đơn đã duyệt mới có thể soạn hàng.');
        }

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }
        $this->assertCentralIngredients(
            $request->restaurant_id,
            (int) $central->id,
            $request->items->pluck('ingredient_id')
        );

        return DB::transaction(function () use ($request, $picker, $pickedItems) {
            foreach ($pickedItems as $picked) {
                $item = SupplyRequestItem::where('supply_request_id', $request->id)
                    ->where('id', $picked['id'])
                    ->firstOrFail();

                $actualQty = (float) ($picked['actual_dispatched_quantity'] ?? $item->approved_quantity);
                $batchId = $picked['batch_id'] ?? null;

                $ingredient = Ingredient::where('restaurant_id', $request->restaurant_id)
                    ->findOrFail($item->ingredient_id);

                // Tính truy xuất lô theo mặc định danh mục. Dùng FEFO tự động khi
                // giao diá»‡n khÃ´ng truyá»n batch_id; dá»¯ liá»‡u legacy khÃ´ng cÃ³ lÃ´ váº«n
                // được phép xuất để không làm gián đoạn kho cũ.
                if (! $batchId && $actualQty > 0) {
                    $requiresBatch = (bool) $ingredient->batch_tracking_required
                        || in_array($ingredient->storage_type, ['fresh', 'daily', 'short_shelf'], true);
                    $fefoBatch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>=', $actualQty)
                        ->where(function ($query) {
                            $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
                        })
                        ->orderByRaw('expiry_date IS NULL')
                        ->orderBy('expiry_date')
                        ->orderBy('id')
                        ->first();

                    if ($fefoBatch) {
                        $batchId = $fefoBatch->id;
                    } else {
                        $anyBatch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                            ->where('branch_id', $request->from_branch_id)
                            ->where('ingredient_id', $item->ingredient_id)
                            ->where('status', 'active')
                            ->where('quantity_remaining', '>', 0)
                            ->orderByRaw('expiry_date IS NULL')
                            ->orderBy('expiry_date')
                            ->orderBy('id')
                            ->first();

                        if ($anyBatch) {
                            $batchId = $anyBatch->id;
                        } else {
                            $hasOnHandStock = Inventory::where('restaurant_id', $request->restaurant_id)
                                ->where('branch_id', $request->from_branch_id)
                                ->where('ingredient_id', $item->ingredient_id)
                                ->where('quantity_on_hand', '>', 0)
                                ->exists();

                            if (! $hasOnHandStock && $requiresBatch) {
                                throw new InvalidArgumentException("Nguyên liệu {$ingredient->name} bắt buộc truy xuất lô nhưng không có lô FEFO đủ tồn.");
                            }
                        }
                    }
                }

                // Khóa chặt: số lượng thực xuất không được vượt số lượng đã duyệt
                if ($actualQty < 0 || $actualQty > (float) $item->approved_quantity) {
                    throw new InvalidArgumentException("Số lượng thực soạn ({$actualQty}) không được vượt quá số lượng đã duyệt ({$item->approved_quantity}) cho nguyên liệu #{$item->ingredient_id}.");
                }

                // Kiểm tra lô & FEFO nếu truyền batch_id
                if ($batchId) {
                    $batch = InventoryBatch::where('restaurant_id', $request->restaurant_id)->findOrFail($batchId);

                    if ((int) $batch->ingredient_id !== (int) $item->ingredient_id) {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} không thuộc nguyên liệu #{$item->ingredient_id}.");
                    }
                    if ($request->from_branch_id && (int) $batch->branch_id !== (int) $request->from_branch_id) {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} không thuộc Kho Tổng hiện tại.");
                    }
                    if ($batch->isExpired()) {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} đã hết hạn sử dụng. Không thể soạn hàng từ lô hết hạn!");
                    }
                    if ($batch->status === 'recalled' || $batch->status === 'locked') {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} đang bị khóa/thu hồi!");
                    }
                    if ((float) $batch->quantity_remaining < $actualQty) {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} chỉ còn {$batch->quantity_remaining}, không đủ để soạn {$actualQty}.");
                    }

                    // Kiểm tra FEFO
                    $earlierBatch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->where('id', '!=', $batch->id)
                        ->whereNotNull('expiry_date')
                        ->where('expiry_date', '<', $batch->expiry_date)
                        ->exists();

                    $nonFefoReason = $picked['non_fefo_reason'] ?? null;
                    if ($earlierBatch && blank($nonFefoReason)) {
                        throw new InvalidArgumentException("Lô #{$batch->batch_code} không phải lô gần hết hạn nhất. Vui lòng nhập lý do nếu chọn lô khác FEFO.");
                    }
                }

                $item->update([
                    'actual_dispatched_quantity' => $actualQty,
                    'batch_id' => $batchId,
                    'warehouse_location_id' => $picked['warehouse_location_id'] ?? null,
                    'shortage_notes' => $picked['notes'] ?? null,
                    'non_fefo_reason' => $picked['non_fefo_reason'] ?? null,
                ]);
            }

            $isFullyPrepared = $request->items->every(fn (SupplyRequestItem $item): bool =>
                $item->actual_dispatched_quantity !== null
                && (float) $item->actual_dispatched_quantity >= (float) $item->approved_quantity
            );
            $nextStatus = $isFullyPrepared
                ? SupplyRequest::STATUS_PREPARED
                : SupplyRequest::STATUS_PREPARING;

            $request->update([
                'status' => $nextStatus,
                'prepared_by' => $picker->id,
                'prepared_at' => now(),
            ]);

            $this->notifyStakeholders($request, $isFullyPrepared ? 'prepared' : 'preparing');

            return $request->fresh(['items.ingredient', 'items.batch']);
        });
    }

    /**
     * Duyệt số lượng xuất kho cuối cùng (Layer 2 - Warehouse Manager sign-off).
     */
    public function approveDispatch(SupplyRequest $request, User $manager): SupplyRequest
    {
        $this->assertSameRestaurant($request, $manager);
        $this->assertActorCan($manager, ['warehouse_manager'], ['warehouse.manage', 'warehouse.handover'], 'Bạn không có quyền duyệt xuất kho.');
        $this->assertCentralWarehouseActor($manager, (int) $request->restaurant_id);
        $this->assertNotSelfApproval($request, $manager, 'prepared_by', 'Người soạn hàng không được tự duyệt số lượng xuất.');

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }

        if (! in_array($request->status, [SupplyRequest::STATUS_PREPARING, SupplyRequest::STATUS_PREPARED], true)) {
            throw new InvalidArgumentException('Đơn hàng phải qua bước soạn hàng trước khi Trưởng kho duyệt xuất.');
        }

        $request->update([
            'status' => SupplyRequest::STATUS_DISPATCH_PENDING,
            'dispatch_approved_by' => $manager->id,
            'dispatch_approved_at' => now(),
        ]);

        $this->notifyStakeholders($request, 'dispatch_approved');

        return $request->fresh();
    }

    /**
     * Xuất kho thực tế & bàn giao (Layer 3 - Physical Handover & Stock Deduction).
     * [P0.1]: Chỉ cho phép xuất khi đơn đã ở trạng thái dispatch_pending_approval.
     */
    public function dispatchSupplyRequest(
        SupplyRequest $request,
        User $handoverPerson,
        ?string $sealCode = null
    ): SupplyRequest {
        $this->assertSameRestaurant($request, $handoverPerson);
        $this->assertActorCan($handoverPerson, ['warehouse_staff', 'warehouse_manager'], ['warehouse.handover', 'warehouse.manage'], 'Bạn không có quyền bàn giao hàng Kho Tổng.');
        $this->assertCentralWarehouseActor($handoverPerson, (int) $request->restaurant_id);
        $this->assertNotSelfApproval($request, $handoverPerson, 'created_by', 'Người tạo đơn không được tự xuất kho.');

        // KHÓA CỨNG: Bắt buộc phải qua bước Trưởng kho duyệt xuất (STATUS_DISPATCH_PENDING)
        if ($request->status !== SupplyRequest::STATUS_DISPATCH_PENDING) {
            throw new InvalidArgumentException('Chỉ đơn đã được Trưởng kho duyệt số lượng xuất (chờ xuất hàng) mới có thể xuất kho bàn giao.');
        }

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }
        $this->assertCentralIngredients(
            $request->restaurant_id,
            (int) $central->id,
            $request->items->pluck('ingredient_id')
        );

        $rules = app(WarehouseGovernanceService::class)->getRules($request->restaurant_id);
        if ($rules->require_seal_code_on_dispatch && blank($sealCode) && blank($request->seal_code)) {
            throw new InvalidArgumentException('Vui lòng nhập mã niêm phong trước khi xuất kho giao hàng.');
        }

        return DB::transaction(function () use ($request, $handoverPerson, $sealCode) {
            foreach ($request->items as $item) {
                if ($item->actual_dispatched_quantity === null) {
                    throw new InvalidArgumentException('Đơn chưa có đủ số lượng thực soạn cho tất cả dòng hàng.');
                }
                $qtyToDeduct = (float) $item->effective_dispatched_quantity;

                // Kiểm tra lại không vượt quá số lượng đã duyệt
                if ($qtyToDeduct < 0 || $qtyToDeduct > (float) $item->approved_quantity) {
                    throw new InvalidArgumentException("Số lượng thực xuất ({$qtyToDeduct}) vượt quá số lượng đã duyệt ({$item->approved_quantity}) cho nguyên liệu #{$item->ingredient_id}.");
                }

                $ingredient = Ingredient::where('restaurant_id', $request->restaurant_id)
                    ->findOrFail($item->ingredient_id);

                if ($qtyToDeduct > 0 && ! $item->batch_id && $request->from_branch_id) {
                    $autoBatch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->orderByRaw('expiry_date IS NULL')
                        ->orderBy('expiry_date')
                        ->orderBy('id')
                        ->first();

                    if ($autoBatch) {
                        $item->batch_id = $autoBatch->id;
                        $item->save();
                    } else {
                        $anyBatch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                            ->where('branch_id', $request->from_branch_id)
                            ->where('ingredient_id', $item->ingredient_id)
                            ->orderByDesc('id')
                            ->first();

                        if ($anyBatch) {
                            $item->batch_id = $anyBatch->id;
                            $item->save();
                        }
                    }
                }

                $requiresBatch = (bool) $ingredient->batch_tracking_required;
                if ($qtyToDeduct > 0 && $requiresBatch && ! $item->batch_id) {
                    $hasStock = Inventory::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->where('quantity_on_hand', '>', 0)
                        ->exists();

                    if (! $hasStock) {
                        throw new InvalidArgumentException("Nguyên liệu {$ingredient->name} bắt buộc truy xuất lô nhưng Kho Tổng không đủ tồn.");
                    }
                }

                if ($request->from_branch_id) {
                    // Xuất kho là giao dịch thực tế. Nếu tồn hiện tại không đủ,
                    // vẫn ghi nhận số xuất và để tồn âm đi qua workflow xử lý âm.
                    $centralInventory = Inventory::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $centralInventory) {
                        $centralInventory = Inventory::create([
                            'restaurant_id' => $request->restaurant_id,
                            'branch_id' => $request->from_branch_id,
                            'ingredient_id' => $item->ingredient_id,
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => (float) ($ingredient->average_cost ?? 0),
                        ]);
                    }

                    $centralInventory = Inventory::whereKey($centralInventory->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $centralBefore = (float) $centralInventory->quantity_on_hand;
                    $centralAfter = $centralBefore - $qtyToDeduct;
                    $centralTheoreticalBefore = $centralInventory->effectiveTheoreticalQuantity();
                    $centralInventory->update([
                        'quantity_on_hand' => $centralAfter,
                        'theoretical_quantity' => $centralTheoreticalBefore - $qtyToDeduct,
                    ]);
                    $centralShortage = max(0, -$centralAfter);

                    // Trừ tồn theo lô nếu có chọn lô
                    if ($item->batch_id) {
                        $batch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                            ->where('branch_id', $request->from_branch_id)
                            ->where('ingredient_id', $item->ingredient_id)
                            ->where('id', $item->batch_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $batch) {
                            throw new InvalidArgumentException('Lô xuất kho không còn tồn tại hoặc không thuộc Kho Tổng.');
                        }
                        if ($batch) {
                            $batchAfter = (float) $batch->quantity_remaining - $qtyToDeduct;
                            $batch->update([
                                'quantity_remaining' => $batchAfter,
                                'status' => $batchAfter <= 0 ? 'depleted' : $batch->status,
                            ]);
                            if ((float) $batch->quantity_remaining <= 0) {
                                $batch->update(['status' => 'depleted']);
                            }
                        }
                    }

                    // Giải phóng Reservation giữ chỗ tương ứng
                    InventoryReservation::where('supply_request_id', $request->id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->whereNull('released_at')
                        ->update(['released_at' => now()]);

                    // Ghi Ledger Xuất Kho (InventoryTransaction)
                    $transaction = InventoryTransaction::createWithIdempotency([
                        'restaurant_id' => $request->restaurant_id,
                        'branch_id' => $request->from_branch_id,
                        'ingredient_id' => $item->ingredient_id,
                        'inventory_id' => $centralInventory->id,
                        'performed_by' => $handoverPerson->id,
                        'type' => 'transfer',
                        'direction' => 'out',
                        'quantity' => $qtyToDeduct,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => round($item->unit_cost * $qtyToDeduct, 2),
                        'source_type' => 'supply_request',
                        'source_id' => $request->id,
                        'idempotency_key' => "dispatch_sr_{$request->id}_item_{$item->id}",
                        'notes' => "Xuất kho cấp phát cho chi nhánh {$request->toBranch?->name} theo đơn {$request->request_code}"
                            .($centralShortage > 0 ? " | Tồn âm sau xuất: {$centralShortage}" : ''),
                        'quantity_before' => $centralBefore,
                        'quantity_after' => $centralAfter,
                        'occurred_at' => now(),
                    ]);
                    app(NegativeInventoryService::class)->sync($centralInventory, $transaction);

                    if ($item->batch_id) {
                        InventoryBatchAllocation::create([
                            'restaurant_id' => $request->restaurant_id,
                            'branch_id' => $request->from_branch_id,
                            'inventory_batch_id' => $item->batch_id,
                            'inventory_transaction_id' => $transaction->id,
                            'direction' => 'out',
                            'quantity' => $qtyToDeduct,
                            'unit_cost' => $item->unit_cost,
                        ]);
                    }
                }
            }

            $request->update([
                'status' => SupplyRequest::STATUS_DISPATCHED,
                'seal_code' => $sealCode ?: $request->seal_code,
                'dispatched_by' => $handoverPerson->id,
                'dispatched_at' => now(),
                'handover_by' => $handoverPerson->id,
                'handover_at' => now(),
            ]);

            $this->notifyStakeholders($request, 'dispatched');

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'dispatcher']);
        });
    }

    /**
     * Nhận hàng tại Chi Nhánh (chống gian lận: bắt buộc đếm thực tế, ảnh, chữ ký, tạo dispute nếu thiếu).
     */
    public function receiveSupplyRequest(
        SupplyRequest $request,
        User $receiver,
        ?array $receivedItems = null,
        ?string $receiptPhotoPath = null,
        ?string $signaturePath = null,
        ?string $notes = null,
        ?string $receiptPhotoHash = null,
        ?string $signatureHash = null
    ): SupplyRequest {
        $this->assertSameRestaurant($request, $receiver);
        $this->assertActorCanReceive($request, $receiver);
        $this->assertNotSelfApproval($request, $receiver, 'dispatched_by', 'Người xuất kho không được tự xác nhận nhận hàng.');

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if ($central && (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }
        if ($central) {
            $this->assertCentralIngredients(
                $request->restaurant_id,
                (int) $central->id,
                $request->items->pluck('ingredient_id')
            );
        }

        if (! in_array($request->status, [SupplyRequest::STATUS_DISPATCHED, SupplyRequest::STATUS_PARTIAL_RECEIVED, SupplyRequest::STATUS_COMPLETED])) {
            throw new InvalidArgumentException('Chỉ đơn đang giao mới có thể xác nhận nhận hàng.');
        }

        if ($request->status === SupplyRequest::STATUS_COMPLETED) {
            return $request;
        }

        $this->assertCompleteItemSet($request, $receivedItems ?? [], 'nhận hàng');

        $receivedItems = $receivedItems ?? [];

        return DB::transaction(function () use ($request, $receiver, $receivedItems, $receiptPhotoPath, $signaturePath, $notes, $receiptPhotoHash, $signatureHash) {
            $lockedRequest = SupplyRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedRequest->status, [SupplyRequest::STATUS_DISPATCHED, SupplyRequest::STATUS_PARTIAL_RECEIVED], true)) {
                throw new InvalidArgumentException('Đơn nhận hàng đã được xử lý hoặc không còn ở trạng thái có thể xác nhận.');
            }

            $lockedItems = $lockedRequest->items()->lockForUpdate()->get();
            $hasShortage = false;
            $hasDamage = false;
            $reverseLogistics = app(WarehouseReverseLogisticsService::class);

            foreach ($lockedItems as $item) {
                $dispatchedQty = (float) $item->effective_dispatched_quantity;
                $previouslyReceivedQty = (float) ($item->received_quantity ?? 0);
                $targetTotalRecQty = null;
                $targetGoodQty = null;
                $targetDamagedQty = 0.0;
                $targetExpiredQty = 0.0;
                $targetWrongItemQty = 0.0;
                $targetCondition = null;
                $targetNote = null;
                $targetTemperatureMin = null;
                $targetTemperatureMax = null;

                foreach ($receivedItems as $recItem) {
                    if ((int) ($recItem['id'] ?? 0) === (int) $item->id && isset($recItem['received_quantity'])) {
                        $targetTotalRecQty = (float) $recItem['received_quantity'];
                        $targetGoodQty = (float) ($recItem['received_good_quantity'] ?? $targetTotalRecQty);
                        $targetDamagedQty = (float) ($recItem['received_damaged_quantity'] ?? 0);
                        $targetExpiredQty = (float) ($recItem['received_expired_quantity'] ?? 0);
                        $targetWrongItemQty = (float) ($recItem['received_wrong_item_quantity'] ?? 0);
                        $targetCondition = $recItem['received_condition'] ?? null;
                        $targetNote = $recItem['received_note'] ?? null;
                        $targetTemperatureMin = $recItem['received_temperature_min_c'] ?? null;
                        $targetTemperatureMax = $recItem['received_temperature_max_c'] ?? null;
                        break;
                    }
                }

                if ($targetTotalRecQty === null || $targetTotalRecQty < 0) {
                    throw new InvalidArgumentException('Thiếu hoặc sai số lượng nhận của một dòng hàng.');
                }

                if ($targetTotalRecQty > $dispatchedQty) {
                    throw new InvalidArgumentException("Số lượng nhận ({$targetTotalRecQty}) không được vượt quá số lượng Kho Tổng đã xuất ({$dispatchedQty}) cho nguyên liệu #{$item->ingredient_id}.");
                }

                if ($targetTotalRecQty < $previouslyReceivedQty) {
                    throw new InvalidArgumentException("Số lượng nhận mới ({$targetTotalRecQty}) không được nhỏ hơn số lượng đã nhận trước đó ({$previouslyReceivedQty}).");
                }

                $previousGoodQty = $item->received_good_quantity !== null
                    ? (float) $item->received_good_quantity
                    : $previouslyReceivedQty;
                $previousDamagedQty = (float) ($item->received_damaged_quantity ?? 0);
                $previousExpiredQty = (float) ($item->received_expired_quantity ?? 0);
                $previousWrongItemQty = (float) ($item->received_wrong_item_quantity ?? 0);
                if ($targetGoodQty < $previousGoodQty || $targetDamagedQty < $previousDamagedQty || $targetExpiredQty < $previousExpiredQty || $targetWrongItemQty < $previousWrongItemQty) {
                    throw new InvalidArgumentException('Số lượng theo tình trạng không được nhỏ hơn số đã ghi nhận trước đó.');
                }

                $incrementalGoodQty = $targetGoodQty - $previousGoodQty;
                $incrementalDamagedQty = $targetDamagedQty - $previousDamagedQty;
                $incrementalExpiredQty = $targetExpiredQty - $previousExpiredQty;
                $incrementalWrongItemQty = $targetWrongItemQty - $previousWrongItemQty;
                $incrementalBadQty = $incrementalDamagedQty + $incrementalExpiredQty + $incrementalWrongItemQty;
                $itemHasDamage = $targetDamagedQty > 0 || $targetExpiredQty > 0 || $targetWrongItemQty > 0;

                $incrementalQty = $targetTotalRecQty - $previouslyReceivedQty;

                if ($targetTotalRecQty < $dispatchedQty) {
                    $hasShortage = true;
                }
                if ($itemHasDamage) {
                    $hasDamage = true;
                }

                $item->update([
                    'received_quantity' => $targetTotalRecQty,
                    'received_good_quantity' => $targetGoodQty,
                    'received_damaged_quantity' => $targetDamagedQty,
                    'received_expired_quantity' => $targetExpiredQty,
                    'received_wrong_item_quantity' => $targetWrongItemQty,
                    'received_condition' => $targetCondition ?: ($itemHasDamage ? 'damaged' : ($targetTotalRecQty < $dispatchedQty ? 'shortage' : 'good')),
                    'received_note' => $targetNote,
                    'received_evidence_path' => $receiptPhotoPath ?: $request->receipt_photo_path,
                    'received_temperature_min_c' => $targetTemperatureMin,
                    'received_temperature_max_c' => $targetTemperatureMax,
                ]);

                if ($incrementalGoodQty > 0) {
                    // Chỉ hàng đạt mới được cộng vào tồn khả dụng Chi nhánh.
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

                    $branchInventory = Inventory::whereKey($branchInventory->id)->lockForUpdate()->firstOrFail();
                    $branchBefore = (float) $branchInventory->quantity_on_hand;
                    $branchTheoreticalBefore = $branchInventory->effectiveTheoreticalQuantity();
                    $branchAfter = $branchBefore + $incrementalGoodQty;
                    $branchInventory->update([
                        'quantity_on_hand' => $branchAfter,
                        'theoretical_quantity' => $branchTheoreticalBefore + $incrementalGoodQty,
                    ]);

                    // Ghi Ledger Nhập Kho Chi Nhánh
                    $idempotencyKey = "receive_sr_{$request->id}_item_{$item->id}_good_{$previousGoodQty}_to_{$targetGoodQty}";
                    $sourceBatch = $item->batch_id
                        ? InventoryBatch::where('restaurant_id', $request->restaurant_id)->whereKey($item->batch_id)->first()
                        : null;

                    $transaction = InventoryTransaction::createWithIdempotency([
                        'restaurant_id' => $request->restaurant_id,
                        'branch_id' => $request->to_branch_id,
                        'ingredient_id' => $item->ingredient_id,
                        'inventory_id' => $branchInventory->id,
                        'performed_by' => $receiver->id,
                        'type' => 'transfer',
                        'direction' => 'in',
                        'quantity' => $incrementalGoodQty,
                        'unit_cost' => $item->unit_cost,
                        'total_cost' => round($item->unit_cost * $incrementalGoodQty, 2),
                        'source_type' => 'supply_request',
                        'source_id' => $request->id,
                        'idempotency_key' => $idempotencyKey,
                        'notes' => "Nhập hàng đạt từ Kho Tổng theo đơn {$request->request_code} (+{$incrementalGoodQty})",
                        'quantity_before' => $branchBefore,
                        'quantity_after' => $branchAfter,
                        'occurred_at' => now(),
                    ]);

                    $destinationBatch = $reverseLogistics->createDestinationBatch(
                        (int) $request->restaurant_id,
                        (int) $request->to_branch_id,
                        (int) $item->ingredient_id,
                        $incrementalGoodQty,
                        (float) $item->unit_cost,
                        $receiver,
                        $sourceBatch,
                    );
                    $item->update(['received_batch_id' => $destinationBatch?->id]);
                    if ($destinationBatch) {
                        InventoryBatchAllocation::create([
                            'restaurant_id' => $request->restaurant_id,
                            'branch_id' => $request->to_branch_id,
                            'inventory_batch_id' => $destinationBatch->id,
                            'inventory_transaction_id' => $transaction->id,
                            'direction' => 'in',
                            'quantity' => $incrementalGoodQty,
                            'unit_cost' => $item->unit_cost,
                        ]);
                    }
                }

                if ($incrementalBadQty > 0) {
                    $sourceBatch = $item->batch_id
                        ? InventoryBatch::where('restaurant_id', $request->restaurant_id)->whereKey($item->batch_id)->first()
                        : null;
                    $lockedBatch = $reverseLogistics->createDestinationBatch(
                        (int) $request->restaurant_id,
                        (int) $request->to_branch_id,
                        (int) $item->ingredient_id,
                        $incrementalBadQty,
                        (float) $item->unit_cost,
                        $receiver,
                        $sourceBatch,
                        true,
                        'Hàng nhận từ Kho Tổng bị hỏng/hết hạn/sai hàng, chờ hoàn trả hoặc tiêu hủy.',
                    );
                    $condition = $targetExpiredQty > 0 ? 'expired' : ($targetWrongItemQty > 0 ? 'wrong_item' : 'damaged');
                    $quarantine = $reverseLogistics->createQuarantine(
                        (int) $request->restaurant_id,
                        (int) $request->to_branch_id,
                        (int) $item->ingredient_id,
                        $incrementalBadQty,
                        $condition,
                        $targetNote ?: 'Hàng không đạt khi chi nhánh nhận.',
                        $receiver,
                        $lockedBatch,
                        'supply_request',
                        $request->id,
                        $item->id,
                        array_filter([$receiptPhotoPath ?: $request->receipt_photo_path]),
                        $targetNote,
                    );
                    $item->update(['quarantine_id' => $quarantine->id]);
                }
            }

            if (($hasShortage || $hasDamage) && (blank($receiptPhotoPath) && blank($request->receipt_photo_path) || blank($signaturePath) && blank($request->receiver_signature_path))) {
                throw new InvalidArgumentException('Bắt buộc đính kèm ảnh thực tế và chữ ký người nhận khi giao nhận thiếu hoặc hỏng.');
            }

            $finalStatus = ($hasShortage || $hasDamage) ? SupplyRequest::STATUS_DISPUTED : SupplyRequest::STATUS_COMPLETED;

            $request->update([
                'status' => $finalStatus,
                'received_by' => $receiver->id,
                'received_at' => now(),
                'receipt_photo_path' => $receiptPhotoPath ?: $request->receipt_photo_path,
                'receipt_photo_hash' => $receiptPhotoHash ?: $request->receipt_photo_hash,
                'receiver_signature_path' => $signaturePath ?: $request->receiver_signature_path,
                'receiver_signature_hash' => $signatureHash ?: $request->receiver_signature_hash,
                'received_notes' => $notes ?: $request->received_notes,
                'discrepancy_flag' => $hasShortage || $hasDamage,
            ]);

            $reverseLogistics->recordShipmentEvent(
                (int) $request->restaurant_id,
                'supply_request',
                (int) $request->id,
                ($hasShortage || $hasDamage) ? 'received_with_discrepancy' : 'received',
                $receiver,
                ['branch_id' => $request->to_branch_id, 'notes' => $notes],
            );

            // Nếu có nhận thiếu/hỏng: Tự động kích hoạt Governance Service để mở tranh chấp.
            if (($hasShortage || $hasDamage) && ! empty($receivedItems)) {
                app(WarehouseGovernanceService::class)->checkAndCreateDisputesFromSupplyRequest($lockedRequest, $receivedItems);
                $this->notifyStakeholders($lockedRequest, 'disputed');
            } else {
                $this->notifyStakeholders($lockedRequest, 'received');
            }

            return $lockedRequest->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'receiver']);
        });
    }

    /**
     * Hủy đơn yêu cầu cấp phát (giải phóng ngay lập tức các giữ chỗ tồn kho).
     */
    public function cancelSupplyRequest(SupplyRequest $request, User $user, string $reason): SupplyRequest
    {
        $this->assertSameRestaurant($request, $user);
        $this->assertActorCan($user, ['warehouse_manager'], ['warehouse.manage', 'supply_requests.cancel'], 'Bạn không có quyền hủy đơn cấp phát.');
        $central = $this->getCentralWarehouse((int) $request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Chỉ được hủy đơn cấp phát xuất từ Kho Tổng.');
        }

        if (! $request->canBeCancelled()) {
            throw new InvalidArgumentException('Không thể hủy đơn hàng đã xuất kho hoặc đã hoàn tất.');
        }

        return DB::transaction(function () use ($request, $reason) {
            // Giải phóng reservation giữ chỗ
            InventoryReservation::where('supply_request_id', $request->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $request->update([
                'status' => SupplyRequest::STATUS_CANCELLED,
                'cancel_reason' => $reason,
            ]);

            $this->notifyStakeholders($request, 'cancelled');

            return $request->fresh();
        });
    }

    /**
     * Từ chối đơn cấp phát (dành cho Trưởng Kho Duyệt).
     */
    public function rejectSupplyRequest(SupplyRequest $request, User $user, string $reason): SupplyRequest
    {
        $this->assertSameRestaurant($request, $user);
        $this->assertActorCan($user, ['warehouse_manager'], ['warehouse.manage', 'supply_requests.approve'], 'Bạn không có quyền từ chối đơn cấp phát.');

        if (in_array($request->status, [SupplyRequest::STATUS_COMPLETED, SupplyRequest::STATUS_DISPATCHED])) {
            throw new InvalidArgumentException('Không thể từ chối đơn hàng đã xuất kho hoặc hoàn thành.');
        }

        return DB::transaction(function () use ($request, $user, $reason) {
            // Giải phóng reservation nếu có
            InventoryReservation::where('supply_request_id', $request->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $request->update([
                'status' => SupplyRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => $user->id,
            ]);

            $this->notifyStakeholders($request, 'rejected');

            return $request->fresh();
        });
    }

    private function centralIngredientQuery(int $restaurantId, int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->where(fn ($query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $centralBranchId)
                ->orWhereHas('inventories', fn ($inv) => $inv->where('branch_id', $centralBranchId)));
    }

    private function assertCentralIngredients(int $restaurantId, int $centralBranchId, iterable $ingredientIds): void
    {
        $ids = collect($ingredientIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($this->centralIngredientQuery($restaurantId, $centralBranchId)->whereIn('id', $ids)->count() !== $ids->count()) {
            throw new InvalidArgumentException('Đơn cấp phát chứa nguyên liệu ngoài phạm vi Kho Tổng.');
        }
    }

    private function assertSameRestaurant(SupplyRequest $request, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ((int) $actor->restaurant_id !== (int) $request->restaurant_id) {
            throw new InvalidArgumentException('Bạn không thể thao tác trên đơn cấp phát của nhà hàng khác.');
        }
    }

    private function assertBranchHasNoOperationalData(RestaurantBranch $branch, string $action): void
    {
        $branchId = (int) $branch->id;

        if (Inventory::where('restaurant_id', $branch->restaurant_id)->where('branch_id', $branchId)->where('quantity_on_hand', '>', 0)->exists()
            || InventoryBatch::where('restaurant_id', $branch->restaurant_id)->where('branch_id', $branchId)->where('quantity_remaining', '>', 0)->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn tồn kho hoặc lô hàng chưa xử lý.");
        }

        if (SupplyRequest::where('restaurant_id', $branch->restaurant_id)
            ->where(fn ($query) => $query->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId))
            ->whereNotIn('status', [SupplyRequest::STATUS_COMPLETED, SupplyRequest::STATUS_REJECTED, SupplyRequest::STATUS_CANCELLED])
            ->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn đơn cấp phát đang xử lý.");
        }

        if (StockTransferRequest::where('restaurant_id', $branch->restaurant_id)
            ->where(fn ($query) => $query->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId))
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn đơn luân chuyển đang mở.");
        }

        if (InventoryCountSession::where('restaurant_id', $branch->restaurant_id)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['approved', 'cancelled', 'rejected'])
            ->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn phiên kiểm kê chưa kết thúc.");
        }

        if (InventoryDiscrepancyDispute::whereHas('supplyRequest', fn ($query) => $query
            ->where(fn ($scope) => $scope->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId)))
            ->where('status', 'open')
            ->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn tranh chấp kho đang mở.");
        }

        if (DeliveryManifest::where('restaurant_id', $branch->restaurant_id)
            ->where('from_branch_id', $branchId)
            ->whereIn('status', ['draft', 'preparing'])
            ->exists()) {
            throw new InvalidArgumentException("Không thể {$action} khi chi nhánh còn chuyến xe đang soạn.");
        }
    }

    private function ensureCentralWarehouseAssignment(int $restaurantId, int $branchId): void
    {
        DB::table('central_warehouse_assignments')->updateOrInsert(
            ['restaurant_id' => $restaurantId],
            [
                'branch_id' => $branchId,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function assertActorCan(User $actor, array $roles, array $permissions, string $message): void
    {
        if ($actor->hasRole('warehouse_staff')
            && ($actor->status !== 'active' || ($actor->warehouse_staff_status ?? 'active') !== 'active')) {
            throw new InvalidArgumentException('Tài khoản Nhân viên kho Tổng đang tạm dừng hoặc không còn hoạt động.');
        }

        if ($actor->isSuperAdmin() || $actor->isOwner() || $actor->hasAnyRole($roles)) {
            return;
        }

        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return;
            }
        }

        throw new InvalidArgumentException($message);
    }

    private function assertActorCanReceive(SupplyRequest $request, User $receiver): void
    {
        if ($receiver->isSuperAdmin() || $receiver->isOwner()) {
            return;
        }

        if (! $receiver->canAccessBranch((int) $request->to_branch_id)
            && ! $receiver->can('supply_requests.receive')) {
            throw new InvalidArgumentException('Người nhận không thuộc phạm vi chi nhánh nhận hàng.');
        }
    }

    private function assertCentralWarehouseActor(User $actor, int $restaurantId): void
    {
        if ($actor->isSuperAdmin() || $actor->isOwner()) {
            return;
        }

        $central = $this->getCentralWarehouse($restaurantId);
        if ($actor->hasRole('warehouse_staff')
            && ($actor->status !== 'active' || ($actor->warehouse_staff_status ?? 'active') !== 'active')) {
            throw new InvalidArgumentException('Tài khoản Nhân viên kho Tổng đang tạm dừng hoặc không còn hoạt động.');
        }
        $assignedBranchId = $actor->warehouse_branch_id ?: $actor->assignedBranchId();
        if (! $central || ! $assignedBranchId || (int) $assignedBranchId !== (int) $central->id) {
            throw new InvalidArgumentException('Tài khoản chưa được gán đúng Kho Tổng hiện tại.');
        }
    }

    private function assertCompleteItemSet(SupplyRequest $request, array $items, string $operation): void
    {
        $submitted = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->values();
        $uniqueSubmitted = $submitted->unique()->values();
        $expected = $request->items->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($submitted->count() !== $uniqueSubmitted->count()
            || $uniqueSubmitted->sort()->values()->all() !== $expected->sort()->values()->all()) {
            throw new InvalidArgumentException("Phải gửi đủ và đúng một lần tất cả dòng hàng của đơn khi {$operation}.");
        }
    }

    private function assertNotSelfApproval(SupplyRequest $request, User $actor, string $field, string $message): void
    {
        if ($actor->isOwner() || $actor->isSuperAdmin()) {
            return;
        }

        if ((int) $request->getAttribute($field) === (int) $actor->id) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Thuật toán Phân bổ Thông minh Fair-Share khi Kho Tổng bị thiếu hàng.
     */
    public function suggestSmartAllocation(int $restaurantId, array $supplyRequestIds): array
    {
        $central = $this->getCentralWarehouse($restaurantId);
        if (! $central) {
            throw new InvalidArgumentException('Chưa cấu hình Kho Tổng.');
        }

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->whereIn('id', $supplyRequestIds)
            ->where('from_branch_id', $central->id)
            ->whereIn('status', [SupplyRequest::STATUS_PENDING, SupplyRequest::STATUS_APPROVED])
            ->with(['items.ingredient', 'toBranch'])
            ->get();

        if ($requests->isEmpty()) {
            return [];
        }

        $this->assertCentralIngredients(
            $restaurantId,
            (int) $central->id,
            $requests->flatMap(fn (SupplyRequest $request) => $request->items->pluck('ingredient_id'))
        );

        // Gom tổng nhu cầu theo từng ingredient_id
        $totalDemandByIngredient = [];
        foreach ($requests as $req) {
            foreach ($req->items as $item) {
                $ingId = $item->ingredient_id;
                if (! isset($totalDemandByIngredient[$ingId])) {
                    $totalDemandByIngredient[$ingId] = [
                        'total_requested' => 0.0,
                        'requests' => [],
                    ];
                }
                $qty = (float) $item->requested_quantity;
                $totalDemandByIngredient[$ingId]['total_requested'] += $qty;
                $totalDemandByIngredient[$ingId]['requests'][] = [
                    'request_id' => $req->id,
                    'item_id' => $item->id,
                    'to_branch_id' => $req->to_branch_id,
                    'branch_name' => $req->toBranch?->name ?? "CN #{$req->to_branch_id}",
                    'requested_qty' => $qty,
                ];
            }
        }

        $suggestions = [];

        foreach ($totalDemandByIngredient as $ingId => $data) {
            $centralInventory = Inventory::where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('ingredient_id', $ingId)
                ->first();

            $availableStock = $centralInventory ? (float) $centralInventory->quantity_available : 0.0;
            $totalDemand = $data['total_requested'];
            $ingredient = Ingredient::find($ingId);

            $allocationRatio = ($totalDemand > 0 && $availableStock < $totalDemand)
                ? round($availableStock / $totalDemand, 4)
                : 1.0;

            foreach ($data['requests'] as $reqData) {
                $suggestedQty = round($reqData['requested_qty'] * $allocationRatio, 2);
                $suggestions[] = [
                    'request_id' => $reqData['request_id'],
                    'item_id' => $reqData['item_id'],
                    'branch_name' => $reqData['branch_name'],
                    'ingredient_id' => $ingId,
                    'ingredient_name' => $ingredient?->name ?? "NL #{$ingId}",
                    'requested_qty' => $reqData['requested_qty'],
                    'available_stock' => $availableStock,
                    'suggested_qty' => $suggestedQty,
                    'shortage_qty' => max(0, round($reqData['requested_qty'] - $suggestedQty, 2)),
                    'is_shortage' => $availableStock < $totalDemand,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Tự động sinh Đơn Giao Bù (Backorder) cho phần thiếu lượng khi xuất kho.
     */
    public function createBackorder(SupplyRequest $parentRequest, array $shortageItems, User $user): SupplyRequest
    {
        $this->assertSameRestaurant($parentRequest, $user);
        $central = $this->getCentralWarehouse($parentRequest->restaurant_id);
        if (! $central || (int) $parentRequest->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn giao bù không xuất phát từ Kho Tổng hiện tại.');
        }
        $this->assertCentralIngredients(
            $parentRequest->restaurant_id,
            (int) $central->id,
            collect($shortageItems)->where('shortage_quantity', '>', 0)->pluck('ingredient_id')
        );

        return DB::transaction(function () use ($parentRequest, $shortageItems, $user, $central) {
            $restaurantId = $parentRequest->restaurant_id;
            $requestCode = $parentRequest->request_code.'-BO';

            $existingBackorder = SupplyRequest::where('restaurant_id', $restaurantId)
                ->where('request_code', $requestCode)
                ->first();
            if ($existingBackorder) {
                return $existingBackorder->load(['items.ingredient', 'fromBranch', 'toBranch']);
            }

            $backorder = SupplyRequest::create([
                'restaurant_id' => $restaurantId,
                'request_code' => $requestCode,
                'from_branch_id' => $parentRequest->from_branch_id,
                'to_branch_id' => $parentRequest->to_branch_id,
                'parent_request_id' => $parentRequest->id,
                'created_by' => $user->id,
                'status' => SupplyRequest::STATUS_PENDING,
                'notes' => "Đơn giao bù tự động từ đơn gốc #{$parentRequest->request_code}",
                'total_amount' => 0,
                'requested_delivery_date' => now()->addDays(2),
            ]);

            $totalAmount = 0;

            foreach ($shortageItems as $sItem) {
                $shortageQty = (float) $sItem['shortage_quantity'];
                if ($shortageQty <= 0) {
                    continue;
                }

                $ingredient = $this->centralIngredientQuery($restaurantId, (int) $central->id)
                    ->whereKey($sItem['ingredient_id'])
                    ->firstOrFail();
                $unitCost = (float) ($ingredient->average_cost ?? 0);
                $lineCost = round($unitCost * $shortageQty, 2);
                $totalAmount += $lineCost;

                SupplyRequestItem::create([
                    'supply_request_id' => $backorder->id,
                    'ingredient_id' => $ingredient->id,
                    'requested_quantity' => $shortageQty,
                    'approved_quantity' => $shortageQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'unit_symbol' => $ingredient->unit?->symbol ?? 'kg',
                    'notes' => "Giao bù phần thiếu từ đơn #{$parentRequest->request_code}",
                ]);
            }

            $backorder->update(['total_amount' => $totalAmount]);

            $this->notifyStakeholders($backorder, 'backorder_created');

            return $backorder->fresh(['items.ingredient', 'fromBranch', 'toBranch']);
        });
    }

    /**
     * Báo cáo KPIs Chuyên sâu cho Trưởng Kho Tổng (Fill Rate, OTIF, Waste Ratio, FEFO Compliance).
     * [P1.4]: Tính toán OTIF và tỷ lệ hao hụt từ dữ liệu thật, không gán cứng.
     */
    public function getCentralWarehouseAnalytics(int $restaurantId): array
    {
        $startOfMonth = now()->startOfMonth();
        $central = $this->getCentralWarehouse($restaurantId);

        $totalRequests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($central, fn ($query) => $query->where('from_branch_id', $central->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $completedRequests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($central, fn ($query) => $query->where('from_branch_id', $central->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('created_at', '>=', $startOfMonth)
            ->where('status', SupplyRequest::STATUS_COMPLETED)
            ->with(['items'])
            ->get();

        $completedCount = $completedRequests->count();
        $fillRate = $totalRequests > 0 ? round(($completedCount / $totalRequests) * 100, 1) : 100.0;

        // ── OTIF THẬT ────────────────────────────────────────────────────────
        // On-Time: received_at <= requested_delivery_date
        // In-Full: mọi item received_quantity >= requested_quantity và không có dispute
        if ($completedCount > 0) {
            $otifCount = 0;
            foreach ($completedRequests as $cr) {
                $isOnTime = ! $cr->requested_delivery_date || ! $cr->received_at || Carbon::parse($cr->received_at)->lte(Carbon::parse($cr->requested_delivery_date)->endOfDay());
                $isInFull = $cr->items->every(fn ($it) => (float) ($it->received_quantity ?? $it->effective_dispatched_quantity) >= (float) $it->requested_quantity);
                if ($isOnTime && $isInFull && ! $cr->discrepancy_flag) {
                    $otifCount++;
                }
            }
            $otifPercent = round(($otifCount / $completedCount) * 100, 1);
        } else {
            $otifPercent = $totalRequests > 0 ? 0.0 : 100.0;
        }

        // ── FEFO Compliance ──────────────────────────────────────────────────
        $itemsCount = SupplyRequestItem::whereHas('supplyRequest', function ($q) use ($restaurantId, $startOfMonth, $central) {
            $q->where('restaurant_id', $restaurantId)
                ->when($central, fn ($query) => $query->where('from_branch_id', $central->id), fn ($query) => $query->whereRaw('1 = 0'))
                ->where('created_at', '>=', $startOfMonth);
        })->count();

        $nonFefoCount = SupplyRequestItem::whereHas('supplyRequest', function ($q) use ($restaurantId, $startOfMonth, $central) {
            $q->where('restaurant_id', $restaurantId)
                ->when($central, fn ($query) => $query->where('from_branch_id', $central->id), fn ($query) => $query->whereRaw('1 = 0'))
                ->where('created_at', '>=', $startOfMonth);
        })->whereNotNull('non_fefo_reason')->count();

        $fefoCompliance = $itemsCount > 0 ? round((($itemsCount - $nonFefoCount) / $itemsCount) * 100, 1) : 100.0;

        // ── Tỷ Lệ Hao Hụt Thật (Waste Ratio) ──────────────────────────────────
        $wasteRatio = 0.0;
        if ($central) {
            $totalWasteCost = (float) InventoryTransaction::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('branch_id', $central->id)
                ->where('type', 'waste')
                ->where('occurred_at', '>=', $startOfMonth)
                ->sum('total_cost');

            $totalDispatchedValue = (float) SupplyRequest::where('restaurant_id', $restaurantId)
                ->where('from_branch_id', $central->id)
                ->where('created_at', '>=', $startOfMonth)
                ->whereIn('status', [SupplyRequest::STATUS_DISPATCHED, SupplyRequest::STATUS_COMPLETED])
                ->sum('total_amount');

            if ($totalDispatchedValue > 0) {
                $wasteRatio = round(($totalWasteCost / $totalDispatchedValue) * 100, 2);
            }
        }

        return [
            'total_requests_month' => $totalRequests,
            'completed_month' => $completedCount,
            'fill_rate_percent' => $fillRate,
            'otif_percent' => $otifPercent,
            'fefo_compliance' => $fefoCompliance,
            'waste_ratio_percent' => $wasteRatio,
            'active_disputes_count' => InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)->where('status', 'open')->count(),
        ];
    }

    /**
     * Gửi thông báo đến các bên liên quan theo từng giai đoạn.
     */
    public function notifyStakeholders(SupplyRequest $request, string $stage, ?string $customMessage = null): void
    {
        try {
            $recipients = collect();

            // 1. Người tạo yêu cầu
            if ($request->creator) {
                $recipients->push($request->creator);
            }

            // 2. Quản lý chi nhánh nhận
            if ($request->toBranch?->manager) {
                $recipients->push($request->toBranch->manager);
            }

            // 3. Đội ngũ quản lý Kho Tổng (warehouse_manager, owner, super_admin)
            $warehouseManagers = User::where('restaurant_id', $request->restaurant_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['warehouse_manager', 'owner', 'super_admin']))
                ->get();
            $recipients = $recipients->merge($warehouseManagers);

            // Nhân viên đang được giao task phải nhận được cập nhật để phối hợp hai chiều.
            $assignedStaff = WarehouseTaskAssignment::where('restaurant_id', $request->restaurant_id)
                ->where('supply_request_id', $request->id)
                ->whereNotNull('assigned_to')
                ->with('assignee')
                ->get()
                ->pluck('assignee')
                ->filter();
            $recipients = $recipients->merge($assignedStaff);

            // Nhân sự tại chi nhánh nhận phải thấy được các mốc chuẩn bị,
            // vận chuyển và yêu cầu xác nhận; chỉ thông báo cho quản lý là
            // chưa đủ để hoàn tất vòng đời hai chiều.
            $branchReceivers = User::where('restaurant_id', $request->restaurant_id)
                ->where('status', 'active')
                ->where('branch_id', $request->to_branch_id)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['manager', 'branch_staff', 'inventory_staff', 'staff']))
                ->get();
            $recipients = $recipients->merge($branchReceivers);

            // 4. Nếu có tranh chấp (disputed): Bắt buộc gửi tới Owner
            if ($stage === 'disputed') {
                $owners = User::where('restaurant_id', $request->restaurant_id)
                    ->whereHas('roles', fn ($r) => $r->where('name', 'owner'))
                    ->get();
                $recipients = $recipients->merge($owners);
            }

            $uniqueRecipients = $recipients->unique('id')->filter();

            if ($stage === 'created') {
                $uniqueRecipients->each(fn (User $user) => $user->notify(new SupplyRequestCreatedNotification($request)));
            } else {
                $uniqueRecipients->each(fn (User $user) => $user->notify(new SupplyRequestStatusNotification($request, $stage, $customMessage)));
            }
        } catch (\Throwable $e) {
            Log::warning("Không thể gửi notification cho đơn cấp phát #{$request->id}: ".$e->getMessage());
        }
    }

    /**
     * Kiểm tra và gửi cảnh báo các đơn cấp phát quá hạn xử lý.
     */
    public function checkAndAlertOverdueRequests(int $restaurantId): int
    {
        $overdueCount = 0;

        // Đơn chờ duyệt quá 24h
        $pendingOverdue = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('status', SupplyRequest::STATUS_PENDING)
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($pendingOverdue as $req) {
            if ($this->notifyOverdueOnce($req, SupplyRequest::STATUS_PENDING, "Đơn cấp phát #{$req->request_code} đã chờ duyệt quá 24 giờ.")) {
                $overdueCount++;
            }
        }

        // Đơn đã duyệt nhưng chưa soạn hàng quá 24h
        $preparingOverdue = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('status', SupplyRequest::STATUS_APPROVED)
            ->where('approved_at', '<=', now()->subHours(24))
            ->get();

        foreach ($preparingOverdue as $req) {
            if ($this->notifyOverdueOnce($req, SupplyRequest::STATUS_APPROVED, "Đơn cấp phát #{$req->request_code} đã được duyệt hơn 24 giờ nhưng chưa hoàn tất soạn hàng.")) {
                $overdueCount++;
            }
        }

        $stageChecks = [
            [SupplyRequest::STATUS_PREPARING, 'prepared_at', 24, 'đã bắt đầu soạn nhưng chưa hoàn tất soạn hàng'],
            [SupplyRequest::STATUS_DISPATCH_PENDING, 'dispatch_approved_at', 12, 'đã được duyệt xuất nhưng chưa bàn giao vận chuyển'],
            [SupplyRequest::STATUS_DISPATCHED, 'dispatched_at', 24, 'đã xuất kho nhưng chưa được chi nhánh xác nhận nhận hàng'],
        ];

        foreach ($stageChecks as [$status, $timestampColumn, $hours, $description]) {
            SupplyRequest::where('restaurant_id', $restaurantId)
                ->where('status', $status)
                ->whereNotNull($timestampColumn)
                ->where($timestampColumn, '<=', now()->subHours($hours))
                ->get()
                ->each(function (SupplyRequest $req) use (&$overdueCount, $status, $hours, $description): void {
                    if ($this->notifyOverdueOnce($req, 'overdue_'.$status, "Đơn cấp phát #{$req->request_code} {$description} quá {$hours} giờ.")) {
                        $overdueCount++;
                    }
                });
        }

        return $overdueCount;
    }

    private function notifyOverdueOnce(SupplyRequest $request, string $stage, string $message): bool
    {
        if ($request->last_overdue_alert_stage === $stage
            && $request->last_overdue_alert_at
            && $request->last_overdue_alert_at->gte(now()->subDay())) {
            return false;
        }

        $request->update([
            'last_overdue_alert_at' => now(),
            'last_overdue_alert_stage' => $stage,
        ]);
        $this->notifyStakeholders($request, 'overdue_alert', $message);

        return true;
    }
}
