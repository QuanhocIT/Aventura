<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryCountService
{
    /**
     * Khởi tạo phiên kiểm kê mới.
     */
    public function startCountSession(
        int $restaurantId,
        int $branchId,
        User $creator,
        string $type = 'periodic',
        bool $blindCount = false,
        ?array $ingredientIds = null
    ): InventoryCountSession {
        return DB::transaction(function () use ($restaurantId, $branchId, $creator, $type, $blindCount, $ingredientIds) {
            $session = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id'     => $branchId,
                'type'          => $type,
                'status'        => 'in_progress',
                'blind_count'   => $blindCount,
                'counted_by'    => $creator->id,
                'started_at'    => now(),
            ]);

            // Lấy danh sách tồn kho hiện tại của chi nhánh
            $query = Inventory::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId);

            if (! empty($ingredientIds)) {
                $query->whereIn('ingredient_id', $ingredientIds);
            }

            $inventories = $query->get();

            foreach ($inventories as $inv) {
                InventoryCountItem::create([
                    'count_session_id'  => $session->id,
                    'ingredient_id'     => $inv->ingredient_id,
                    'expected_quantity' => (float) $inv->quantity_on_hand,
                    'variance_quantity' => 0,
                    'variance_percent'  => 0,
                    'variance_value'    => 0,
                ]);
            }

            return $session->load(['items.ingredient.unit', 'branch', 'countedBy']);
        });
    }

    public function submitCounts(InventoryCountSession $session, User $user, array $countedItems): InventoryCountSession
    {
        if ($session->status !== 'in_progress') {
            throw new InvalidArgumentException('Chỉ có thể nhập số lượng đếm khi phiên kiểm kê đang diễn ra.');
        }

        // Tự động xác định người đếm 1 hay người đếm 2 từ backend assignment
        $isCounter1 = ((int) $session->counted_by === (int) $user->id);
        $isCounter2 = ((int) $session->second_counted_by === (int) $user->id);

        if (! $isCounter1 && ! $isCounter2) {
            if (empty($session->second_counted_by)) {
                $session->update(['second_counted_by' => $user->id]);
                $isCounter2 = true;
            } else {
                throw new InvalidArgumentException('Tài khoản của bạn không được phân công kiểm kê trong phiên này.');
            }
        }

        $isSecondCounter = $isCounter2 && ! $isCounter1;

        return DB::transaction(function () use ($session, $user, $countedItems, $isSecondCounter) {

            $totalVarianceVal = 0;

            foreach ($countedItems as $counted) {
                $item = InventoryCountItem::where('count_session_id', $session->id)
                    ->where('id', $counted['id'])
                    ->first();

                if (! $item) {
                    continue;
                }

                $qty = (float) $counted['counted_quantity'];

                if ($isSecondCounter) {
                    $item->counted_quantity_2 = $qty;
                } else {
                    $item->counted_quantity_1 = $qty;
                }

                $finalQty = $item->counted_quantity_2 ?? $item->counted_quantity_1 ?? $item->expected_quantity;
                $expected = (float) $item->expected_quantity;
                $variance = $finalQty - $expected;

                $unitCost = (float) ($item->ingredient->average_cost ?? 0);
                $varValue = round($variance * $unitCost, 2);
                $varPct   = $expected > 0 ? round(($variance / $expected) * 100, 2) : ($variance != 0 ? 100 : 0);

                $item->update([
                    'counted_quantity_1' => $item->counted_quantity_1,
                    'counted_quantity_2' => $item->counted_quantity_2,
                    'final_quantity'     => $finalQty,
                    'variance_quantity'  => $variance,
                    'variance_percent'   => $varPct,
                    'variance_value'     => $varValue,
                    'notes'              => $counted['notes'] ?? $item->notes,
                ]);

                $totalVarianceVal += abs($varValue);
            }

            $session->update([
                'total_variance_value' => $totalVarianceVal,
                'completed_at'         => now(),
            ]);

            return $session->fresh(['items.ingredient.unit']);
        });
    }

    /**
     * Gửi duyệt kết quả kiểm kê.
     */
    public function finalizeAndSubmitForApproval(InventoryCountSession $session, ?string $variancePhotoPath = null, ?string $notes = null): InventoryCountSession
    {
        if ($session->status !== 'in_progress') {
            throw new InvalidArgumentException('Phiên kiểm kê không ở trạng thái sẵn sàng gửi duyệt.');
        }

        $governance = app(WarehouseGovernanceService::class);
        $totalLoss  = (float) $session->total_variance_value;

        // Thống kê tổng phần trăm sai lệch lớn nhất
        $maxVarPct = (float) $session->items()->max('variance_percent');
        $isOver = $governance->isVarianceOverThreshold($session->restaurant_id, $totalLoss, $maxVarPct);

        if ($isOver && blank($variancePhotoPath) && blank($session->variance_photo_path)) {
            throw new InvalidArgumentException('Sai lệch vượt quá ngưỡng cho phép của quy tắc quản trị. Bắt buộc đính kèm ảnh bằng chứng hoặc biên bản giải trình!');
        }

        $session->update([
            'status'                   => 'pending_approval',
            'requires_owner_approval'  => $isOver,
            'variance_photo_path'      => $variancePhotoPath ?: $session->variance_photo_path,
            'notes'                    => $notes ? ($session->notes . "\n[Gửi duyệt]: " . $notes) : $session->notes,
        ]);

        return $session->fresh();
    }

    /**
     * Phê duyệt kiểm kê & áp dụng điều chỉnh tồn kho thực tế.
     */
    public function approveCountSession(InventoryCountSession $session, User $approver): InventoryCountSession
    {
        if ($session->status !== 'pending_approval') {
            throw new InvalidArgumentException('Chỉ phiên kiểm kê ở trạng thái chờ duyệt mới có thể phê duyệt.');
        }

        // Self-approval check: người đếm 1 không được tự duyệt
        if ($session->counted_by === $approver->id && ! $approver->isOwner() && ! $approver->isSuperAdmin()) {
            throw new InvalidArgumentException('Người thực hiện đếm không được tự phê duyệt kết quả kiểm kê của chính mình!');
        }

        return DB::transaction(function () use ($session, $approver) {
            foreach ($session->items as $item) {
                $variance = (float) $item->variance_quantity;
                if ($variance == 0) {
                    continue;
                }

                $inventory = Inventory::firstOrCreate(
                    [
                        'restaurant_id' => $session->restaurant_id,
                        'branch_id'     => $session->branch_id,
                        'ingredient_id' => $item->ingredient_id,
                    ],
                    ['quantity_on_hand' => 0]
                );

                $inventory->lockForUpdate();

                $direction = $variance > 0 ? 'in' : 'out';
                $absQty    = abs($variance);

                if ($direction === 'in') {
                    $inventory->increment('quantity_on_hand', $absQty);
                } else {
                    $inventory->decrement('quantity_on_hand', $absQty);
                }

                // Ghi Ledger Bất Biến
                InventoryTransaction::createWithIdempotency([
                    'restaurant_id'   => $session->restaurant_id,
                    'branch_id'       => $session->branch_id,
                    'ingredient_id'   => $item->ingredient_id,
                    'inventory_id'    => $inventory->id,
                    'performed_by'    => $approver->id,
                    'type'            => 'inventory_count',
                    'direction'       => $direction,
                    'quantity'        => $absQty,
                    'unit_cost'       => $item->ingredient->average_cost ?? 0,
                    'total_cost'      => abs((float) $item->variance_value),
                    'source_type'     => 'inventory_count',
                    'source_id'       => $session->id,
                    'idempotency_key' => "count_session_{$session->id}_item_{$item->id}",
                    'notes'           => "Điều chỉnh tồn kho theo Phiên kiểm kê #{$session->id} (Sai lệch: {$variance} {$item->ingredient->unit?->symbol})",
                    'occurred_at'     => now(),
                ]);

                // Cập nhật last_counted_at
                $inventory->update(['last_counted_at' => now()]);
            }

            $session->update([
                'status'      => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $session->fresh(['items.ingredient.unit', 'approver']);
        });
    }
}
