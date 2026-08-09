<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use App\Notifications\ApprovalRequestedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalService
{
    public function __construct(
        private SalaryService $salaryService,
        private InventoryService $inventoryService,
        private OrderRefundService $orderRefundService,
        private OrderItemCancellationService $orderItemCancellationService,
        private CentralWarehouseService $warehouseService,
    ) {}

    /**
     * Tạo yêu cầu chờ phê duyệt và thông báo đến Owner.
     */
    public function submitRequest(string $operationType, array $data, User $requester): ApprovalRequest
    {
        $request = ApprovalRequest::create([
            'restaurant_id' => $requester->restaurant_id,
            'branch_id' => $requester->branch_id,
            'requester_id' => $requester->id,
            'operation_type' => $operationType,
            'operation_data' => $data,
            'status' => 'pending',
        ]);

        // Thông báo Owner
        $owner = User::where('restaurant_id', $requester->restaurant_id)
            ->role('owner')
            ->first();

        if ($owner) {
            $owner->notify(new ApprovalRequestedNotification($request, $requester));
        }

        Cache::forget("pending_approvals:{$requester->restaurant_id}");

        return $request;
    }

    /**
     * Owner phê duyệt → thực thi thao tác → thông báo requester.
     */
    public function approve(ApprovalRequest $approval, User $reviewer): void
    {
        if ($approval->operation_type === 'order_refund' && ! $reviewer->hasRole('owner')) {
            throw new \Exception('Chỉ chủ doanh nghiệp mới được duyệt yêu cầu hoàn tiền.');
        }

        DB::transaction(function () use ($approval, $reviewer) {
            // Khóa bi quan bản ghi phê duyệt để tránh chạy trùng lặp
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if ($lockedApproval->status !== 'pending') {
                throw new \Exception('Yêu cầu này đã được xử lý trước đó.');
            }

            $this->executeOperation($lockedApproval, $reviewer->id);

            $lockedApproval->update([
                'status' => 'approved',
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        Cache::forget("pending_approvals:{$approval->restaurant_id}");

        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'approved'));
    }

    /**
     * Owner từ chối → thông báo requester.
     */
    public function reject(ApprovalRequest $approval, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($approval, $reviewer, $reason) {
            // Khóa bi quan bản ghi phê duyệt
            $lockedApproval = ApprovalRequest::where('id', $approval->id)->lockForUpdate()->firstOrFail();
            if ($lockedApproval->status !== 'pending') {
                throw new \Exception('Yêu cầu này đã được xử lý trước đó.');
            }

            $lockedApproval->update([
                'status' => 'rejected',
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            if ($approval->operation_type === 'shift_checkin' && ! empty($approval->operation_data['assignment_id'])) {
                $assignment = ScheduleAssignment::withoutGlobalScopes()->find($approval->operation_data['assignment_id']);
                if ($assignment && in_array($assignment->status, ['scheduled', 'pending_checkin'])) {
                    $assignment->update(['status' => 'absent']);
                }
            } elseif ($approval->operation_type === 'shift_checkout' && ! empty($approval->operation_data['assignment_id'])) {
                $assignment = ScheduleAssignment::withoutGlobalScopes()->find($approval->operation_data['assignment_id']);
                if ($assignment && $assignment->status === 'pending_checkout') {
                    $assignment->update(['status' => 'checked_in']);
                }
            }
        });

        Cache::forget("pending_approvals:{$approval->restaurant_id}");

        $approval->requester?->notify(new ApprovalDecisionNotification($approval, 'rejected'));
    }

    /**
     * Thực thi thao tác đã được phê duyệt.
     */
    private function executeOperation(ApprovalRequest $approval, int $reviewerId): void
    {
        $data = $approval->operation_data;

        match ($approval->operation_type) {
            'inventory_create' => $this->executeInventoryCreate($data, $approval->restaurant_id),
            'inventory_update' => $this->executeInventoryUpdate($data, $approval->restaurant_id),
            'inventory_delete' => $this->executeInventoryDelete($data, $approval->restaurant_id),
            'inventory_adjustment' => $this->executeInventoryAdjustment($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_purchase' => $this->executePurchase($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_waste' => $this->executeWaste($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_stocktake' => $this->executeStocktake($data, $approval->restaurant_id, $approval->requester_id),
            'inventory_recipe_save' => $this->executeRecipeSave($data, $approval->restaurant_id),
            'inventory_recipe_delete' => $this->executeRecipeDelete($data, $approval->restaurant_id),
            'warehouse_set_central' => $this->warehouseService->setCentralWarehouse($approval->restaurant_id, (int) $data['branch_id']),
            'warehouse_price_update' => $this->executeWarehousePriceUpdate($data, $approval->restaurant_id),
            'warehouse_supply_approve' => $this->executeSupplyApprove($data, $approval->restaurant_id, $reviewerId),
            'warehouse_supply_dispatch' => $this->executeSupplyDispatch($data, $approval->restaurant_id, $reviewerId),
            'warehouse_supply_reject' => $this->executeSupplyReject($data, $approval->restaurant_id, $reviewerId),
            'salary_adjustment' => $this->executeSalaryAdjustment($data, $approval->restaurant_id),
            'shift_checkin' => $this->executeShiftCheckin($data, $approval->restaurant_id),
            'shift_checkout' => $this->executeShiftCheckout($data, $approval->restaurant_id),
            'order_refund' => $this->executeOrderRefund($data, $approval->restaurant_id, $reviewerId),
            'order_item_cancel' => $this->executeOrderItemCancellation($data, $approval->restaurant_id, $reviewerId),
            default => null,
        };
    }

    private function executeInventoryCreate(array $data, int $restaurantId): void
    {
        $branchId = $data['branch_id'] ?? null;
        Ingredient::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? ('ING-'.strtoupper(Str::random(6))),
            'unit_id' => $data['unit_id'],
            'category_name' => $data['category'] ?? $data['category_name'] ?? null,
            'storage_type' => $data['storage_type'] ?? 'dry',
            'default_shelf_life_days' => $data['default_shelf_life_days'] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'expiry_warning_days' => $data['expiry_warning_days'] ?? 3,
            'min_stock_level' => $data['min_stock_level'] ?? 0,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'auto_waste_end_of_day' => $data['auto_waste_end_of_day'] ?? false,
            'status' => 'active',
        ]);
    }

    private function executeInventoryUpdate(array $data, int $restaurantId): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['ingredient_id']);
        $ingredient->update($data['attributes'] ?? []);
    }

    private function executeInventoryDelete(array $data, int $restaurantId): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['ingredient_id']);
        $ingredient->delete();
    }

    private function executeInventoryAdjustment(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase([
            'ingredient_id' => $data['ingredient_id'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? 0,
            'notes' => $data['notes'] ?? 'Điều chỉnh tồn kho theo phê duyệt của Chủ nhà hàng',
            'branch_id' => $data['branch_id'] ?? null,
        ], $restaurantId, $performedBy);
    }

    private function executeOrderItemCancellation(array $data, int $restaurantId, int $reviewerId): void
    {
        $item = OrderItem::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['order_item_id']);
        $reviewer = User::findOrFail($reviewerId);

        $this->orderItemCancellationService->cancel(
            $item,
            $reviewer,
            (string) $data['reason'],
        );
    }

    private function executeOrderRefund(array $data, int $restaurantId, int $reviewerId): void
    {
        $order = Order::where('restaurant_id', $restaurantId)->findOrFail($data['order_id']);
        $reviewer = User::findOrFail($reviewerId);

        $this->orderRefundService->process($order, [
            'refund_type' => $data['refund_type'],
            'refund_amount' => $data['refund_amount'],
            'reason' => $data['reason'],
        ], $reviewer);
    }

    private function executeShiftCheckin(array $data, int $restaurantId): void
    {
        $assignment = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->find($data['assignment_id']);

        if ($assignment && in_array($assignment->status, ['scheduled', 'pending_checkin'])) {
            $checkInTime = ! empty($data['requested_at']) ? Carbon::parse($data['requested_at']) : now();
            $assignment->update([
                'check_in_at' => $checkInTime,
                'status' => 'checked_in',
            ]);
            $assignment->employee?->flushShiftAccessCache();
            Cache::forget("employee_dashboard:{$assignment->employee_id}:".now()->format('Y-m'));
        }
    }

    private function executeShiftCheckout(array $data, int $restaurantId): void
    {
        $assignment = ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->find($data['assignment_id']);

        if ($assignment && in_array($assignment->status, ['checked_in', 'pending_checkout'])) {
            $checkOutTime = ! empty($data['requested_at']) ? Carbon::parse($data['requested_at']) : now();
            $assignment->update([
                'check_out_at' => $checkOutTime,
                'status' => 'completed',
            ]);
            $assignment->employee?->flushShiftAccessCache();
            Cache::forget("employee_dashboard:{$assignment->employee_id}:".now()->format('Y-m'));
        }
    }

    private function executePurchase(array $data, int $restaurantId, int $performedBy): void
    {
        $this->inventoryService->executePurchase($data, $restaurantId, $performedBy);
    }

    private function executeWaste(array $data, int $restaurantId, int $performedBy): void
    {
        $transaction = $this->inventoryService->executeWaste($data, $restaurantId, $performedBy);

        $ingredientQuery = Ingredient::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when(! empty($data['branch_id']), fn ($q) => $q->where('branch_id', $data['branch_id']));
        $ingredient = $ingredientQuery->findOrFail($data['ingredient_id']);
        $wasteQty = (float) $data['quantity'];
        // Khớp khoản khấu trừ (nếu có) với giá vốn thực tế của lô đã trừ.
        $wasteCost = $transaction
            ? (float) $transaction->total_cost
            : $wasteQty * (float) $ingredient->average_cost;

        if ($transaction && ! empty($data['employee_id']) && $wasteCost > 0) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->when(! empty($data['branch_id']), fn ($q) => $q->where('branch_id', $data['branch_id']))
                ->find($data['employee_id']);
            if ($employee) {
                $allowedRatio = $ingredient ? (float) ($ingredient->allowed_waste_ratio ?? 0) : 0;
                $penaltyAmount = $wasteCost * (1 - $allowedRatio / 100);
                $penaltyAmount = max(0.0, $penaltyAmount);

                if ($penaltyAmount > 0) {
                    $salary = $this->salaryService->getOrCreateDraft($restaurantId, $employee, now()->toDateString());
                    $this->salaryService->addAdjustment($salary, [
                        'employee_id' => $employee->id,
                        'type' => 'inventory_loss',
                        'amount' => $penaltyAmount,
                        'reason' => "Hao hụt {$ingredient->name}: {$wasteQty} ".($ingredient->unit?->symbol ?? '').' — '.number_format($wasteCost).'đ'.' (Đã khấu trừ '.$allowedRatio.'% định mức cho phép)',
                        'reference_id' => $transaction->id,
                        'reference_type' => InventoryTransaction::class,
                        'status' => 'applied',
                    ]);
                }
            }
        }
    }

    private function executeRecipeSave(array $data, int $restaurantId): void
    {
        $productId = (int) $data['product_id'];
        $items = collect($data['items'] ?? []);

        ProductRecipe::where('restaurant_id', $restaurantId)
            ->where('product_id', $productId)
            ->whereNotIn('ingredient_id', $items->pluck('ingredient_id'))
            ->delete();

        foreach ($items as $item) {
            ProductRecipe::updateOrCreate([
                'restaurant_id' => $restaurantId,
                'product_id' => $productId,
                'ingredient_id' => $item['ingredient_id'],
            ], [
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => $item['quantity'],
                'waste_rate' => $item['waste_rate'] ?? 0,
            ]);
        }

        ProductRecipe::where('restaurant_id', $restaurantId)->where('product_id', $productId)->exists()
            ? Product::where('restaurant_id', $restaurantId)->whereKey($productId)->update(['is_available' => true])
            : Product::where('restaurant_id', $restaurantId)->whereKey($productId)->update(['is_available' => false]);
    }

    private function executeRecipeDelete(array $data, int $restaurantId): void
    {
        $recipe = ProductRecipe::where('restaurant_id', $restaurantId)->findOrFail($data['recipe_id']);
        $productId = $recipe->product_id;
        $recipe->delete();
        Product::where('restaurant_id', $restaurantId)->whereKey($productId)
            ->update(['is_available' => ProductRecipe::where('restaurant_id', $restaurantId)->where('product_id', $productId)->exists()]);
    }

    private function executeStocktake(array $data, int $restaurantId, int $performedBy): void
    {
        DB::transaction(function () use ($data, $restaurantId, $performedBy): void {
            foreach ($data['reconcile_items'] as $item) {
                $ingredient = Ingredient::withoutGlobalScopes()->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $data['branch_id'])->findOrFail($item['ingredient_id']);
                $inventory = Inventory::withoutGlobalScopes()->firstOrCreate([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $data['branch_id'],
                    'ingredient_id' => $ingredient->id,
                ], ['quantity_on_hand' => 0, 'theoretical_quantity' => 0, 'last_cost' => 0]);
                $current = (float) $inventory->quantity_on_hand;
                $physical = (float) $item['physical_qty'];
                $delta = $physical - $current;
                if ($delta !== 0.0) {
                    $transaction = InventoryTransaction::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $data['branch_id'],
                        'ingredient_id' => $ingredient->id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $performedBy,
                        'type' => 'stocktake',
                        'direction' => $delta > 0 ? 'in' : 'out',
                        'quantity' => abs($delta),
                        'unit_cost' => (float) $ingredient->average_cost,
                        'total_cost' => abs($delta) * (float) $ingredient->average_cost,
                        'notes' => $data['notes'] ?? 'Kiểm kê kho đã được Chủ nhà hàng duyệt',
                        'occurred_at' => now(),
                    ]);
                    app(InventoryService::class)->reconcileBatchesForStocktake($inventory, $current, $physical, $transaction, $performedBy);
                }
                $inventory->update(['quantity_on_hand' => $physical, 'theoretical_quantity' => $physical, 'last_counted_at' => now(), 'updated_by' => $performedBy]);
            }
        });
    }

    private function executeWarehousePriceUpdate(array $data, int $restaurantId): void
    {
        foreach ($data['prices'] as $row) {
            Ingredient::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereKey((int) $row['ingredient_id'])
                ->update(['average_cost' => round((float) $row['average_cost'], 2)]);
        }
    }

    private function executeSupplyApprove(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $this->warehouseService->approveSupplyRequest($request, User::findOrFail($reviewerId), $data['items'] ?? null, $data['notes'] ?? null);
    }

    private function executeSupplyDispatch(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $this->warehouseService->dispatchSupplyRequest($request, User::findOrFail($reviewerId), $data['seal_code'] ?? null);
    }

    private function executeSupplyReject(array $data, int $restaurantId, int $reviewerId): void
    {
        $request = SupplyRequest::where('restaurant_id', $restaurantId)->findOrFail($data['supply_request_id']);
        $this->warehouseService->rejectSupplyRequest($request, User::findOrFail($reviewerId), $data['reason']);
    }

    private function executeSalaryAdjustment(array $data, int $restaurantId): void
    {
        $salary = Salary::withoutGlobalScopes()->findOrFail($data['salary_id']);

        if ($data['type'] === 'advance') {
            $employee = $salary->employee;
            if ($employee) {
                $salaryMonth = Carbon::parse($salary->pay_period_start);
                $calculationDate = today()->isSameMonth($salaryMonth) ? today() : $salaryMonth->endOfMonth();
                $earnedWages = $this->salaryService->calculateEarnedWagesForMonth($employee, $calculationDate->toDateString());

                $existingAdvanceAmount = SalaryAdjustment::withoutGlobalScopes()
                    ->where('salary_id', $salary->id)
                    ->where('type', 'advance')
                    ->where('status', 'applied')
                    ->sum('amount');

                $limit = $earnedWages * 0.50;
                if (($existingAdvanceAmount + $data['amount']) > $limit) {
                    throw new \Exception(sprintf('Yêu cầu tạm ứng vượt quá giới hạn 50%% tiền lương tích lũy trong tháng (Hạn mức tạm ứng tối đa: %sđ).', number_format($limit)));
                }
            }
        }

        $this->salaryService->addAdjustment($salary, [
            'employee_id' => $salary->employee_id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'status' => 'applied',
        ]);
    }
}
