<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
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
        return RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('is_central_warehouse', true)
                  ->orWhere('warehouse_type', 'central');
            })
            ->first();
    }

    /**
     * Ensure a Central Warehouse branch exists for a restaurant.
     */
    public function ensureCentralWarehouse(int $restaurantId): RestaurantBranch
    {
        $warehouse = $this->getCentralWarehouse($restaurantId);
        if ($warehouse) {
            return $warehouse;
        }

        $restaurant = \App\Models\Restaurant::findOrFail($restaurantId);

        return RestaurantBranch::create([
            'restaurant_id' => $restaurantId,
            'code' => 'WH-CENTRAL-' . $restaurantId,
            'name' => 'Kho Tổng ' . $restaurant->name,
            'status' => 'active',
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
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
        });

        return $branch->fresh();
    }

    /**
     * Sinh mã yêu cầu cấp phát duy nhất chống xung đột (race condition).
     */
    public function generateUniqueRequestCode(int $restaurantId): string
    {
        $prefix = 'SR-' . Carbon::now()->format('Ymd') . '-';
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $count = SupplyRequest::where('restaurant_id', $restaurantId)
                ->whereDate('created_at', Carbon::today())
                ->count() + 1 + $attempt;
            $candidate = $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            if (! SupplyRequest::where('restaurant_id', $restaurantId)->where('request_code', $candidate)->exists()) {
                return $candidate;
            }
        }
        return $prefix . strtoupper(bin2hex(random_bytes(3)));
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
        $this->assertNotSelfApproval($request, $approver, 'created_by', 'Bạn không thể tự duyệt đơn cấp phát do chính mình tạo.');

        if (! in_array($request->status, [SupplyRequest::STATUS_PENDING, 'draft'])) {
            throw new InvalidArgumentException('Chỉ có thể duyệt đơn ở trạng thái chờ duyệt.');
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
                    'restaurant_id'     => $request->restaurant_id,
                    'branch_id'         => $request->from_branch_id,
                    'ingredient_id'     => $item->ingredient_id,
                    'supply_request_id' => $request->id,
                    'reservation_type'  => 'supply_request',
                    'quantity'          => $approvedQty,
                    'expires_at'        => now()->addDays(7),
                    'created_by'        => $approver->id,
                ]);
            }

            $request->update([
                'status'       => SupplyRequest::STATUS_APPROVED,
                'approved_by'  => $approver->id,
                'approved_at'  => now(),
                'total_amount' => $totalAmount,
                'notes'        => $notes ? ($request->notes . "\n[Duyệt]: " . $notes) : $request->notes,
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
                $batchId   = $picked['batch_id'] ?? null;

                // Khóa chặt: số lượng thực xuất không được vượt số lượng đã duyệt
                if ($actualQty > (float) $item->approved_quantity) {
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
                    'batch_id'                   => $batchId,
                    'warehouse_location_id'      => $picked['warehouse_location_id'] ?? null,
                    'shortage_notes'             => $picked['notes'] ?? null,
                    'non_fefo_reason'            => $picked['non_fefo_reason'] ?? null,
                ]);
            }

            $request->update([
                'status'      => SupplyRequest::STATUS_PREPARING,
                'prepared_by' => $picker->id,
                'prepared_at' => now(),
            ]);

            $this->notifyStakeholders($request, 'prepared');

            return $request->fresh(['items.ingredient', 'items.batch']);
        });
    }

    /**
     * Duyệt số lượng xuất kho cuối cùng (Layer 2 - Warehouse Manager sign-off).
     */
    public function approveDispatch(SupplyRequest $request, User $manager): SupplyRequest
    {
        $this->assertSameRestaurant($request, $manager);
        $this->assertNotSelfApproval($request, $manager, 'prepared_by', 'Người soạn hàng không được tự duyệt số lượng xuất.');

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }

        if ($request->status !== SupplyRequest::STATUS_PREPARING) {
            throw new InvalidArgumentException('Đơn hàng phải qua bước soạn hàng trước khi Trưởng kho duyệt xuất.');
        }

        $request->update([
            'status'               => SupplyRequest::STATUS_DISPATCH_PENDING,
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
        $this->assertNotSelfApproval($request, $handoverPerson, 'approved_by', 'Người duyệt đơn không được tự xuất kho.');
        $this->assertNotSelfApproval($request, $handoverPerson, 'dispatch_approved_by', 'Người duyệt xuất không được tự bàn giao hàng.');

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
                $qtyToDeduct = (float) $item->effective_dispatched_quantity;

                // Kiểm tra lại không vượt quá số lượng đã duyệt
                if ($qtyToDeduct > (float) $item->approved_quantity) {
                    throw new InvalidArgumentException("Số lượng thực xuất ({$qtyToDeduct}) vượt quá số lượng đã duyệt ({$item->approved_quantity}) cho nguyên liệu #{$item->ingredient_id}.");
                }

                if ($request->from_branch_id) {
                    // Lock inventory record for atomic update
                    $centralInventory = Inventory::where('restaurant_id', $request->restaurant_id)
                        ->where('branch_id', $request->from_branch_id)
                        ->where('ingredient_id', $item->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $centralInventory || (float) $centralInventory->quantity_on_hand < $qtyToDeduct) {
                        $onHand = (float) ($centralInventory?->quantity_on_hand ?? 0);
                        throw new InvalidArgumentException("Tồn Kho Tổng chỉ còn {$onHand}, không đủ để thực xuất {$qtyToDeduct} cho nguyên liệu #{$item->ingredient_id}.");
                    }

                    // Trừ tồn Kho Tổng
                    $centralInventory->decrement('quantity_on_hand', $qtyToDeduct);

                    // Trừ tồn theo lô nếu có chọn lô
                    if ($item->batch_id) {
                        $batch = InventoryBatch::where('restaurant_id', $request->restaurant_id)
                            ->where('branch_id', $request->from_branch_id)
                            ->where('ingredient_id', $item->ingredient_id)
                            ->where('id', $item->batch_id)
                            ->lockForUpdate()
                            ->first();

                        if ($batch) {
                            if ((float) $batch->quantity_remaining < $qtyToDeduct) {
                                throw new InvalidArgumentException("Lô #{$batch->batch_code} không đủ tồn để trừ ({$batch->quantity_remaining} < {$qtyToDeduct}).");
                            }
                            $batch->decrement('quantity_remaining', $qtyToDeduct);
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
                    InventoryTransaction::createWithIdempotency([
                        'restaurant_id'   => $request->restaurant_id,
                        'branch_id'       => $request->from_branch_id,
                        'ingredient_id'   => $item->ingredient_id,
                        'inventory_id'    => $centralInventory->id,
                        'performed_by'    => $handoverPerson->id,
                        'type'            => 'transfer',
                        'direction'       => 'out',
                        'quantity'        => $qtyToDeduct,
                        'unit_cost'       => $item->unit_cost,
                        'total_cost'      => round($item->unit_cost * $qtyToDeduct, 2),
                        'source_type'     => 'supply_request',
                        'source_id'       => $request->id,
                        'idempotency_key' => "dispatch_sr_{$request->id}_item_{$item->id}",
                        'notes'           => "Xuất kho cấp phát cho chi nhánh {$request->toBranch?->name} theo đơn {$request->request_code}",
                        'occurred_at'     => now(),
                    ]);
                }
            }

            $request->update([
                'status'        => SupplyRequest::STATUS_DISPATCHED,
                'seal_code'     => $sealCode ?: $request->seal_code,
                'dispatched_by' => $handoverPerson->id,
                'dispatched_at' => now(),
                'handover_by'   => $handoverPerson->id,
                'handover_at'   => now(),
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
        $this->assertNotSelfApproval($request, $receiver, 'dispatched_by', 'Người xuất kho không được tự xác nhận nhận hàng.');

        $central = $this->getCentralWarehouse($request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Đơn cấp phát không xuất phát từ Kho Tổng hiện tại.');
        }
        $this->assertCentralIngredients(
            $request->restaurant_id,
            (int) $central->id,
            $request->items->pluck('ingredient_id')
        );

        if (! in_array($request->status, [SupplyRequest::STATUS_DISPATCHED, SupplyRequest::STATUS_PARTIAL_RECEIVED, SupplyRequest::STATUS_COMPLETED])) {
            throw new InvalidArgumentException('Chỉ đơn đang giao mới có thể xác nhận nhận hàng.');
        }

        if ($request->status === SupplyRequest::STATUS_COMPLETED) {
            return $request;
        }

        return DB::transaction(function () use ($request, $receiver, $receivedItems, $receiptPhotoPath, $signaturePath, $notes, $receiptPhotoHash, $signatureHash) {
            $lockedRequest = SupplyRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();
            $hasShortage = false;

            foreach ($lockedRequest->items as $item) {
                $item->lockForUpdate();
                $dispatchedQty = (float) $item->effective_dispatched_quantity;
                $previouslyReceivedQty = (float) ($item->received_quantity ?? 0);
                $targetTotalRecQty = $dispatchedQty;

                if (! empty($receivedItems)) {
                    foreach ($receivedItems as $recItem) {
                        if ($recItem['id'] == $item->id && isset($recItem['received_quantity'])) {
                            $targetTotalRecQty = (float) $recItem['received_quantity'];
                            break;
                        }
                    }
                }

                if ($targetTotalRecQty > $dispatchedQty) {
                    throw new InvalidArgumentException("Số lượng nhận ({$targetTotalRecQty}) không được vượt quá số lượng Kho Tổng đã xuất ({$dispatchedQty}) cho nguyên liệu #{$item->ingredient_id}.");
                }

                if ($targetTotalRecQty < $previouslyReceivedQty) {
                    throw new InvalidArgumentException("Số lượng nhận mới ({$targetTotalRecQty}) không được nhỏ hơn số lượng đã nhận trước đó ({$previouslyReceivedQty}).");
                }

                $incrementalQty = $targetTotalRecQty - $previouslyReceivedQty;

                if ($targetTotalRecQty < $dispatchedQty) {
                    $hasShortage = true;
                }

                $item->update(['received_quantity' => $targetTotalRecQty]);

                if ($incrementalQty > 0) {
                    // Cộng phần nhận tăng thêm vào tồn kho Chi Nhánh Nhận
                    $branchInventory = Inventory::firstOrCreate(
                        [
                            'restaurant_id' => $request->restaurant_id,
                            'branch_id'     => $request->to_branch_id,
                            'ingredient_id' => $item->ingredient_id,
                        ],
                        [
                            'quantity_on_hand' => 0,
                        ]
                    );

                    $branchInventory->lockForUpdate();
                    $branchInventory->increment('quantity_on_hand', $incrementalQty);

                    // Ghi Ledger Nhập Kho Chi Nhánh
                    $idempotencyKey = "receive_sr_{$request->id}_item_{$item->id}_prev_{$previouslyReceivedQty}_to_{$targetTotalRecQty}";

                    InventoryTransaction::createWithIdempotency([
                        'restaurant_id'   => $request->restaurant_id,
                        'branch_id'       => $request->to_branch_id,
                        'ingredient_id'   => $item->ingredient_id,
                        'inventory_id'    => $branchInventory->id,
                        'performed_by'    => $receiver->id,
                        'type'            => 'transfer',
                        'direction'       => 'in',
                        'quantity'        => $incrementalQty,
                        'unit_cost'       => $item->unit_cost,
                        'total_cost'      => round($item->unit_cost * $incrementalQty, 2),
                        'source_type'     => 'supply_request',
                        'source_id'       => $request->id,
                        'idempotency_key' => $idempotencyKey,
                        'notes'           => "Nhập kho cấp phát từ Kho Tổng theo đơn {$request->request_code} (+{$incrementalQty})",
                        'occurred_at'     => now(),
                    ]);
                }
            }

            if ($hasShortage && (blank($receiptPhotoPath) && blank($request->receipt_photo_path) || blank($signaturePath) && blank($request->receiver_signature_path))) {
                throw new InvalidArgumentException('Bắt buộc đính kèm ảnh thực tế và chữ ký người nhận khi giao nhận thiếu hoặc hỏng.');
            }

            $finalStatus = $hasShortage ? SupplyRequest::STATUS_DISPUTED : SupplyRequest::STATUS_COMPLETED;

            $request->update([
                'status'                  => $finalStatus,
                'received_by'             => $receiver->id,
                'received_at'             => now(),
                'receipt_photo_path'      => $receiptPhotoPath ?: $request->receipt_photo_path,
                'receipt_photo_hash'      => $receiptPhotoHash ?: $request->receipt_photo_hash,
                'receiver_signature_path' => $signaturePath ?: $request->receiver_signature_path,
                'receiver_signature_hash' => $signatureHash ?: $request->receiver_signature_hash,
                'received_notes'          => $notes ?: $request->received_notes,
                'discrepancy_flag'        => $hasShortage,
            ]);

            // Nếu có nhận thiếu: Tự động kích hoạt Governance Service để mở tranh chấp
            if ($hasShortage && ! empty($receivedItems)) {
                app(WarehouseGovernanceService::class)->checkAndCreateDisputesFromSupplyRequest($request, $receivedItems);
                $this->notifyStakeholders($request, 'disputed');
            } else {
                $this->notifyStakeholders($request, 'received');
            }

            return $request->fresh(['items.ingredient', 'fromBranch', 'toBranch', 'receiver']);
        });
    }

    /**
     * Hủy đơn yêu cầu cấp phát (giải phóng ngay lập tức các giữ chỗ tồn kho).
     */
    public function cancelSupplyRequest(SupplyRequest $request, User $user, string $reason): SupplyRequest
    {
        $this->assertSameRestaurant($request, $user);
        $central = $this->getCentralWarehouse((int) $request->restaurant_id);
        if (! $central || (int) $request->from_branch_id !== (int) $central->id) {
            throw new InvalidArgumentException('Chỉ được hủy đơn cấp phát xuất từ Kho Tổng.');
        }

        if (! $request->canBeCancelled()) {
            throw new InvalidArgumentException('Không thể hủy đơn hàng đã xuất kho hoặc đã hoàn tất.');
        }

        return DB::transaction(function () use ($request, $user, $reason) {
            // Giải phóng reservation giữ chỗ
            InventoryReservation::where('supply_request_id', $request->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $request->update([
                'status'        => SupplyRequest::STATUS_CANCELLED,
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

        if (in_array($request->status, [SupplyRequest::STATUS_COMPLETED, SupplyRequest::STATUS_DISPATCHED])) {
            throw new InvalidArgumentException('Không thể từ chối đơn hàng đã xuất kho hoặc hoàn thành.');
        }

        return DB::transaction(function () use ($request, $user, $reason) {
            // Giải phóng reservation nếu có
            InventoryReservation::where('supply_request_id', $request->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $request->update([
                'status'           => SupplyRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by'      => $user->id,
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
                ->orWhere('branch_id', $centralBranchId));
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
                        'requests'        => [],
                    ];
                }
                $qty = (float) $item->requested_quantity;
                $totalDemandByIngredient[$ingId]['total_requested'] += $qty;
                $totalDemandByIngredient[$ingId]['requests'][] = [
                    'request_id'   => $req->id,
                    'item_id'      => $item->id,
                    'to_branch_id' => $req->to_branch_id,
                    'branch_name'  => $req->toBranch?->name ?? "CN #{$req->to_branch_id}",
                    'requested_qty'=> $qty,
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
            $totalDemand    = $data['total_requested'];
            $ingredient     = Ingredient::find($ingId);

            $allocationRatio = ($totalDemand > 0 && $availableStock < $totalDemand)
                ? round($availableStock / $totalDemand, 4)
                : 1.0;

            foreach ($data['requests'] as $reqData) {
                $suggestedQty = round($reqData['requested_qty'] * $allocationRatio, 2);
                $suggestions[] = [
                    'request_id'      => $reqData['request_id'],
                    'item_id'         => $reqData['item_id'],
                    'branch_name'     => $reqData['branch_name'],
                    'ingredient_id'   => $ingId,
                    'ingredient_name' => $ingredient?->name ?? "NL #{$ingId}",
                    'requested_qty'   => $reqData['requested_qty'],
                    'available_stock' => $availableStock,
                    'suggested_qty'   => $suggestedQty,
                    'shortage_qty'    => max(0, round($reqData['requested_qty'] - $suggestedQty, 2)),
                    'is_shortage'     => $availableStock < $totalDemand,
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
            $requestCode = $parentRequest->request_code . '-BO';

            $existingBackorder = SupplyRequest::where('restaurant_id', $restaurantId)
                ->where('request_code', $requestCode)
                ->first();
            if ($existingBackorder) {
                return $existingBackorder->load(['items.ingredient', 'fromBranch', 'toBranch']);
            }

            $backorder = SupplyRequest::create([
                'restaurant_id'           => $restaurantId,
                'request_code'            => $requestCode,
                'from_branch_id'          => $parentRequest->from_branch_id,
                'to_branch_id'            => $parentRequest->to_branch_id,
                'parent_request_id'       => $parentRequest->id,
                'created_by'              => $user->id,
                'status'                  => SupplyRequest::STATUS_PENDING,
                'notes'                   => "Đơn giao bù tự động từ đơn gốc #{$parentRequest->request_code}",
                'total_amount'            => 0,
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
                $unitCost   = (float) ($ingredient->average_cost ?? 0);
                $lineCost   = round($unitCost * $shortageQty, 2);
                $totalAmount += $lineCost;

                SupplyRequestItem::create([
                    'supply_request_id'  => $backorder->id,
                    'ingredient_id'      => $ingredient->id,
                    'requested_quantity' => $shortageQty,
                    'approved_quantity'  => $shortageQty,
                    'unit_cost'          => $unitCost,
                    'total_cost'         => $lineCost,
                    'unit_symbol'        => $ingredient->unit?->symbol ?? 'kg',
                    'notes'              => "Giao bù phần thiếu từ đơn #{$parentRequest->request_code}",
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
            'total_requests_month'  => $totalRequests,
            'completed_month'       => $completedCount,
            'fill_rate_percent'     => $fillRate,
            'otif_percent'          => $otifPercent,
            'fefo_compliance'       => $fefoCompliance,
            'waste_ratio_percent'   => $wasteRatio,
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
            Log::warning("Không thể gửi notification cho đơn cấp phát #{$request->id}: " . $e->getMessage());
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
            $this->notifyStakeholders($req, 'overdue_alert', "Đơn cấp phát #{$req->request_code} đã chờ duyệt quá 24 giờ.");
            $overdueCount++;
        }

        // Đơn đã duyệt nhưng chưa soạn hàng quá 24h
        $preparingOverdue = SupplyRequest::where('restaurant_id', $restaurantId)
            ->where('status', SupplyRequest::STATUS_APPROVED)
            ->where('approved_at', '<=', now()->subHours(24))
            ->get();

        foreach ($preparingOverdue as $req) {
            $this->notifyStakeholders($req, 'overdue_alert', "Đơn cấp phát #{$req->request_code} đã được duyệt hơn 24 giờ nhưng chưa hoàn tất soạn hàng.");
            $overdueCount++;
        }

        return $overdueCount;
    }
}
