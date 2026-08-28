<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryCountService
{
    public function __construct(
        protected InventoryCountScopeService $countScope,
        protected MaterialClosingService $materialClosing,
    ) {}

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
            if ((int) $creator->restaurant_id !== $restaurantId) {
                throw new InvalidArgumentException('Tài khoản không được phép kiểm kê chi nhánh này.');
            }
            $this->countScope->assertCanAccessBranch($creator, $branchId);
            $branch = RestaurantBranch::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->whereKey($branchId)
                ->lockForUpdate()
                ->first();
            if (! $branch) {
                throw new InvalidArgumentException('Chi nhánh kiểm kê không tồn tại hoặc đã ngừng hoạt động.');
            }

            if (InventoryCountSession::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->whereIn('status', ['draft', 'in_progress', 'pending_approval'])
                ->exists()) {
                throw new InvalidArgumentException('Chi nhanh nay dang co phien kiem ke chua dong. Hay hoan tat, huy hoac xu ly phien hien tai truoc.');
            }

            $session = InventoryCountSession::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'type' => $type,
                'status' => 'in_progress',
                'blind_count' => $blindCount,
                'counted_by' => $creator->id,
                'started_at' => now(),
            ]);

            // Lấy danh sách nguyên liệu của chi nhánh (kể cả chưa có bản ghi tồn kho hoặc tồn = 0)
            $ingredientQuery = Ingredient::where('restaurant_id', $restaurantId)
                ->where(function ($scope) use ($branchId) {
                    $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });

            if (! empty($ingredientIds)) {
                $ingredientQuery->whereIn('id', $ingredientIds);
            }

            $ingredients = $ingredientQuery->get();

            if ($ingredients->isEmpty()) {
                throw new InvalidArgumentException('Khong co nguyen lieu phu hop de tao phien kiem ke.');
            }

            $inventoryMap = Inventory::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->whereIn('ingredient_id', $ingredients->pluck('id'))
                ->pluck('quantity_on_hand', 'ingredient_id');

            foreach ($ingredients as $ing) {
                $onHand = (float) ($inventoryMap[$ing->id] ?? 0);
                InventoryCountItem::create([
                    'count_session_id' => $session->id,
                    'ingredient_id' => $ing->id,
                    'expected_quantity' => $onHand,
                    'variance_quantity' => 0,
                    'variance_percent' => 0,
                    'variance_value' => 0,
                ]);
            }

            return $session->load(['items.ingredient.unit', 'branch', 'countedBy']);
        });
    }

    public function submitCounts(InventoryCountSession $session, User $user, array $countedItems, bool $isSecondCounter = false): InventoryCountSession
    {
        $this->assertSessionScope($session, $user);

        return DB::transaction(function () use ($session, $user, $countedItems, $isSecondCounter) {
            $session = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'in_progress') {
                throw new InvalidArgumentException('Phien kiem ke da duoc xu ly, khong the ghi them ket qua.');
            }

            // Gán người đếm 2 trong cùng transaction với kết quả đếm để tránh
            // hai tài khoản cùng lúc chiếm quyền đếm lần 2.
            $isCounter1 = (int) $session->counted_by === (int) $user->id;
            $isCounter2 = (int) $session->second_counted_by === (int) $user->id;

            if ($isSecondCounter && $isCounter1) {
                throw new InvalidArgumentException('Nguoi dem lan 1 khong duoc tu nhap vai tro dem kiem tra lan 2.');
            }

            if (! $isCounter1 && ! $isCounter2 && ! $user->hasRole('inventory_staff')) {
                throw new InvalidArgumentException('Tài khoản của bạn chưa được phân công kiểm kê trong phiên này.');
            }

            if (! $isCounter1 && ! $isCounter2) {
                if (empty($session->second_counted_by)) {
                    $session->update(['second_counted_by' => $user->id]);
                    $isCounter2 = true;
                } else {
                    throw new InvalidArgumentException('Tài khoản của bạn không được phân công kiểm kê trong phiên này.');
                }
            }

            $effectiveSecondCounter = $isCounter2 && ! $isCounter1;

            $totalVarianceVal = 0;

            foreach ($countedItems as $counted) {
                $item = InventoryCountItem::where('count_session_id', $session->id)
                    ->where('id', $counted['id'])
                    ->with('ingredient')
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    continue;
                }

                $qty = (float) $counted['counted_quantity'];

                if ($effectiveSecondCounter) {
                    $item->counted_quantity_2 = $qty;
                } else {
                    $item->counted_quantity_1 = $qty;
                }

                $hasBothCounts = $item->counted_quantity_1 !== null && $item->counted_quantity_2 !== null;
                $countsMatch = $hasBothCounts
                    && abs((float) $item->counted_quantity_1 - (float) $item->counted_quantity_2) <= 0.001;
                $finalQty = $hasBothCounts && ! $countsMatch
                    ? null
                    : ($item->counted_quantity_2 ?? $item->counted_quantity_1);

                $updates = [
                    'counted_quantity_1' => $item->counted_quantity_1,
                    'counted_quantity_2' => $item->counted_quantity_2,
                    'notes' => $counted['notes'] ?? $item->notes,
                ];

                if ($hasBothCounts && ! $countsMatch) {
                    $updates += [
                        'final_quantity' => null,
                        'variance_quantity' => 0,
                        'variance_percent' => 0,
                        'variance_value' => 0,
                        'reconciliation_status' => 'pending',
                    ];
                } else {
                    $expected = (float) $item->expected_quantity;
                    $variance = (float) $finalQty - $expected;
                    $unitCost = $this->unitCostForItem($session, $item);
                    $varValue = round($variance * $unitCost, 2);
                    $varPct = $expected > 0 ? round(($variance / $expected) * 100, 2) : ($variance != 0 ? 100 : 0);

                    $updates += [
                        'final_quantity' => $finalQty,
                        'variance_quantity' => $variance,
                        'variance_percent' => $varPct,
                        'variance_value' => $varValue,
                        'reconciliation_status' => 'not_required',
                    ];
                    $totalVarianceVal += abs($varValue);
                }

                $item->update($updates);
            }

            $totalVarianceVal = (float) InventoryCountItem::where('count_session_id', $session->id)
                ->sum(DB::raw('ABS(variance_value)'));

            $session->update([
                'total_variance_value' => $totalVarianceVal,
            ]);

            $this->materialClosing->refreshSummary($session);

            return $session->fresh(['items.ingredient.unit']);
        });
    }

    /**
     * Gửi duyệt kết quả kiểm kê.
     */
    public function assignSecondCounter(InventoryCountSession $session, User $assigner, User $counter): InventoryCountSession
    {
        $this->assertSessionScope($session, $assigner);

        return DB::transaction(function () use ($session, $assigner, $counter) {
            $locked = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $assigner->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['draft', 'in_progress'], true)) {
                throw new InvalidArgumentException('Chỉ có thể phân công người đếm khi phiên đang mở.');
            }
            if ((int) $locked->counted_by === (int) $counter->id) {
                throw new InvalidArgumentException('Người đếm 1 không thể đồng thời là người đếm 2.');
            }
            if ((int) $counter->restaurant_id !== (int) $assigner->restaurant_id
                || ! $this->countScope->canAccessBranch($counter, (int) $locked->branch_id)) {
                throw new InvalidArgumentException('Người đếm 2 phải thuộc cùng phạm vi chi nhánh của phiên.');
            }
            if ($locked->second_counted_by && (int) $locked->second_counted_by !== (int) $counter->id) {
                throw new InvalidArgumentException('Phiên đã có người đếm 2; không thể thay đổi sau khi đã ghi nhận kết quả.');
            }

            $locked->update(['second_counted_by' => $counter->id]);

            return $locked->fresh(['items.ingredient.unit', 'countedBy', 'secondCountedBy']);
        });
    }

    public function reconcileItem(
        InventoryCountSession $session,
        User $user,
        int $itemId,
        float $finalQuantity,
        string $notes,
    ): InventoryCountSession {
        $this->assertSessionScope($session, $user);

        return DB::transaction(function () use ($session, $user, $itemId, $finalQuantity, $notes) {
            $lockedSession = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== 'in_progress') {
                throw new InvalidArgumentException('Chi co the doi soat dong dem khi phien dang dien ra.');
            }

            $item = InventoryCountItem::where('count_session_id', $lockedSession->id)
                ->whereKey($itemId)
                ->with('ingredient')
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->counted_quantity_1 === null || $item->counted_quantity_2 === null) {
                throw new InvalidArgumentException('Dong hang phai co du ca hai lan dem truoc khi doi soat.');
            }

            if (blank(trim($notes))) {
                throw new InvalidArgumentException('Bat buoc ghi chu khi chot ket qua doi soat.');
            }

            $expected = (float) $item->expected_quantity;
            $variance = $finalQuantity - $expected;
            $unitCost = $this->unitCostForItem($lockedSession, $item);
            $varValue = round($variance * $unitCost, 2);
            $varPct = $expected > 0 ? round(($variance / $expected) * 100, 2) : ($variance != 0 ? 100 : 0);

            $item->update([
                'final_quantity' => $finalQuantity,
                'variance_quantity' => $variance,
                'variance_percent' => $varPct,
                'variance_value' => $varValue,
                'reconciliation_status' => 'resolved',
                'reconciliation_notes' => trim($notes),
                'reconciled_by' => $user->id,
                'reconciled_at' => now(),
            ]);

            $totalVarianceValue = (float) InventoryCountItem::where('count_session_id', $lockedSession->id)
                ->sum(DB::raw('ABS(variance_value)'));
            $lockedSession->update(['total_variance_value' => round($totalVarianceValue, 2)]);
            $this->materialClosing->refreshSummary($lockedSession);

            return $lockedSession->fresh(['items.ingredient.unit', 'countedBy', 'secondCountedBy']);
        });
    }

    public function finalizeAndSubmitForApproval(InventoryCountSession $session, ?string $variancePhotoPath = null, ?string $notes = null): InventoryCountSession
    {
        return DB::transaction(function () use ($session, $variancePhotoPath, $notes) {
            $session = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $session->restaurant_id)
                ->with('items.ingredient')
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'in_progress') {
                throw new InvalidArgumentException('Phiên kiểm kê không ở trạng thái sẵn sàng gửi duyệt.');
            }

            if ($session->items()->where('reconciliation_status', 'pending')->exists()) {
                throw new InvalidArgumentException('Khong the gui duyet khi con dong co hai lan dem khong khop nhau.');
            }

            // Bắt buộc đếm đủ toàn bộ mặt hàng trong phiên
            $uncountedCount = $session->items()->whereNull('final_quantity')->count();

            if ($uncountedCount > 0) {
                throw new InvalidArgumentException("Không thể gửi duyệt: Còn {$uncountedCount} mặt hàng chưa được kiểm đếm trong phiên này!");
            }

            $governance = app(WarehouseGovernanceService::class);
            $totalLoss = (float) $session->total_variance_value;

            // Ngưỡng phải xét theo độ lớn tuyệt đối để thiếu kho cũng bị kiểm soát.
            $maxVarPct = (float) $session->items->max(fn ($item) => abs((float) $item->variance_percent));
            $isOver = $governance->isVarianceOverThreshold($session->restaurant_id, $totalLoss, $maxVarPct);

            if ($isOver && blank($variancePhotoPath) && blank($session->variance_photo_path)) {
                throw new InvalidArgumentException('Sai lệch vượt quá ngưỡng cho phép của quy tắc quản trị. Bắt buộc đính kèm ảnh bằng chứng hoặc biên bản giải trình!');
            }

            $session->update([
                'status' => 'pending_approval',
                'requires_owner_approval' => $isOver,
                'variance_photo_path' => $variancePhotoPath ?: $session->variance_photo_path,
                'notes' => $notes ? ($session->notes."\n[Gửi duyệt]: ".$notes) : $session->notes,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Phê duyệt kiểm kê & áp dụng điều chỉnh tồn kho thực tế.
     */
    public function rejectCountSession(InventoryCountSession $session, User $approver, string $reason): InventoryCountSession
    {
        $this->assertSessionScope($session, $approver);

        if (blank(trim($reason))) {
            throw new InvalidArgumentException('Bat buoc nhap ly do tu choi phien kiem ke.');
        }

        return DB::transaction(function () use ($session, $approver, $reason) {
            $lockedSession = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $approver->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== 'pending_approval') {
                throw new InvalidArgumentException('Chi phien dang cho phe duyet moi co the tu choi.');
            }

            $lockedSession->update([
                'status' => 'rejected',
                'rejection_reason' => trim($reason),
                'rejected_by' => $approver->id,
                'rejected_at' => now(),
            ]);

            return $lockedSession->fresh(['items.ingredient.unit', 'rejectedBy']);
        });
    }

    public function reopenRejectedSession(InventoryCountSession $session, User $user): InventoryCountSession
    {
        $this->assertSessionScope($session, $user);

        return DB::transaction(function () use ($session, $user) {
            $lockedSession = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== 'rejected') {
                throw new InvalidArgumentException('Chi phien da bi tu choi moi co the mo lai.');
            }

            $lockedSession->update([
                'status' => 'in_progress',
                'completed_at' => null,
            ]);

            return $lockedSession->fresh(['items.ingredient.unit', 'countedBy', 'secondCountedBy']);
        });
    }

    public function cancelCountSession(InventoryCountSession $session, User $user, string $reason): InventoryCountSession
    {
        $this->assertSessionScope($session, $user);

        if (blank(trim($reason))) {
            throw new InvalidArgumentException('Bat buoc nhap ly do huy phien kiem ke.');
        }

        return DB::transaction(function () use ($session, $user, $reason) {
            $lockedSession = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedSession->status, ['draft', 'in_progress', 'pending_approval'], true)) {
                throw new InvalidArgumentException('Phien kiem ke nay khong con o trang thai co the huy.');
            }

            $lockedSession->update([
                'status' => 'cancelled',
                'cancel_reason' => trim($reason),
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            return $lockedSession->fresh(['items.ingredient.unit', 'cancelledBy']);
        });
    }

    public function approveCountSession(InventoryCountSession $session, User $approver): InventoryCountSession
    {
        $this->assertSessionScope($session, $approver);

        if ($session->status !== 'pending_approval') {
            throw new InvalidArgumentException('Chỉ phiên kiểm kê ở trạng thái chờ duyệt mới có thể phê duyệt.');
        }

        // Self-approval check: người đếm 1 không được tự duyệt trừ khi là Owner/Super Admin
        if ($session->counted_by === $approver->id && ! $approver->isOwner() && ! $approver->isSuperAdmin()) {
            throw new InvalidArgumentException('Người thực hiện đếm không được tự phê duyệt kết quả kiểm kê của chính mình!');
        }

        // Phân quyền hạn mức: nếu vượt ngưỡng thì chỉ Owner/Super Admin mới duyệt được
        if ($session->requires_owner_approval && ! $approver->isOwner() && ! $approver->isSuperAdmin()) {
            throw new InvalidArgumentException('Phiên kiểm kê này có sai lệch vượt ngưỡng quy chuẩn, bắt buộc Chủ nhà hàng phê duyệt.');
        }

        return DB::transaction(function () use ($session, $approver) {
            $session = InventoryCountSession::whereKey($session->id)
                ->where('restaurant_id', $approver->restaurant_id)
                ->with('items.ingredient.unit')
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'pending_approval') {
                throw new InvalidArgumentException('Phien kiem ke da duoc xu ly hoac khong con cho phe duyet.');
            }

            if ($session->counted_by === $approver->id && ! $approver->isOwner() && ! $approver->isSuperAdmin()) {
                throw new InvalidArgumentException('Nguoi thuc hien dem khong duoc tu phe duyet ket qua cua chinh minh.');
            }

            if ($session->requires_owner_approval && ! $approver->isOwner() && ! $approver->isSuperAdmin()) {
                throw new InvalidArgumentException('Phien nay vuot nguong sai lech va chi Chu nha hang moi duoc phe duyet.');
            }

            foreach ($session->items as $item) {
                $variance = (float) $item->variance_quantity;
                if ($variance == 0) {
                    continue;
                }

                $inventory = Inventory::where('restaurant_id', $session->restaurant_id)
                    ->where('branch_id', $session->branch_id)
                    ->where('ingredient_id', $item->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $session->restaurant_id,
                        'branch_id' => $session->branch_id,
                        'ingredient_id' => $item->ingredient_id,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                    ]);
                    $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
                }

                $direction = $variance > 0 ? 'in' : 'out';
                $absQty = abs($variance);
                $quantityBefore = (float) $inventory->quantity_on_hand;
                $quantityAfter = $direction === 'in'
                    ? $quantityBefore + $absQty
                    : $quantityBefore - $absQty;
                $theoreticalBefore = $inventory->effectiveTheoreticalQuantity();
                $inventory->update([
                    'quantity_on_hand' => $quantityAfter,
                    'theoretical_quantity' => $direction === 'in'
                        ? $theoreticalBefore + $absQty
                        : $theoreticalBefore - $absQty,
                ]);

                // Ghi Ledger Bất Biến
                $transaction = InventoryTransaction::createWithIdempotency([
                    'restaurant_id' => $session->restaurant_id,
                    'branch_id' => $session->branch_id,
                    'ingredient_id' => $item->ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $approver->id,
                    'type' => 'inventory_count',
                    'direction' => $direction,
                    'quantity' => $absQty,
                    'unit_cost' => $this->unitCostForItem($session, $item),
                    'total_cost' => abs((float) $item->variance_value),
                    'source_type' => 'inventory_count',
                    'source_id' => $session->id,
                    'idempotency_key' => "count_session_{$session->id}_item_{$item->id}",
                    'notes' => "Điều chỉnh tồn kho theo Phiên kiểm kê #{$session->id} (Sai lệch: {$variance} {$item->ingredient->unit?->symbol})",
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'occurred_at' => now(),
                ]);
                app(NegativeInventoryService::class)->sync($inventory, $transaction);

                // Cập nhật last_counted_at
                $inventory->update(['last_counted_at' => now()]);
            }

            $session->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            $this->materialClosing->refreshSummary($session);

            return $session->fresh(['items.ingredient.unit', 'approver']);
        });
    }

    private function assertSessionScope(InventoryCountSession $session, User $user): void
    {
        if ((int) $session->restaurant_id !== (int) $user->restaurant_id
            || ! $this->countScope->canAccessBranch($user, (int) $session->branch_id)
            || ! RestaurantBranch::where('restaurant_id', $session->restaurant_id)
                ->where('status', 'active')
                ->whereKey($session->branch_id)
                ->exists()) {
            throw new InvalidArgumentException('Phiên kiểm kê không thuộc phạm vi tài khoản hoặc chi nhánh đã ngừng hoạt động.');
        }
    }

    private function unitCostForItem(InventoryCountSession $session, InventoryCountItem $item): float
    {
        if (in_array($session->type, ['material_closing', 'branch_closing'], true) && $item->unit_cost !== null) {
            return (float) $item->unit_cost;
        }

        return (float) ($item->ingredient?->average_cost ?? 0);
    }
}
