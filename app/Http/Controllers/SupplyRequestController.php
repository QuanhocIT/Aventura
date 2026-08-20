<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\InventoryReservation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\WarehouseTaskAssignment;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * Dashboard cho Kho Tổng (Inertia View)
     */
    public function centralWarehousePage(Request $request): Response
    {
        $props = $this->centralWarehouseProps($request);

        return Inertia::render('inventory/CentralWarehouseOverview', [
            'centralBranch' => $props['centralBranch'],
            'supplyAnalytics' => $props['supplyAnalytics'],
            'centralWarehouseAnalytics' => $props['centralWarehouseAnalytics'],
            'receivingSummary' => $props['receivingSummary'],
            'inventorySummary' => $props['inventorySummary'],
        ]);
    }

    /**
     * Danh sách tồn kho thực tế của riêng Kho Tổng.
     *
     * Màn này không dùng TenantContext đang được chọn trên header. Trưởng
     * kho có thể xem toàn chuỗi, nhưng tồn kho cần luôn cố định ở kho nguồn
     * để không vô tình đọc nhầm tồn của một chi nhánh đang được chọn.
     */
    public function centralWarehouseInventoryPage(Request $request): Response
    {
        $user = $request->user();
        $props = $this->centralWarehouseProps($request);

        return Inertia::render('inventory/CentralWarehouseInventory', [
            'centralBranch' => $props['centralBranch'],
            'centralStockItems' => $props['centralStockItems'],
            'inventorySummary' => $props['inventorySummary'],
            'inventoryActivity' => $props['inventoryActivity'],
            'warehouseLocations' => $props['warehouseLocations'],
            'canManageWarehouse' => $props['canManageWarehouse'],
            'canReconcile' => $props['canReconcile'],
            'canUnlockBatches' => $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Workspace riêng cho quy trình duyệt - soạn - xuất đơn cấp phát.
     */
    public function centralWarehouseRequestsPage(Request $request): Response
    {
        return Inertia::render('inventory/CentralWarehouse', $this->centralWarehouseProps($request));
    }

    /**
     * Workspace riêng cho bảng giá nguyên liệu Kho Tổng.
     */
    public function centralWarehousePricesPage(Request $request): Response
    {
        $user = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $ingredients = $this->centralIngredientQuery($user->restaurant_id, $centralBranch?->id)
            ->with('unit')
            ->orderBy('name')
            ->get();
        $ingredientIds = $ingredients->pluck('id');
        $ingredientNames = $ingredients->pluck('name', 'id');
        $priceHistory = IngredientPriceHistory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('ingredient_id', $ingredientIds)
            ->with(['ingredient:id,name,sku', 'changedBy:id,name', 'approvedBy:id,name'])
            ->latest('created_at')
            ->limit(100)
            ->get();
        $latestHistoryByIngredient = $priceHistory->unique('ingredient_id')->keyBy('ingredient_id');
        $pendingPriceUpdates = ApprovalRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('operation_type', 'warehouse_price_update')
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED])
            ->when(
                ! $user->isOwner() && ! $user->isSuperAdmin(),
                fn ($query) => $query->where('requester_id', $user->id),
            )
            ->with('requester:id,name')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (ApprovalRequest $approval) use ($ingredientNames): array {
                $prices = collect($approval->operation_data['prices'] ?? []);

                return [
                    'id' => $approval->id,
                    'status' => $approval->status,
                    'requester_name' => $approval->requester?->name ?? '—',
                    'reason' => $approval->operation_data['reason'] ?? null,
                    'created_at' => $approval->created_at?->format('H:i d/m/Y'),
                    'items' => $prices->map(fn (array $price): array => [
                        'ingredient_id' => (int) ($price['ingredient_id'] ?? 0),
                        'ingredient_name' => $ingredientNames->get((int) ($price['ingredient_id'] ?? 0), 'Nguyên liệu không còn trong danh mục'),
                        'proposed_price' => (float) ($price['average_cost'] ?? 0),
                    ])->values()->all(),
                ];
            })
            ->values();
        $staleCutoff = now()->subDays(30);
        $staleCount = $ingredientIds->filter(function ($ingredientId) use ($latestHistoryByIngredient, $staleCutoff): bool {
            $history = $latestHistoryByIngredient->get($ingredientId);

            return ! $history || $history->created_at?->lt($staleCutoff);
        })->count();
        $largeChangeCount = $priceHistory
            ->where('status', 'approved')
            ->filter(fn (IngredientPriceHistory $history): bool => abs((float) $history->change_percent) >= 10)
            ->unique('ingredient_id')
            ->count();

        return Inertia::render('inventory/CentralWarehousePrices', [
            'ingredients' => $ingredients,
            // Giá vốn Kho Tổng là dữ liệu tài chính dùng chung toàn chuỗi.
            // Trưởng kho được soạn đề xuất; chỉ Chủ/Super Admin được áp dụng trực tiếp.
            'canManageWarehouse' => $user->isOwner() || $user->isSuperAdmin(),
            'canProposePrices' => $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('warehouse_manager')
                || $user->can('warehouse.manage'),
            'pendingPriceUpdates' => $pendingPriceUpdates,
            'priceHistory' => $priceHistory->map(fn (IngredientPriceHistory $history): array => [
                'id' => $history->id,
                'ingredient_id' => $history->ingredient_id,
                'ingredient_name' => $history->ingredient?->name ?? '—',
                'ingredient_sku' => $history->ingredient?->sku,
                'old_price' => (float) ($history->old_price ?? 0),
                'new_price' => (float) ($history->new_price ?? 0),
                'change_percent' => (float) $history->change_percent,
                'status' => $history->status,
                'reason' => $history->change_reason,
                'changed_by' => $history->changedBy?->name ?? '—',
                'approved_by' => $history->approvedBy?->name,
                'created_at' => $history->created_at?->format('H:i d/m/Y'),
                'approved_at' => $history->approved_at?->format('H:i d/m/Y'),
            ])->values(),
            'priceGovernance' => [
                'last_updated_at' => $priceHistory->first()?->created_at?->format('H:i d/m/Y'),
                'stale_count' => $staleCount,
                'large_change_count' => $largeChangeCount,
                'pending_count' => $pendingPriceUpdates->count(),
            ],
        ]);
    }

    /**
     * Workspace riêng cho tiếp nhận hàng và phiếu GRN.
     */
    public function centralWarehouseReceivingPage(Request $request): Response
    {
        $user = $request->user();
        $props = $this->centralWarehouseProps($request);

        return Inertia::render('inventory/CentralWarehouseReceiving', [
            'centralBranch' => $props['centralBranch'],
            'receivingVouchers' => $props['receivingVouchers'],
            'receivingSummary' => $props['receivingSummary'],
            'inventorySummary' => $props['inventorySummary'],
            'warehouseLocations' => $props['warehouseLocations'],
            'ingredients' => $props['ingredients'],
            'suppliers' => $props['warehouseSuppliers'],
            'purchaseOrders' => $props['warehousePurchaseOrders'],
            'canManageWarehouse' => $props['canManageWarehouse'],
            'canCreateReceiving' => $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.receiving.create') || $user->can('warehouse.manage') || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']),
        ]);
    }

    private function centralIngredientQuery(int $restaurantId, ?int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->when(
                $centralBranchId,
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $centralBranchId)),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    private function centralWarehouseProps(Request $request): array
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
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver'])
            ->orderByDesc('id')
            ->get();

        $ingredients = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->with(['unit'])
            ->get();

        $warehouseStaff = $this->getWarehouseStaff($restaurantId);
        $warehouseTasks = $this->getWarehouseTasks($restaurantId);

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

        // Chỉ lấy catalog dùng được tại Kho Tổng. Nguyên liệu riêng của các
        // chi nhánh khác không được xuất hiện trong màn tồn Kho Tổng.
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
            'pending_review' => (clone $receivingStats)->whereIn('status', ['draft', 'discrepancy', 'pending_review'])->count(),
            'draft' => (clone $receivingStats)->where('status', 'draft')->count(),
            'discrepancy_vouchers' => (clone $receivingStats)->whereIn('status', ['discrepancy', 'pending_review'])->count(),
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
            'canManageWarehouse' => $isOwnerOrSuperAdmin || $user->can('warehouse.manage'),
            'canApproveRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.approve'),
            'canDispatchRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.dispatch'),
            'warehouseStaff' => $warehouseStaff,
            'warehouseTasks' => $warehouseTasks,
            'warehouseTaskSummary' => $this->buildWarehouseTaskSummary($warehouseTasks),
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
        ];
    }

    /**
     * Xuất sổ điều phối cấp phát để Trưởng kho đối soát ngoài hệ thống.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.report'),
            403,
            'Bạn không có quyền xuất báo cáo Kho Tổng.'
        );

        $requests = SupplyRequest::where('restaurant_id', $user->restaurant_id)
            ->when(
                $this->warehouseService->getCentralWarehouse($user->restaurant_id),
                fn ($query, $central) => $query->where('from_branch_id', $central->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['toBranch', 'creator', 'items.ingredient.unit'])
            ->orderByDesc('id')
            ->get();

        $filename = 'bao-cao-kho-tong-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($requests): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Mã đơn', 'Chi nhánh', 'Trạng thái', 'Người lập', 'Ngày tạo', 'Ngày cần giao', 'Ngày xuất', 'Ngày nhận', 'Giá trị', 'Số dòng', 'Số lượng yêu cầu', 'Số lượng đã nhận']);

            foreach ($requests as $request) {
                $requested = (float) $request->items->sum(fn ($item) => (float) $item->requested_quantity);
                $received = (float) $request->items->sum(fn ($item) => (float) ($item->received_quantity ?? 0));
                fputcsv($handle, [
                    $request->request_code,
                    $request->toBranch?->name,
                    $request->status,
                    $request->creator?->name,
                    optional($request->created_at)->format('d/m/Y H:i'),
                    optional($request->requested_delivery_date)->format('d/m/Y H:i'),
                    optional($request->dispatched_at)->format('d/m/Y H:i'),
                    optional($request->received_at)->format('d/m/Y H:i'),
                    $request->total_amount,
                    $request->items->count(),
                    $requested,
                    $received,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Phân tích nhu cầu cấp phát để chủ doanh nghiệp biết hôm nay cần xử lý gì
     * và nên bổ sung nguyên liệu nào cho Kho Tổng trong 7 ngày tới.
     */
    private function buildSupplyRequestAnalytics(
        Collection $requests,
        Collection $branches,
        Collection $ingredients,
        ?RestaurantBranch $centralBranch,
    ): array {
        $now = now();
        $historyStart = $now->copy()->subDays(27)->startOfDay();
        $last7Start = $now->copy()->subDays(6)->startOfDay();
        $previous7Start = $now->copy()->subDays(13)->startOfDay();
        $validStatuses = ['pending', 'approved', 'dispatched', 'completed'];
        $openStatuses = ['pending', 'approved', 'dispatched'];

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

        $terminalStatuses = [
            SupplyRequest::STATUS_COMPLETED,
            SupplyRequest::STATUS_REJECTED,
            SupplyRequest::STATUS_CANCELLED,
        ];
        $operationalOpen = $requests->filter(fn ($request) => ! in_array($request->status, $terminalStatuses, true));
        $overdueRequests = $operationalOpen->filter(fn ($request) => $request->requested_delivery_date && $request->requested_delivery_date->isPast());
        $dueTodayRequests = $operationalOpen->filter(fn ($request) => $request->requested_delivery_date && $request->requested_delivery_date->isToday());
        $receivingRequests = $requests->filter(fn ($request) => in_array($request->status, [
            SupplyRequest::STATUS_DISPATCHED,
            SupplyRequest::STATUS_PARTIAL_RECEIVED,
            SupplyRequest::STATUS_DISPUTED,
        ], true));
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

    /**
     * Trang Lên đơn cấp phát cho Chi nhánh (Inertia View)
     */
    public function branchRequisitionPage(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('id', '!=', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', 'active')
            ->when(! $user->canViewAllBranches(), fn ($q) => $q->whereKey($user->assignedBranchId()))
            ->get();

        $activeBranchId = $user->canViewAllBranches()
            ? ($request->integer('branch_id') ?: ($user->assignedBranchId() ?: $branches->first()?->id))
            : $user->assignedBranchId();

        if ($activeBranchId && ! $branches->contains('id', (int) $activeBranchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh đặt hàng không thuộc phạm vi tài khoản này.',
            ]);
        }

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($activeBranchId, fn ($q) => $q->where('to_branch_id', $activeBranchId))
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver'])
            ->orderByDesc('id')
            ->get();

        $ingredients = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->with(['unit'])
            ->get();

        return Inertia::render('inventory/BranchRequisition', [
            'centralBranch' => $centralBranch,
            'branches' => $branches,
            'activeBranchId' => $activeBranchId,
            'supplyRequests' => $requests,
            'ingredients' => $ingredients,
            'canCreateRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.create'),
            'canReceiveRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.receive'),
        ]);
    }

    /**
     * Lấy danh sách Yêu cầu cấp phát (API JSON)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $query = SupplyRequest::where('restaurant_id', $restaurantId)
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('branch_id')) {
            $query->where('to_branch_id', $request->branch_id);
        }

        if ($user->hasAnyRole(['warehouse_manager', 'warehouse_staff'])) {
            $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
            $query->when($centralBranch, fn ($scope) => $scope->where('from_branch_id', $centralBranch->id), fn ($scope) => $scope->whereRaw('1 = 0'));
        }

        if (! $this->canViewAllSupplyRequests($user)) {
            $branchId = $user->assignedBranchId();
            abort_if($branchId === null, 403, 'Tài khoản chưa được gán chi nhánh nhận hàng.');
            $query->where('to_branch_id', $branchId);
        }

        $requests = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Tạo Đơn xin cấp phát mới
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_branch_id'            => ['required', TenantRule::exists('restaurant_branches')],
            'items'                   => 'required|array|min:1',
            'items.*.ingredient_id'   => ['required', TenantRule::exists('ingredients'), 'distinct'],
            'items.*.quantity'        => 'required|numeric|gt:0',
            'requested_delivery_date' => 'nullable|date',
            'notes'                   => 'nullable|string',
            'overlimit_reason'        => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $toBranchId = (int) $request->to_branch_id;
        if (! $user->canAccessBranch($toBranchId)) {
            abort(403, 'Bạn chỉ có thể lập đơn cấp phát cho chi nhánh thuộc phạm vi tài khoản.');
        }

        try {
            $supplyRequest = $this->warehouseService->createSupplyRequest(
                $user->restaurant_id,
                $toBranchId,
                $user,
                $data['items'],
                $data['requested_delivery_date'] ?? null,
                $data['notes'] ?? null,
                $data['overlimit_reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã gửi yêu cầu nhập hàng đến Kho Tổng thành công.',
                'data'    => $supplyRequest,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function quickRecommendedRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->can('supply_requests.create'),
            403,
            'Bạn không có quyền tạo đề xuất cấp phát nhanh.'
        );
        $restaurantId = $user->restaurant_id;

        $data = $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'notes' => 'nullable|string',
        ]);

        $branchId = (int) $data['branch_id'];
        if (! $user->canAccessBranch($branchId)) {
            abort(403, 'Bạn chỉ có thể lập đơn cấp phát cho chi nhánh thuộc phạm vi tài khoản.');
        }

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $ingredients = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->whereHas('inventories', function ($inv) use ($branchId) {
                $inv->where('branch_id', $branchId)
                    ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level');
            })
            ->get();

        if ($ingredients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tồn kho chi nhánh vẫn đủ định mức, không có nguyên liệu nào cần cấp thêm.',
            ], 422);
        }

        $items = [];
        foreach ($ingredients as $ing) {
            $inv = \App\Models\Inventory::where('branch_id', $branchId)->where('ingredient_id', $ing->id)->first();
            $qtyOnHand = $inv ? (float) $inv->quantity_on_hand : 0.0;
            $min = (float) $ing->min_stock_level;
            $optimal = ($min > 0) ? ($min * 2) : 10.0;
            $qtyNeeded = max(1.0, $optimal - $qtyOnHand);

            $items[] = [
                'ingredient_id' => $ing->id,
                'quantity' => $qtyNeeded,
            ];
        }

        try {
            $this->warehouseService->ensureCentralWarehouse($restaurantId);

            $supplyRequest = $this->warehouseService->createSupplyRequest(
                $restaurantId,
                $branchId,
                $user,
                $items,
                now()->addDay()->toDateString(),
                $data['notes'] ?? 'Tự động lập đơn đề xuất cấp hàng theo tồn kho định mức'
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã tự động tạo phiếu đề xuất cấp hàng gồm '.count($items).' nguyên liệu đang thiếu.',
                'data' => $supplyRequest,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Kho Tổng phê duyệt đơn & giữ chỗ tồn khả dụng
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        $data = $request->validate([
            'items'                     => 'nullable|array',
            'items.*.id'                => 'required_with:items|integer',
            'items.*.approved_quantity' => 'required_with:items|numeric|min:0',
            'notes'                     => 'nullable|string',
        ]);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        if (! $this->canApproveSupplyRequests($user)) {
            $this->approvalService->submitRequest('warehouse_supply_approve', [
                'supply_request_id' => $supplyRequest->id,
                'items'             => $data['items'] ?? [],
                'notes'             => $data['notes'] ?? null,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu duyệt đơn cấp phát đã gửi Chủ nhà hàng.'], 202);
        }

        try {
            $updated = $this->warehouseService->approveSupplyRequest(
                $supplyRequest,
                $user,
                $data['items'] ?? null,
                $data['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã duyệt đơn cấp phát và khóa giữ chỗ tồn kho khả dụng.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Soạn hàng tại Kho Tổng (Layer 1 - Quét mã lô FEFO & kiểm đếm)
     */
    public function prepare(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        $data = $request->validate([
            'items'                              => 'required|array|min:1',
            'items.*.id'                         => 'required|integer',
            'items.*.actual_dispatched_quantity' => 'required|numeric|min:0',
            'items.*.batch_id'                   => 'nullable|integer',
            'items.*.warehouse_location_id'      => 'nullable|integer',
            'items.*.non_fefo_reason'            => 'nullable|string',
            'items.*.notes'                      => 'nullable|string',
        ]);

        try {
            $updated = $this->warehouseService->prepareDispatch($supplyRequest, $user, $data['items']);

            WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('supply_request_id', $supplyRequest->id)
                ->where('task_type', 'picking')
                ->whereIn('status', ['assigned', 'in_progress'])
                ->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Đã hoàn thành bước soạn hàng. Đơn chuyển sang chờ Trưởng kho duyệt xuất.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Trưởng Kho Tổng phê duyệt số lượng xuất (Layer 2)
     */
    public function approveDispatch(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        try {
            $updated = $this->warehouseService->approveDispatch($supplyRequest, $user);

            return response()->json([
                'success' => true,
                'message' => 'Đã phê duyệt lệnh xuất kho. Sẵn sàng bàn giao vận chuyển.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Kho Tổng xuất kho bàn giao thực tế (Layer 3)
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'seal_code' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        if (! $this->canDispatchSupplyRequests($user)) {
            $this->approvalService->submitRequest('warehouse_supply_dispatch', [
                'supply_request_id' => $supplyRequest->id,
                'seal_code'         => $request->seal_code,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu xuất kho đã gửi Chủ nhà hàng.'], 202);
        }

        try {
            $updated = $this->warehouseService->dispatchSupplyRequest($supplyRequest, $user, $request->seal_code);

            WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('supply_request_id', $supplyRequest->id)
                ->where('task_type', 'handover')
                ->whereIn('status', ['assigned', 'in_progress'])
                ->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Đã xuất kho Tổng và tạo Phiếu giao hàng thành công.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Chi nhánh xác nhận nhận hàng (Chống gian lận: bắt buộc kiểm đếm thực tế, ảnh, chữ ký)
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless($user->canAccessBranch((int) $supplyRequest->to_branch_id), 403, 'Bạn chỉ có thể xác nhận hàng cho chi nhánh thuộc phạm vi tài khoản.');

        $data = $request->validate([
            'items'                     => 'nullable|array',
            'items.*.id'                => 'required_with:items|integer',
            'items.*.received_quantity' => 'required_with:items|numeric|min:0',
            'receipt_photo_path'        => 'nullable|string',
            'receiver_signature_path'   => 'nullable|string',
            'notes'                     => 'nullable|string',
            'receipt_photo'             => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'receiver_signature'        => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        $hasShortage = false;
        if (! empty($data['items'])) {
            foreach ($supplyRequest->items as $item) {
                foreach ($data['items'] as $recItem) {
                    if ($recItem['id'] == $item->id && isset($recItem['received_quantity'])) {
                        $dispatched = (float) $item->effective_dispatched_quantity;
                        $received = (float) $recItem['received_quantity'];
                        if ($received < $dispatched) {
                            $hasShortage = true;
                            break 2;
                        }
                    }
                }
            }
        }

        $receiptPhotoPath = $data['receipt_photo_path'] ?? null;
        $receiptPhotoHash = null;
        if ($request->hasFile('receipt_photo')) {
            $file = $request->file('receipt_photo');
            $receiptPhotoHash = hash_file('sha256', $file->getRealPath());
            $receiptPhotoPath = $file->store("restaurants/{$user->restaurant_id}/supply_receipts", 'local');
        }

        $signaturePath = $data['receiver_signature_path'] ?? null;
        $signatureHash = null;
        if ($request->hasFile('receiver_signature')) {
            $file = $request->file('receiver_signature');
            $signatureHash = hash_file('sha256', $file->getRealPath());
            $signaturePath = $file->store("restaurants/{$user->restaurant_id}/supply_receipts", 'local');
        }

        if ($hasShortage && (blank($receiptPhotoPath) && blank($supplyRequest->receipt_photo_path) || blank($signaturePath) && blank($supplyRequest->receiver_signature_path))) {
            return response()->json([
                'success' => false,
                'message' => 'Bắt buộc chụp ảnh thực tế và ký tên xác nhận khi số lượng thực nhận ít hơn số lượng Kho Tổng xuất.',
                'error'   => 'evidence_required',
            ], 422);
        }

        try {
            $updated = $this->warehouseService->receiveSupplyRequest(
                $supplyRequest,
                $user,
                $data['items'] ?? null,
                $receiptPhotoPath,
                $signaturePath,
                $data['notes'] ?? null,
                $receiptPhotoHash,
                $signatureHash
            );

            $msg = $updated->status === SupplyRequest::STATUS_DISPUTED
                ? 'Đã ghi nhận nhận hàng (Phát hiện hàng thiếu: Đã tự động tạo Hồ sơ tranh chấp).'
                : 'Đã nghiệm thu và nhập hàng vào tồn kho Chi nhánh thành công.';

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Hủy đơn xin cấp phát
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $canCancelChainwide = $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasRole('warehouse_manager')
            || $user->can('warehouse.manage');
        abort_unless(
            $canCancelChainwide || $user->can('supply_requests.cancel'),
            403,
            'Bạn không có quyền hủy đơn cấp phát.'
        );
        if (! $canCancelChainwide) {
            abort_unless(
                $user->canAccessBranch((int) $supplyRequest->to_branch_id),
                403,
                'Bạn chỉ có thể hủy đơn cấp phát của chi nhánh được phân công.'
            );
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $updated = $this->warehouseService->cancelSupplyRequest($supplyRequest, $user, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy đơn cấp phát và giải phóng giữ chỗ tồn kho.',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Kho Tổng từ chối đơn xin cấp phát
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (! $this->canApproveSupplyRequests($user)) {
            $this->approvalService->submitRequest('warehouse_supply_reject', [
                'supply_request_id' => $supplyRequest->id,
                'reason' => $request->reason,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu từ chối đơn đã gửi Chủ nhà hàng.'], 202);
        }

        try {
            $updated = $this->warehouseService->rejectSupplyRequest(
                $supplyRequest,
                $user,
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã từ chối đơn cấp phát.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Đổi Chi nhánh làm Kho Tổng
     */
    public function setCentralBranch(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
        ]);

        $user = $request->user();
        $branch = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->findOrFail((int) $request->branch_id);
        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $this->approvalService->submitRequest('warehouse_set_central', [
                'branch_id' => (int) $request->branch_id,
                'branch_name' => $branch->name,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu thiết lập Kho Tổng đã gửi Chủ nhà hàng phê duyệt.']);
        }
        $branch = $this->warehouseService->setCentralWarehouse($user->restaurant_id, $request->branch_id);

        return response()->json([
            'success' => true,
            'message' => "Đã thiết lập chi nhánh '{$branch->name}' làm Kho Tổng của hệ thống.",
            'data' => $branch,
        ]);
    }

    /**
     * Kho Tổng cập nhật đơn giá nguyên liệu dùng chung cho chuỗi (Owner/Super Admin).
     */
    public function updateIngredientPrices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin(),
            403,
            'Chỉ Chủ doanh nghiệp mới được thiết lập trực tiếp giá vốn nguyên liệu toàn chuỗi.'
        );

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.ingredient_id' => ['required', 'distinct', TenantRule::exists('ingredients')],
            'prices.*.average_cost' => 'required|numeric|min:0',
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $centralIngredientQuery = fn () => $this->centralIngredientQuery($user->restaurant_id, $centralBranch?->id);

        foreach ($data['prices'] as $index => $priceRow) {
            if (! $centralIngredientQuery()->whereKey((int) $priceRow['ingredient_id'])->exists()) {
                abort(403, 'Chỉ được cập nhật giá nguyên liệu thuộc Kho Tổng hoặc catalog toàn chuỗi.');
            }
        }
        $reason = trim((string) ($data['reason'] ?? 'Cập nhật trực tiếp trên bảng giá Kho Tổng.'));
        $updatedIds = DB::transaction(function () use ($data, $centralIngredientQuery, $reason, $user): array {
            $updatedIds = [];

            foreach ($data['prices'] as $priceRow) {
                $ingredient = $centralIngredientQuery()
                    ->whereKey((int) $priceRow['ingredient_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $oldPrice = (float) $ingredient->average_cost;
                $newPrice = round((float) $priceRow['average_cost'], 2);

                if (abs($newPrice - $oldPrice) < 0.005) {
                    continue;
                }

                $ingredient->update(['average_cost' => $newPrice]);
                IngredientPriceHistory::create([
                    'restaurant_id' => $user->restaurant_id,
                    'ingredient_id' => $ingredient->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'change_percent' => $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : ($newPrice > 0 ? 100 : 0),
                    'changed_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'change_reason' => $reason,
                    'requires_owner_approval' => false,
                    'status' => 'approved',
                ]);
                $updatedIds[] = $ingredient->id;
            }

            return $updatedIds;
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật đơn giá nguyên liệu đồng bộ toàn chuỗi.',
            'data' => $centralIngredientQuery()
                ->whereIn('id', $updatedIds)
                ->with('unit')
                ->get(),
        ]);
    }

    /**
     * Trưởng kho đề xuất thay đổi đơn giá nguyên liệu (gửi Chủ duyệt).
     */
    public function proposeIngredientPrices(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            403,
            'Bạn không có quyền đề xuất giá nguyên liệu.'
        );

        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.ingredient_id' => ['required', 'distinct', TenantRule::exists('ingredients')],
            'prices.*.average_cost' => 'required|numeric|min:0',
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $centralIngredientQuery = fn () => $this->centralIngredientQuery($user->restaurant_id, $centralBranch?->id);

        foreach ($data['prices'] as $index => $priceRow) {
            if (! $centralIngredientQuery()->whereKey((int) $priceRow['ingredient_id'])->exists()) {
                abort(403, 'Chỉ được đề xuất giá nguyên liệu thuộc Kho Tổng hoặc catalog toàn chuỗi.');
            }
        }

        $this->approvalService->submitRequest('warehouse_price_update', $data, $user);

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu cập nhật đơn giá đã gửi Chủ nhà hàng phê duyệt.',
        ]);
    }

    private function canViewAllSupplyRequests(User $user): bool
    {
        return $user->canViewAllBranches()
            || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']);
    }

    private function canApproveSupplyRequests(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('supply_requests.approve');
    }

    private function canDispatchSupplyRequests(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->can('supply_requests.dispatch')
            || $user->can('warehouse.handover');
    }

    private function authorizeSupplyRequestScope(User $user, SupplyRequest $supplyRequest): void
    {
        if ($this->canViewAllSupplyRequests($user)) {
            return;
        }

        abort_unless(
            $user->canAccessBranch((int) $supplyRequest->to_branch_id),
            403,
            'Bạn chỉ có thể xử lý đơn cấp phát thuộc chi nhánh được phân công.'
        );
    }

    /**
     * Danh sách nhân viên Kho Tổng để Trưởng kho phân công theo ca việc.
     */
    private function getWarehouseStaff(int $restaurantId): array
    {
        return User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'warehouse_staff'))
            ->with(['roles', 'employee'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $staff): array => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'job_title' => $staff->employee?->job_title ?: 'Nhân viên Kho Tổng',
                'employee_code' => $staff->employee?->employee_code,
                'avatar_url' => $staff->avatar_url,
            ])
            ->values()
            ->all();
    }

    private function getWarehouseTasks(int $restaurantId): array
    {
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        return WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            }))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'assignee.employee',
                'supplyRequest.toBranch',
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WarehouseTaskAssignment $task): array => [
                'id' => $task->id,
                'supply_request_id' => $task->supply_request_id,
                'request_code' => $task->supplyRequest?->request_code,
                'request_status' => $task->supplyRequest?->status,
                'branch_name' => $task->supplyRequest?->toBranch?->name,
                'task_type' => $task->task_type,
                'status' => $task->status,
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
                'assignee_name' => $task->assignee?->name,
                'assignee_job_title' => $task->assignee?->employee?->job_title,
                'due_at' => $task->due_at?->toISOString(),
                'notes' => $task->notes,
                'created_at' => $task->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function buildWarehouseTaskSummary(array $tasks): array
    {
        return [
            'total' => count($tasks),
            'assigned' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'assigned')),
            'in_progress' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'in_progress')),
            'completed' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'completed')),
            'unassigned' => count(array_filter($tasks, fn (array $task) => empty($task['assigned_to']) && $task['status'] !== 'completed')),
        ];
    }

    private function authorizeWarehouseTaskManager(User $user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage'),
            403,
            'Chỉ Trưởng kho Tổng mới có quyền điều phối nhân viên Kho Tổng.'
        );
    }

    /**
     * Trưởng kho giao một chặng việc cho nhân viên Kho Tổng.
     */
    public function assignWarehouseTask(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseTaskManager($user);

        $data = $request->validate([
            'supply_request_id' => 'required|integer',
            'assigned_to' => 'required|integer',
            'task_type' => 'required|string|in:picking,handover',
            'priority' => 'required|string|in:normal,high,urgent',
            'due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)
            ->where('from_branch_id', $this->warehouseService->getCentralWarehouse($user->restaurant_id)?->id)
            ->findOrFail((int) $data['supply_request_id']);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');

        $assignee = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereKey((int) $data['assigned_to'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'warehouse_staff'))
            ->where(function ($query) use ($centralBranch) {
                $query->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->firstOrFail();

        $allowedStatuses = $data['task_type'] === 'picking'
            ? [SupplyRequest::STATUS_APPROVED, SupplyRequest::STATUS_PREPARING]
            : [SupplyRequest::STATUS_DISPATCH_PENDING];

        if (! in_array($supplyRequest->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => $data['task_type'] === 'picking'
                    ? 'Đơn phải ở trạng thái Đã duyệt hoặc Đang soạn mới có thể giao việc soạn hàng.'
                    : 'Đơn phải được Trưởng kho duyệt xuất trước khi giao việc bàn giao.',
            ], 422);
        }

        $task = WarehouseTaskAssignment::updateOrCreate(
            [
                'restaurant_id' => $user->restaurant_id,
                'supply_request_id' => $supplyRequest->id,
                'task_type' => $data['task_type'],
            ],
            [
                'assigned_to' => $assignee->id,
                'assigned_by' => $user->id,
                'status' => 'assigned',
                'priority' => $data['priority'],
                'due_at' => $data['due_at'] ?? $supplyRequest->requested_delivery_date,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Đã giao việc cho {$assignee->name}.",
            'data' => $task->load(['assignee.employee', 'supplyRequest.toBranch']),
        ]);
    }

    /**
     * Nhân viên cập nhật tiến độ nhiệm vụ được giao.
     */
    public function updateWarehouseTaskStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $isManager = $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage');
        abort_unless($isManager || (int) $task->assigned_to === (int) $user->id, 403, 'Bạn không được cập nhật nhiệm vụ này.');

        $data = $request->validate([
            'status' => 'required|string|in:assigned,in_progress,completed,cancelled',
            'notes'  => 'nullable|string|max:500',
        ]);

        $currentStatus = $task->status;
        $newStatus = $data['status'];

        $allowedTransitions = [
            'pending'     => ['assigned', 'in_progress', 'cancelled'],
            'assigned'    => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled', 'assigned'],
            'completed'   => $isManager ? ['in_progress', 'assigned'] : [],
            'cancelled'   => $isManager ? ['assigned', 'pending'] : [],
        ];

        if ($newStatus !== $currentStatus && ! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Không thể chuyển trạng thái nhiệm vụ từ '{$currentStatus}' sang '{$newStatus}'.",
            ], 422);
        }

        $task->update([
            'status' => $newStatus,
            'notes'  => $data['notes'] ?? $task->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật tiến độ nhiệm vụ Kho Tổng.',
            'data' => $task->fresh(['assignee.employee', 'supplyRequest.toBranch']),
        ]);
    }

    /**
     * Tải / Xem ảnh chứng từ và chữ ký bàn giao (bảo vệ quyền truy cập).
     */
    public function viewProof(Request $request, int $id, string $type)
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless(
            $user->canAccessBranch((int) $supplyRequest->to_branch_id)
            || $user->canAccessBranch((int) $supplyRequest->from_branch_id)
            || $user->isWarehouseManager()
            || $user->isOwner()
            || $user->isSuperAdmin(),
            403,
            'Bạn không có quyền xem chứng từ của đơn cấp phát này.'
        );

        $path = match ($type) {
            'receipt_photo' => $supplyRequest->receipt_photo_path,
            'signature', 'receiver_signature' => $supplyRequest->receiver_signature_path,
            default => null,
        };

        abort_unless($path, 404, 'Không tìm thấy chứng từ.');

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
        }

        abort(404, 'File chứng từ không tồn tại trên hệ thống lưu trữ.');
    }

    /**
     * Lấy dữ liệu Bảng công việc Kho Tổng (Task Board) cho nhân viên kho
     */
    public function taskBoardData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('supply_requests.view') || $user->can('warehouse.view') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = $user->restaurant_id;
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        $pickingPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_APPROVED)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'creator'])
            ->orderBy('requested_delivery_date')
            ->get();

        $dispatchApprovalPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_PREPARING)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'preparedBy'])
            ->orderBy('prepared_at')
            ->get();

        $handoverPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_DISPATCH_PENDING)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'approver', 'preparedBy'])
            ->orderBy('dispatch_approved_at')
            ->get();

        $warehouseTasks = $this->getWarehouseTasks($restaurantId);

        return response()->json([
            'success' => true,
            'data'    => [
                'picking_pending'           => $pickingPending,
                'dispatch_approval_pending' => $dispatchApprovalPending,
                'handover_pending'          => $handoverPending,
                'assignments'               => $warehouseTasks,
                'staff'                     => $this->getWarehouseStaff($restaurantId),
                'summary'                   => $this->buildWarehouseTaskSummary($warehouseTasks),
            ],
        ]);
    }

    private function assertItemsBelongToSupplyRequest(SupplyRequest $supplyRequest, array $itemIds): void
    {
        $ids = collect($itemIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $validCount = $supplyRequest->items()
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'items' => 'Danh sach nguyen lieu xu ly khong khop voi don cap phat nay.',
            ]);
        }
    }

    public function smartAllocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'supply_request_ids'   => 'required|array|min:1',
            'supply_request_ids.*' => 'integer',
        ]);

        $suggestions = $this->warehouseService->suggestSmartAllocation($user->restaurant_id, $validated['supply_request_ids']);

        return response()->json([
            'success'     => true,
            'suggestions' => $suggestions,
        ]);
    }

    public function createBackorder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $parentRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $validated = $request->validate([
            'shortage_items'                       => 'required|array|min:1',
            'shortage_items.*.ingredient_id'       => 'required|integer',
            'shortage_items.*.shortage_quantity'   => 'required|numeric|gt:0',
        ]);

        $backorder = $this->warehouseService->createBackorder($parentRequest, $validated['shortage_items'], $user);

        return response()->json([
            'success'   => true,
            'message'   => "Đã tự động tạo Đơn giao bù #{$backorder->request_code}.",
            'backorder' => $backorder,
        ]);
    }
}
