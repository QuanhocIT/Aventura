<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplyRequestAnalyticsService
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
        protected WarehouseTaskService $taskService
    ) {}

    /**
     * Query nguyên liệu thuộc phạm vi Kho Tổng hoặc catalog dùng chung toàn chuỗi.
     */
    public function centralIngredientQuery(int $restaurantId, ?int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->when(
                $centralBranchId,
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $centralBranchId)
                    ->orWhereHas('inventories', fn ($inv) => $inv->where('branch_id', $centralBranchId))),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    /**
     * Xây dựng dữ liệu props tổng hợp cho các trang Kho Tổng.
     */
    public function getCentralWarehouseProps(Request $request): array
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('id', '!=', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', 'active')
            ->get();

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver', 'transporter', 'deliveryTask.assignee', 'warehouseTasks.assignee'])
            ->orderByDesc('id')
            ->get();

        $ingredients = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->with(['unit'])
            ->get();

        $warehouseStaff = $this->taskService->getWarehouseStaff($restaurantId);
        $warehouseTasks = $this->taskService->getWarehouseTasks($restaurantId);

        $warehouseSuppliers = Supplier::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch?->id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $warehousePurchaseOrders = PurchaseOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'preparing', 'shipping', 'delivered'])
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch?->id))
            ->with(['supplier:id,name', 'items.ingredient:id,name,unit_id', 'items.ingredient.unit:id,symbol'])
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'supplier_id' => $order->supplier_id,
                'supplier_name' => $order->supplier?->name,
                'status' => $order->status,
                'is_frozen' => (bool) $order->is_frozen,
                'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient?->name,
                    'quantity_ordered' => (float) $item->quantity_ordered,
                    'quantity_received' => (float) $item->quantity_received,
                    'price_per_unit' => (float) ($item->invoice_price_per_unit ?: $item->price_per_unit),
                    'unit' => $item->ingredient?->unit?->symbol,
                ])->values()->all(),
            ])->values();

        $receivingVouchers = WarehouseReceivingVoucher::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id))
            ->with([
                'items.ingredient.unit',
                'items.batch.location',
                'items.location',
                'documents',
                'receivedBy',
                'verifiedBy',
                'supplier:id,name,phone',
                'purchaseOrder:id,po_number,status',
            ])
            ->orderByDesc('received_at')
            ->limit(80)
            ->get();

        $centralInventory = $centralBranch
            ? Inventory::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch->id)
                ->with('ingredient.unit')
                ->get()
            : collect();

        $inventoryActivity = $centralBranch
            ? InventoryTransaction::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch->id)
                ->with(['ingredient:id,name,unit_id', 'ingredient.unit:id,symbol', 'performedBy:id,name'])
                ->orderByDesc('occurred_at')
                ->limit(80)
                ->get()
                ->map(fn (InventoryTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'ingredient' => $transaction->ingredient?->name,
                    'unit' => $transaction->ingredient?->unit?->symbol ?? 'đơn vị',
                    'type' => $transaction->type,
                    'direction' => $transaction->direction,
                    'quantity' => (float) $transaction->quantity,
                    'unit_cost' => (float) $transaction->unit_cost,
                    'total_cost' => (float) $transaction->total_cost,
                    'reference_code' => $transaction->reference_code,
                    'notes' => $transaction->notes,
                    'performed_by' => $transaction->performedBy?->name,
                    'occurred_at' => $transaction->occurred_at?->format('d/m/Y H:i'),
                ])
            : collect();

        $centralCatalogIngredients = $ingredients;

        $centralBatches = $centralBranch
            ? InventoryBatch::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch->id)
                ->where('quantity_remaining', '>', 0)
                ->with('location:id,location_code,is_quarantine,is_cold_storage')
                ->get()
            : collect();

        $reservedByIngredient = $centralBranch
            ? InventoryReservation::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch->id)
                ->whereNull('released_at')
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->selectRaw('ingredient_id, SUM(quantity) as quantity_reserved')
                ->groupBy('ingredient_id')
                ->pluck('quantity_reserved', 'ingredient_id')
            : collect();

        $batchesByIngredient = $centralBatches->groupBy('ingredient_id');
        $inventoryByIngredient = $centralInventory->keyBy('ingredient_id');
        $today = now()->startOfDay();

        $centralStockItems = $centralCatalogIngredients->map(function (Ingredient $ingredient) use ($inventoryByIngredient, $reservedByIngredient, $batchesByIngredient, $today): array {
            $inventory = $inventoryByIngredient->get($ingredient->id);
            $onHand = (float) ($inventory?->quantity_on_hand ?? 0);
            $theoretical = (float) ($inventory?->theoretical_quantity ?? $onHand);
            $reserved = (float) ($reservedByIngredient->get($ingredient->id, 0));
            $minimum = (float) ($ingredient->min_stock_level ?? 0);
            $batches = $batchesByIngredient->get($ingredient->id, collect())
                ->sortBy(fn (InventoryBatch $batch) => $batch->expiry_date?->timestamp ?? PHP_INT_MAX)
                ->map(function (InventoryBatch $batch) use ($today): array {
                    $daysRemaining = $batch->expiry_date
                        ? $today->diffInDays($batch->expiry_date->startOfDay(), false)
                        : null;
                    $isExpired = $batch->status === 'expired' || ($daysRemaining !== null && $daysRemaining < 0);

                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_code ?: $batch->batch_number ?: 'Không có mã lô',
                        'quantity_remaining' => (float) $batch->quantity_remaining,
                        'unit_cost' => (float) $batch->unit_cost,
                        'purchased_at' => $batch->purchased_at?->format('d/m/Y'),
                        'expiry_date' => $batch->expiry_date?->format('d/m/Y'),
                        'raw_expiry_date' => $batch->expiry_date?->toDateString(),
                        'days_remaining' => $daysRemaining,
                        'status' => $batch->status,
                        'lock_reason' => $batch->lock_reason,
                        'recall_note' => $batch->recall_note,
                        'locked_at' => $batch->locked_at?->format('d/m/Y H:i'),
                        'location_id' => $batch->location_id,
                        'location_code' => $batch->location?->location_code,
                        'is_quarantine' => (bool) $batch->location?->is_quarantine,
                        'is_expired' => $isExpired,
                        'is_expiring_soon' => ! $isExpired && $batch->status === 'active' && $daysRemaining !== null && $daysRemaining <= 3,
                    ];
                })
                ->values();

            $hasExpiredBatch = $batches->contains(fn (array $batch): bool => $batch['is_expired'] && $batch['quantity_remaining'] > 0);
            $hasExpiringBatch = $batches->contains(fn (array $batch): bool => $batch['is_expiring_soon']);
            $hasLockedBatch = $batches->contains(fn (array $batch): bool => in_array($batch['status'], ['locked', 'recalled'], true));
            $blockedQuantity = min(
                $onHand,
                (float) $batches
                    ->filter(fn (array $batch): bool => $batch['is_expired'] || in_array($batch['status'], ['locked', 'recalled'], true))
                    ->sum('quantity_remaining'),
            );
            $available = max(0, $onHand - $reserved - $blockedQuantity);
            $status = $onHand <= 0
                ? 'out'
                : ($hasExpiredBatch ? 'expired' : ($minimum > 0 && $onHand <= $minimum ? 'low' : ($hasExpiringBatch ? 'expiring' : ($hasLockedBatch ? 'locked' : 'normal'))));

            return [
                'id' => $ingredient->id,
                'inventory_id' => $inventory?->id,
                'name' => $ingredient->name,
                'sku' => $ingredient->sku,
                'category_name' => $ingredient->category_name,
                'storage_type' => $ingredient->storage_type ?? 'dry',
                'storage_type_label' => $ingredient->storage_type_label,
                'unit_symbol' => $ingredient->unit?->symbol ?? 'đv',
                'on_hand' => $onHand,
                'theoretical' => $theoretical,
                'variance' => round($theoretical - $onHand, 3),
                'reserved' => $reserved,
                'available' => $available,
                'blocked_quantity' => round($blockedQuantity, 3),
                'min_stock_level' => $minimum,
                'reorder_level' => (float) ($ingredient->reorder_level ?? 0),
                'average_cost' => (float) ($ingredient->average_cost ?? $inventory?->last_cost ?? 0),
                'stock_value' => round($onHand * (float) ($ingredient->average_cost ?? $inventory?->last_cost ?? 0), 2),
                'status' => $status,
                'last_counted_at' => $inventory?->last_counted_at?->format('d/m/Y H:i'),
                'batches' => $batches,
            ];
        })->values();

        $centralLocations = $centralBranch
            ? WarehouseLocation::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch->id)
                ->where('status', 'active')
                ->orderBy('zone')
                ->orderBy('location_code')
                ->get()
            : collect();

        $inventorySummary = [
            'ingredient_count' => $centralStockItems->count(),
            'on_hand_quantity' => round((float) $centralStockItems->sum('on_hand'), 3),
            'on_hand_value' => round((float) $centralStockItems->sum('stock_value'), 2),
            'reserved_quantity' => round((float) $centralStockItems->sum('reserved'), 3),
            'available_quantity' => round((float) $centralStockItems->sum('available'), 3),
            'blocked_quantity' => round((float) $centralStockItems->sum('blocked_quantity'), 3),
            'low_stock_count' => $centralStockItems->whereIn('status', ['out', 'low'])->count(),
            'expiring_soon_count' => $centralBatches->filter(fn ($batch) => $batch->expiry_date && $batch->expiry_date->between(now()->startOfDay(), now()->addDays(3)->endOfDay()) && $batch->status === 'active')->count(),
            'expired_batch_count' => $centralBatches->filter(fn ($batch) => $batch->status === 'expired' || ($batch->expiry_date && $batch->expiry_date->lt(now()->startOfDay())))->count(),
            'locked_batch_count' => $centralBatches->whereIn('status', ['locked', 'recalled'])->count(),
            'zero_stock_count' => $centralStockItems->where('on_hand', '<=', 0)->count(),
            'variance_quantity' => round((float) $centralStockItems->sum('variance'), 3),
            'location_count' => $centralLocations->count(),
            'quarantine_location_count' => $centralLocations->where('is_quarantine', true)->count(),
        ];

        $receivingStats = WarehouseReceivingVoucher::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id));

        $receivingSummary = [
            'total' => (clone $receivingStats)->count(),
            'today' => (clone $receivingStats)->whereDate('received_at', today())->count(),
            'pending_review' => (clone $receivingStats)->whereIn('status', ['draft', 'discrepancy', 'pending_review', 'pending_disposition'])->count(),
            'draft' => (clone $receivingStats)->where('status', 'draft')->count(),
            'discrepancy_vouchers' => (clone $receivingStats)->whereIn('status', ['discrepancy', 'pending_review'])->count(),
            'pending_disposition' => (clone $receivingStats)->where('status', 'pending_disposition')->count(),
            'confirmed' => (clone $receivingStats)->where('status', 'confirmed')->count(),
            'closed' => (clone $receivingStats)->where('status', 'closed')->count(),
            'discrepancy_quantity' => round((float) (clone $receivingStats)->sum(DB::raw('ABS(total_discrepancy_qty)')), 3),
        ];

        $supplyAnalytics = $this->buildSupplyRequestAnalytics(
            $requests,
            $branches,
            $ingredients,
            $centralBranch,
        );

        return [
            'centralBranch' => $centralBranch,
            'branches' => $branches,
            'supplyRequests' => $requests,
            'ingredients' => $ingredients,
            'canManageWarehouse' => $isOwnerOrSuperAdmin || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            'canApproveRequests' => $isOwnerOrSuperAdmin || $user->hasRole('warehouse_manager') || $user->can('supply_requests.approve'),
            'canDispatchRequests' => $isOwnerOrSuperAdmin || $user->hasRole('warehouse_manager') || $user->can('supply_requests.dispatch'),
            'warehouseStaff' => $warehouseStaff,
            'warehouseTasks' => $warehouseTasks,
            'warehouseTaskSummary' => $this->taskService->buildWarehouseTaskSummary($warehouseTasks),
            'supplyAnalytics' => $supplyAnalytics,
            'centralWarehouseAnalytics' => $this->warehouseService->getCentralWarehouseAnalytics($restaurantId),
            'receivingVouchers' => $receivingVouchers,
            'receivingSummary' => $receivingSummary,
            'inventorySummary' => $inventorySummary,
            'warehouseLocations' => $centralLocations,
            'centralStockItems' => $centralStockItems,
            'inventoryActivity' => $inventoryActivity,
            'canReconcile' => $isOwnerOrSuperAdmin || $user->can('adjust_inventory'),
            'warehouseSuppliers' => $warehouseSuppliers,
            'warehousePurchaseOrders' => $warehousePurchaseOrders,
            'supplyChainAlerts' => app(CentralWarehouseSupplyChainService::class)->alerts($restaurantId),
            'supplyChainReconciliation' => app(CentralWarehouseSupplyChainService::class)->reconciliation($restaurantId),
        ];
    }

    /**
     * Phân tích nhu cầu cấp phát và gợi ý kế hoạch nhập nguyên liệu 7 ngày tới.
     */
    public function buildSupplyRequestAnalytics(
        Collection $requests,
        Collection $branches,
        Collection $ingredients,
        ?RestaurantBranch $centralBranch,
    ): array {
        $now = now();
        $historyStart = $now->copy()->subDays(27)->startOfDay();
        $last7Start = $now->copy()->subDays(6)->startOfDay();
        $previous7Start = $now->copy()->subDays(13)->startOfDay();
        $validStatuses = array_merge([
            SupplyRequest::STATUS_PENDING,
            SupplyRequest::STATUS_APPROVED,
            SupplyRequest::STATUS_PREPARING,
            SupplyRequest::STATUS_DISPATCH_PENDING,
            SupplyRequest::STATUS_DISPATCHED,
            SupplyRequest::STATUS_PARTIAL_RECEIVED,
            SupplyRequest::STATUS_DISPUTED,
            SupplyRequest::STATUS_COMPLETED,
        ], [SupplyRequest::STATUS_RETURNED, SupplyRequest::STATUS_DESTROYED]);
        $openStatuses = [
            SupplyRequest::STATUS_PENDING,
            SupplyRequest::STATUS_APPROVED,
            SupplyRequest::STATUS_PREPARING,
            SupplyRequest::STATUS_DISPATCH_PENDING,
            SupplyRequest::STATUS_DISPATCHED,
            SupplyRequest::STATUS_PARTIAL_RECEIVED,
            SupplyRequest::STATUS_DISPUTED,
        ];

        $isInWindow = static function ($request, $from, $to = null): bool {
            if (! $request->created_at) {
                return false;
            }

            $createdAt = $request->created_at;

            return $createdAt->gte($from) && (! $to || $createdAt->lt($to));
        };

        $validRequests = $requests->filter(
            fn ($request) => in_array($request->status, $validStatuses, true)
        );
        $todayRequests = $validRequests->filter(fn ($request) => $isInWindow($request, $now->copy()->startOfDay()));
        $historyRequests = $validRequests->filter(fn ($request) => $isInWindow($request, $historyStart));
        $last7Requests = $validRequests->filter(fn ($request) => $isInWindow($request, $last7Start));
        $previous7Requests = $validRequests->filter(
            fn ($request) => $isInWindow($request, $previous7Start, $last7Start)
        );

        $sumItems = static fn (Collection $requestSet): float => round(
            $requestSet->sum(fn ($request) => $request->items->sum(
                fn ($item) => (float) ($item->requested_quantity ?? 0)
            )),
            3,
        );

        $sumDemandByIngredient = static function (Collection $requestSet): array {
            $demand = [];

            foreach ($requestSet as $request) {
                foreach ($request->items as $item) {
                    $ingredientId = (int) $item->ingredient_id;
                    $demand[$ingredientId] = ($demand[$ingredientId] ?? 0) + (float) ($item->requested_quantity ?? 0);
                }
            }

            return $demand;
        };

        $historyDemand = $sumDemandByIngredient($historyRequests);
        $last7Demand = $sumDemandByIngredient($last7Requests);
        $previous7Demand = $sumDemandByIngredient($previous7Requests);
        $openDemand = $sumDemandByIngredient($validRequests->filter(
            fn ($request) => in_array($request->status, $openStatuses, true)
        ));

        $centralStock = $centralBranch
            ? Inventory::where('restaurant_id', $centralBranch->restaurant_id)
                ->where('branch_id', $centralBranch->id)
                ->with(['ingredient.unit'])
                ->get()
                ->keyBy('ingredient_id')
            : collect();
        $ingredientMap = $ingredients->keyBy('id');

        $daily = collect(range(6, 0))->map(function (int $daysAgo) use ($now, $validRequests, $isInWindow, $sumItems): array {
            $date = $now->copy()->subDays($daysAgo)->startOfDay();
            $nextDate = $date->copy()->addDay();
            $dayRequests = $validRequests->filter(fn ($request) => $isInWindow($request, $date, $nextDate));

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'weekday' => $date->format('D'),
                'requests' => $dayRequests->count(),
                'items' => $sumItems($dayRequests),
                'value' => round((float) $dayRequests->sum(fn ($request) => (float) $request->total_amount), 2),
            ];
        })->values()->all();

        $branchStats = $branches->map(function (RestaurantBranch $branch) use ($last7Requests): array {
            $branchRequests = $last7Requests->where('to_branch_id', $branch->id);

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'requests' => $branchRequests->count(),
                'items' => round((float) $branchRequests->sum(fn ($request) => $request->items->sum(
                    fn ($item) => (float) ($item->requested_quantity ?? 0)
                )), 3),
                'value' => round((float) $branchRequests->sum(fn ($request) => (float) $request->total_amount), 2),
            ];
        })->sortByDesc('requests')->values()->all();

        $demandDetails = [];
        foreach ($historyRequests as $request) {
            foreach ($request->items as $item) {
                $ingredientId = (int) $item->ingredient_id;
                $ingredient = $ingredientMap->get($ingredientId) ?? $item->ingredient;

                if (! $ingredient) {
                    continue;
                }

                if (! isset($demandDetails[$ingredientId])) {
                    $demandDetails[$ingredientId] = [
                        'ingredient_id' => $ingredientId,
                        'name' => $ingredient->name,
                        'sku' => $ingredient->sku,
                        'unit_symbol' => $item->unit_symbol ?: ($ingredient->unit?->symbol ?? 'đv'),
                        'total_quantity' => 0,
                        'total_value' => 0,
                        'request_ids' => [],
                        'last_requested_at' => null,
                    ];
                }

                $quantity = (float) ($item->requested_quantity ?? 0);
                $demandDetails[$ingredientId]['total_quantity'] += $quantity;
                $demandDetails[$ingredientId]['total_value'] += $quantity * (float) ($item->unit_cost ?? 0);
                $demandDetails[$ingredientId]['request_ids'][$request->id] = true;

                if (! $demandDetails[$ingredientId]['last_requested_at'] || $request->created_at->gt($demandDetails[$ingredientId]['last_requested_at'])) {
                    $demandDetails[$ingredientId]['last_requested_at'] = $request->created_at;
                }
            }
        }

        $topIngredients = collect($demandDetails)
            ->map(fn (array $detail): array => [
                'ingredient_id' => $detail['ingredient_id'],
                'name' => $detail['name'],
                'sku' => $detail['sku'],
                'unit_symbol' => $detail['unit_symbol'],
                'total_quantity' => round($detail['total_quantity'], 3),
                'total_value' => round($detail['total_value'], 2),
                'request_count' => count($detail['request_ids']),
                'last_requested_at' => $detail['last_requested_at']?->toISOString(),
            ])
            ->sortByDesc('total_quantity')
            ->values()
            ->take(8)
            ->all();

        $recommendations = [];
        foreach ($ingredientMap as $ingredientId => $ingredient) {
            $ingredientId = (int) $ingredientId;
            $total28 = (float) ($historyDemand[$ingredientId] ?? 0);
            $averageDaily = $total28 / 28;
            $forecast7 = $averageDaily * 7;
            $stock = (float) ($centralStock->get($ingredientId)?->quantity_on_hand ?? 0);
            $openQuantity = (float) ($openDemand[$ingredientId] ?? 0);
            $reorderLevel = max((float) ($ingredient->reorder_level ?? 0), (float) ($ingredient->min_stock_level ?? 0));
            $targetStock = max($forecast7 + $openQuantity, $reorderLevel);
            $recommendedQuantity = max(0, round($targetStock - $stock, 3));
            $coverageDays = $averageDaily > 0 ? round($stock / $averageDaily, 1) : null;
            $last7Quantity = (float) ($last7Demand[$ingredientId] ?? 0);
            $previous7Quantity = (float) ($previous7Demand[$ingredientId] ?? 0);
            $trendPercent = $previous7Quantity > 0
                ? round((($last7Quantity - $previous7Quantity) / $previous7Quantity) * 100, 1)
                : ($last7Quantity > 0 ? 100 : 0);

            if ($recommendedQuantity <= 0 && $total28 <= 0 && $stock > $reorderLevel) {
                continue;
            }

            $priority = $recommendedQuantity > 0 && ($stock <= 0 || ($coverageDays !== null && $coverageDays < 2))
                ? 'urgent'
                : ($recommendedQuantity > 0 ? 'watch' : 'stable');
            $advice = match ($priority) {
                'urgent' => 'Ưu tiên bổ sung ngay; tồn kho không đủ an toàn cho nhu cầu dự kiến.',
                'watch' => $trendPercent >= 20
                    ? 'Nhu cầu đang tăng; nên đặt hàng sớm và theo dõi giá nhập.'
                    : 'Nên đưa vào kế hoạch nhập trong 7 ngày tới.',
                default => 'Tồn kho hiện đáp ứng được nhu cầu dự kiến.',
            };

            $recommendations[] = [
                'ingredient_id' => $ingredientId,
                'name' => $ingredient->name,
                'sku' => $ingredient->sku,
                'unit_symbol' => $ingredient->unit?->symbol ?? 'đv',
                'current_stock' => round($stock, 3),
                'open_quantity' => round($openQuantity, 3),
                'average_daily' => round($averageDaily, 3),
                'forecast_7d' => round($forecast7, 3),
                'coverage_days' => $coverageDays,
                'trend_percent' => $trendPercent,
                'recommended_quantity' => $recommendedQuantity,
                'estimated_cost' => round($recommendedQuantity * (float) ($ingredient->average_cost ?? 0), 2),
                'priority' => $priority,
                'advice' => $advice,
            ];
        }

        $priorityRank = ['urgent' => 0, 'watch' => 1, 'stable' => 2];
        usort($recommendations, static function (array $left, array $right) use ($priorityRank): int {
            $priorityCompare = ($priorityRank[$left['priority']] ?? 9) <=> ($priorityRank[$right['priority']] ?? 9);

            return $priorityCompare !== 0
                ? $priorityCompare
                : $right['recommended_quantity'] <=> $left['recommended_quantity'];
        });

        $urgentCount = count(array_filter($recommendations, fn (array $item) => $item['priority'] === 'urgent'));
        $trendLeader = collect($recommendations)->sortByDesc('trend_percent')->first();
        $insights = [];

        if ($todayRequests->count() > 0) {
            $insights[] = [
                'type' => $todayRequests->where('status', 'pending')->count() > 0 ? 'warning' : 'info',
                'title' => 'Đơn cần xử lý hôm nay',
                'message' => "Có {$todayRequests->count()} đơn từ chi nhánh, tổng {$sumItems($todayRequests)} đơn vị nguyên liệu. Hãy ưu tiên các đơn đang chờ duyệt.",
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'title' => 'Chưa có đơn mới hôm nay',
                'message' => 'Kho Tổng chưa nhận yêu cầu cấp phát mới trong ngày. Hãy kiểm tra tồn tối thiểu và lịch bán của các chi nhánh.',
            ];
        }

        if ($urgentCount > 0) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Có nguyên liệu cần bổ sung gấp',
                'message' => "{$urgentCount} nguyên liệu có mức tồn an toàn thấp hoặc không đủ cho dự báo 7 ngày.",
            ];
        }

        if ($trendLeader && $trendLeader['trend_percent'] >= 20) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Nhu cầu đang tăng',
                'message' => "{$trendLeader['name']} tăng {$trendLeader['trend_percent']}% so với 7 ngày trước; nên kiểm tra nhà cung cấp và thời gian giao hàng.",
            ];
        }

        $todayList = $todayRequests->sortByDesc('created_at')->take(8)->map(fn ($request): array => [
            'id' => $request->id,
            'request_code' => $request->request_code,
            'branch_name' => $request->toBranch?->name ?? 'Chi nhánh',
            'items' => $request->items->count(),
            'value' => round((float) $request->total_amount, 2),
            'status' => $request->status,
            'created_at' => $request->created_at?->toISOString(),
        ])->values()->all();

        $terminalStatuses = SupplyRequest::terminalStatuses();
        $operationalOpen = $requests->filter(fn ($request) => ! in_array($request->status, $terminalStatuses, true));
        $overdueRequests = $operationalOpen->filter(fn ($request) => $request->requested_delivery_date && $request->requested_delivery_date->isPast());
        $dueTodayRequests = $operationalOpen->filter(fn ($request) => $request->requested_delivery_date && $request->requested_delivery_date->isToday());
        $receivingRequests = $requests->filter(fn ($request) => in_array($request->status, SupplyRequest::receivingStatuses(), true));
        $dispatchedQuantity = (float) $receivingRequests->sum(fn ($request) => $request->items->sum(fn ($item) => $item->effective_dispatched_quantity));
        $receivedQuantity = (float) $receivingRequests->sum(fn ($request) => $request->items->sum(fn ($item) => (float) ($item->received_quantity ?? 0)));

        $branchOperations = $branches->map(function (RestaurantBranch $branch) use ($requests, $terminalStatuses): array {
            $branchRequests = $requests->where('to_branch_id', $branch->id);
            $open = $branchRequests->filter(fn ($request) => ! in_array($request->status, $terminalStatuses, true));
            $overdue = $open->filter(fn ($request) => $request->requested_delivery_date && $request->requested_delivery_date->isPast());
            $dispatched = (float) $branchRequests->sum(fn ($request) => $request->items->sum(fn ($item) => $item->effective_dispatched_quantity));
            $received = (float) $branchRequests->sum(fn ($request) => $request->items->sum(fn ($item) => (float) ($item->received_quantity ?? 0)));

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'open_requests' => $open->count(),
                'overdue_requests' => $overdue->count(),
                'disputed_requests' => $branchRequests->where('status', SupplyRequest::STATUS_DISPUTED)->count(),
                'fill_rate_percent' => $dispatched > 0 ? round(($received / $dispatched) * 100, 1) : 100.0,
            ];
        })->sortByDesc(fn ($row) => [$row['overdue_requests'], $row['open_requests']])->values()->all();

        return [
            'period_days' => 28,
            'generated_at' => $now->toISOString(),
            'summary' => [
                'today_requests' => $todayRequests->count(),
                'today_items' => $sumItems($todayRequests),
                'today_value' => round((float) $todayRequests->sum(fn ($request) => (float) $request->total_amount), 2),
                'today_pending' => $todayRequests->where('status', 'pending')->count(),
                'last7_requests' => $last7Requests->count(),
                'last7_items' => $sumItems($last7Requests),
                'last7_value' => round((float) $last7Requests->sum(fn ($request) => (float) $request->total_amount), 2),
                'average_daily_requests' => round($last7Requests->count() / 7, 1),
                'urgent_recommendations' => $urgentCount,
                'open_requests' => $operationalOpen->count(),
                'overdue_requests' => $overdueRequests->count(),
                'due_today_requests' => $dueTodayRequests->count(),
                'receiving_requests' => $receivingRequests->count(),
                'disputed_requests' => $requests->where('status', SupplyRequest::STATUS_DISPUTED)->count(),
                'fill_rate_percent' => $dispatchedQuantity > 0 ? round(($receivedQuantity / $dispatchedQuantity) * 100, 1) : 100.0,
            ],
            'daily' => $daily,
            'branches' => $branchStats,
            'top_ingredients' => $topIngredients,
            'recommendations' => array_slice($recommendations, 0, 10),
            'insights' => $insights,
            'today_requests' => $todayList,
            'operations' => [
                'open_requests' => $operationalOpen->count(),
                'overdue_requests' => $overdueRequests->count(),
                'due_today_requests' => $dueTodayRequests->count(),
                'receiving_requests' => $receivingRequests->count(),
                'disputed_requests' => $requests->where('status', SupplyRequest::STATUS_DISPUTED)->count(),
                'fill_rate_percent' => $dispatchedQuantity > 0 ? round(($receivedQuantity / $dispatchedQuantity) * 100, 1) : 100.0,
                'branch_operations' => $branchOperations,
            ],
        ];
    }
}
