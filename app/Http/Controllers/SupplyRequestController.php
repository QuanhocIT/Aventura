<?php

namespace App\Http\Controllers;

use App\Models\DeliveryManifest;
use App\Models\DeliveryManifestItem;
use App\Models\Inventory;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestReceivingReport;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Notifications\WarehouseTaskAssignedNotification;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseAiService;
use App\Services\CentralWarehouseService;
use App\Services\DeliveryManifestService;
use App\Services\NegativeInventoryService;
use App\Services\SupplyRequestAnalyticsService;
use App\Services\WarehouseStaffAccessService;
use App\Services\WarehouseTaskService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
        protected SupplyRequestAnalyticsService $analyticsService,
        protected WarehouseTaskService $taskService,
        protected ApprovalService $approvalService,
        protected WarehouseStaffAccessService $staffAccess,
    ) {}

    /**
     * Dashboard cho Kho Tổng (Inertia View)
     */
    public function centralWarehousePage(Request $request): Response
    {
        $props = $this->analyticsService->getCentralWarehouseProps($request);
        $props['centralWarehouseAi'] = app(CentralWarehouseAiService::class)->analyze($props);

        return Inertia::render('inventory/CentralWarehouseOverview', [
            'centralBranch' => $props['centralBranch'],
            'supplyAnalytics' => $props['supplyAnalytics'],
            'centralWarehouseAnalytics' => $props['centralWarehouseAnalytics'],
            'receivingSummary' => $props['receivingSummary'],
            'inventorySummary' => $props['inventorySummary'],
            'centralWarehouseAi' => $props['centralWarehouseAi'],
            'supplyChainAlerts' => $props['supplyChainAlerts'],
            'supplyChainReconciliation' => $props['supplyChainReconciliation'],
            'negativeStockCases' => app(NegativeInventoryService::class)->activeFor(
                (int) $request->user()->restaurant_id,
                $props['centralBranch']?->id,
            ),
        ]);
    }

    /**
     * Danh sách tồn kho thực tế của riêng Kho Tổng.
     */
    public function centralWarehouseInventoryPage(Request $request): Response
    {
        $user = $request->user();
        $props = $this->analyticsService->getCentralWarehouseProps($request);
        $props['centralWarehouseAi'] = app(CentralWarehouseAiService::class)->analyze($props);

        return Inertia::render('inventory/CentralWarehouseInventory', [
            'centralBranch' => $props['centralBranch'],
            'centralStockItems' => $props['centralStockItems'],
            'inventorySummary' => $props['inventorySummary'],
            'inventoryActivity' => $props['inventoryActivity'],
            'warehouseLocations' => $props['warehouseLocations'],
            'canManageWarehouse' => $props['canManageWarehouse'],
            'canReconcile' => $props['canReconcile'],
            'canUnlockBatches' => $user->isOwner() || $user->isSuperAdmin(),
            'centralWarehouseAi' => $props['centralWarehouseAi'],
            'negativeStockCases' => app(NegativeInventoryService::class)->activeFor(
                (int) $user->restaurant_id,
                $props['centralBranch']?->id,
            ),
        ]);
    }

    /**
     * Workspace riêng cho quy trình duyệt - soạn - xuất đơn cấp phát.
     */
    public function centralWarehouseRequestsPage(Request $request): Response
    {
        $props = $this->analyticsService->getCentralWarehouseProps($request);
        $props['centralWarehouseAi'] = app(CentralWarehouseAiService::class)->analyze($props);

        return Inertia::render('inventory/CentralWarehouse', $props);
    }

    /**
     * Workspace riêng cho tiếp nhận hàng và phiếu GRN.
     */
    public function centralWarehouseReceivingPage(Request $request): Response
    {
        $user = $request->user();
        $props = $this->analyticsService->getCentralWarehouseProps($request);
        $props['centralWarehouseAi'] = app(CentralWarehouseAiService::class)->analyze($props);

        return Inertia::render('inventory/CentralWarehouseReceiving', [
            'centralBranch' => $props['centralBranch'],
            'receivingVouchers' => $props['receivingVouchers'],
            'receivingSummary' => $props['receivingSummary'],
            'inventorySummary' => $props['inventorySummary'],
            'warehouseLocations' => $props['warehouseLocations'],
            'ingredients' => $props['ingredients'],
            'purchaseOrders' => $props['warehousePurchaseOrders'],
            'canManageWarehouse' => $props['canManageWarehouse'],
            'canCreateReceiving' => $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.receiving.create') || $user->can('warehouse.manage') || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']),
            'currentUserId' => $user->id,
            'canApproveOwnReceiving' => $user->isOwner() || $user->isSuperAdmin(),
            'centralWarehouseAi' => $props['centralWarehouseAi'],
        ]);
    }

    /**
     * Gợi ý AI dùng chung cho các màn hình Kho Tổng không render qua Inertia props.
     */
    public function aiRecommendations(Request $request): JsonResponse
    {
        $props = $this->analyticsService->getCentralWarehouseProps($request);

        return response()->json([
            'ai' => app(CentralWarehouseAiService::class)->analyze($props),
        ]);
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
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver', 'transporter', 'deliveryTask.assignee', 'receivingReport.items.ingredient.unit', 'receivingReport.transporter', 'receivingReport.submittedBy', 'receivingReport.confirmedBy', 'receivingReport.driverConfirmedBy', 'receivingReport.reviewedBy'])
            ->orderByDesc('id')
            ->get();

        $ingredients = $this->analyticsService->centralIngredientQuery($restaurantId, $centralBranch?->id)
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
     * Lấy danh sách Yêu cầu cấp phát (API JSON)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $query = SupplyRequest::where('restaurant_id', $restaurantId)
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver', 'transporter', 'deliveryTask.assignee', 'receivingReport.items.ingredient.unit', 'receivingReport.transporter', 'receivingReport.submittedBy', 'receivingReport.confirmedBy', 'receivingReport.driverConfirmedBy', 'receivingReport.reviewedBy']);

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
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => ['required', TenantRule::exists('ingredients'), 'distinct'],
            'items.*.quantity' => 'required|numeric|gt:0',
            'requested_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'overlimit_reason' => 'nullable|string|max:500',
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
     * Đề xuất cấp phát tự động nhanh dựa trên tồn kho định mức.
     */
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
        $ingredients = $this->analyticsService->centralIngredientQuery($restaurantId, $centralBranch?->id)
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
            $inv = Inventory::where('branch_id', $branchId)->where('ingredient_id', $ing->id)->first();
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
            'items' => 'required|array|min:1',
            'items.*.id' => 'required_with:items|integer',
            'items.*.approved_quantity' => 'required_with:items|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        if (! $this->canApproveSupplyRequests($user)) {
            $this->approvalService->submitRequest('warehouse_supply_approve', [
                'supply_request_id' => $supplyRequest->id,
                'branch_id' => $supplyRequest->to_branch_id,
                'items' => $data['items'] ?? [],
                'notes' => $data['notes'] ?? null,
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
     * Soạn hàng tại Kho Tổng (Layer 1 - Quét mã lô FEFO & kiểm đếm)
     */
    public function prepare(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->assertWarehouseStaffCanOperate($user);
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);
        $this->assertAssignedWarehouseTask($user, $supplyRequest, ['picking']);

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.actual_dispatched_quantity' => 'required|numeric|min:0',
            'items.*.batch_id' => 'nullable|integer',
            'items.*.warehouse_location_id' => 'nullable|integer',
            'items.*.non_fefo_reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $updated = $this->warehouseService->prepareDispatch($supplyRequest, $user, $data['items']);

            WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('supply_request_id', $supplyRequest->id)
                ->where('task_type', 'picking')
                ->whereIn('status', ['assigned', 'in_progress'])
                ->update(['status' => 'completed', 'completed_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Đã hoàn thành bước soạn hàng. Đơn chuyển sang chờ Trưởng kho duyệt xuất.',
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
     * Trưởng Kho Tổng phê duyệt số lượng xuất (Layer 2)
     */
    public function approveDispatch(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->assertWarehouseStaffCanOperate($user);
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        try {
            $updated = $this->warehouseService->approveDispatch($supplyRequest, $user);

            $assignee = $this->preferredCentralWarehouseStaff($user);
            $handoverTask = WarehouseTaskAssignment::firstOrCreate(
                [
                    'restaurant_id' => $user->restaurant_id,
                    'supply_request_id' => $supplyRequest->id,
                    'task_type' => 'handover',
                ],
                [
                    'assigned_to' => $assignee?->id,
                    'assigned_by' => $user->id,
                    'status' => $assignee ? 'assigned' : 'pending',
                    'priority' => 'high',
                    'due_at' => now()->addHours(2),
                    'notes' => 'Bàn giao đơn '.$supplyRequest->request_code.' cho vận chuyển sau khi được duyệt xuất.',
                ],
            );
            if ($handoverTask->assigned_to === null && $assignee) {
                $handoverTask->update(['assigned_to' => $assignee->id, 'status' => 'assigned']);
            }
            if ($handoverTask->wasRecentlyCreated || $handoverTask->wasChanged('assigned_to')) {
                $assignee?->notify(new WarehouseTaskAssignedNotification($handoverTask));
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã phê duyệt lệnh xuất kho. Sẵn sàng bàn giao vận chuyển.',
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
     * Kho Tổng xuất kho bàn giao thực tế (Layer 3)
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $this->assertWarehouseStaffCanOperate($request->user());
        $data = $request->validate([
            'seal_code' => 'nullable|string|max:100',
            'transporter_id' => 'required|integer',
            'manifest_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);
        $this->assertAssignedWarehouseTask($user, $supplyRequest, ['handover']);

        if (! $this->canDispatchSupplyRequests($user)) {
            $this->approvalService->submitRequest('warehouse_supply_dispatch', [
                'supply_request_id' => $supplyRequest->id,
                'branch_id' => $supplyRequest->to_branch_id,
                'seal_code' => $data['seal_code'] ?? null,
                'transporter_id' => $data['transporter_id'] ?? null,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu xuất kho đã gửi Chủ nhà hàng.'], 202);
        }

        try {
            $dispatchedUser = $user;
            $transporter = ! empty($data['transporter_id'])
                ? User::where('restaurant_id', $user->restaurant_id)->findOrFail((int) $data['transporter_id'])
                : null;

            if (! empty($data['manifest_id'])) {
                $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)->find($data['manifest_id']);
                if ($manifest) {
                    DeliveryManifestItem::firstOrCreate([
                        'delivery_manifest_id' => $manifest->id,
                        'supply_request_id' => $supplyRequest->id,
                    ]);
                }
            }

            $updated = $this->warehouseService->dispatchSupplyRequest($supplyRequest, $dispatchedUser, $data['seal_code'] ?? null);

            if ($transporter) {
                $updated = $this->warehouseService->assignTransporter($updated, $transporter, $user);
            }

            WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('supply_request_id', $supplyRequest->id)
                ->where('task_type', 'handover')
                ->whereIn('status', ['assigned', 'in_progress'])
                ->update(['status' => 'completed', 'completed_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Đã xuất kho Tổng và bàn giao vận chuyển thành công.',
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
     * Gán bổ sung nhân viên giao cho đơn đã xuất kho (dùng để sửa các đơn cũ
     * chưa lưu người giao hoặc đổi người giao trước khi chi nhánh xác nhận).
     */
    public function assignTransporter(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['transporter_id' => 'required|integer']);
        $user = $request->user();
        $this->assertWarehouseStaffCanOperate($user);

        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        try {
            $transporter = User::where('restaurant_id', $user->restaurant_id)
                ->whereKey((int) $data['transporter_id'])
                ->firstOrFail();
            $updated = $this->warehouseService->assignTransporter($supplyRequest, $transporter, $user);

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu nhân viên giao và tạo công việc giao hàng.',
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
     * Chi nhánh xác nhận nhận hàng (Chống gian lận: bắt buộc kiểm đếm thực tế, ảnh, chữ ký)
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless($user->canAccessBranch((int) $supplyRequest->to_branch_id), 403, 'Bạn chỉ có thể xác nhận hàng cho chi nhánh thuộc phạm vi tài khoản.');

        if ($supplyRequest->transporter_id) {
            $supplyRequest->loadMissing('deliveryTask');
            abort_unless(
                $supplyRequest->deliveryTask
                    && (int) $supplyRequest->deliveryTask->assigned_to === (int) $supplyRequest->transporter_id
                    && $supplyRequest->deliveryTask->status === 'completed'
                    && $supplyRequest->delivery_confirmed_at,
                422,
                'Chưa thể nhận hàng: nhân viên giao hàng phải bấm "Ấn giao hàng thành công" trước.'
            );
        }

        $supplyRequest->loadMissing('items.ingredient.unit', 'receivingReport.items.ingredient.unit');
        $pendingReceivingReport = $supplyRequest->receivingReport;
        if ($supplyRequest->status === SupplyRequest::STATUS_RECEIVING_REVIEW
            && (! $pendingReceivingReport || ! $pendingReceivingReport->isPendingBranchConfirmation())) {
            abort(422, 'Đơn đã có biên bản nhận hàng được xử lý hoặc không còn ở bước nhập liệu.');
        }

        $data = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.received_quantity' => 'required_with:items|numeric|min:0',
            'items.*.received_good_quantity' => 'nullable|numeric|min:0',
            'items.*.received_damaged_quantity' => 'nullable|numeric|min:0',
            'items.*.received_expired_quantity' => 'nullable|numeric|min:0',
            'items.*.received_wrong_item_quantity' => 'nullable|numeric|min:0',
            'items.*.received_missing_quantity' => 'nullable|numeric|min:0',
            'items.*.received_condition' => 'nullable|string|in:good,damaged,shortage,mixed,expired,wrong_item',
            'items.*.received_note' => 'nullable|string|max:1000',
            'received_temperature_min_c' => 'nullable|numeric',
            'received_temperature_max_c' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'receipt_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'receiver_signature' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        foreach ($data['items'] ?? [] as &$receivedItem) {
            $hasBreakdown = array_key_exists('received_good_quantity', $receivedItem)
                || array_key_exists('received_damaged_quantity', $receivedItem)
                || array_key_exists('received_expired_quantity', $receivedItem)
                || array_key_exists('received_wrong_item_quantity', $receivedItem);
            if (! $hasBreakdown) {
                $receivedItem['received_good_quantity'] = (float) ($receivedItem['received_quantity'] ?? 0);
            }
            $receivedItem['received_damaged_quantity'] = (float) ($receivedItem['received_damaged_quantity'] ?? 0);
            $receivedItem['received_expired_quantity'] = (float) ($receivedItem['received_expired_quantity'] ?? 0);
            $receivedItem['received_missing_quantity'] = (float) ($receivedItem['received_missing_quantity'] ?? 0);
            $receivedItem['received_wrong_item_quantity'] = (float) ($receivedItem['received_wrong_item_quantity'] ?? 0);
            $breakdownTotal = round(
                (float) $receivedItem['received_good_quantity']
                + (float) $receivedItem['received_damaged_quantity']
                + (float) $receivedItem['received_expired_quantity']
                + (float) $receivedItem['received_wrong_item_quantity'],
                3
            );
            $origItem = $supplyRequest->items->firstWhere('id', $receivedItem['id']);
            $maxAllowed = (float) ($origItem?->effective_dispatched_quantity ?? $origItem?->approved_quantity ?? $origItem?->requested_quantity ?? 0);
            if (! array_key_exists('received_missing_quantity', $receivedItem)) {
                $receivedItem['received_missing_quantity'] = max(0, round($maxAllowed - $breakdownTotal, 3));
            }
            $totalInspection = round(
                (float) $receivedItem['received_good_quantity']
                + (float) $receivedItem['received_damaged_quantity']
                + (float) $receivedItem['received_expired_quantity']
                + (float) ($receivedItem['received_missing_quantity'] ?? 0)
                + (float) $receivedItem['received_wrong_item_quantity'],
                3
            );
            if ($maxAllowed > 0 && abs($totalInspection - $maxAllowed) > 0.0005) {
                $ingredientName = $origItem?->ingredient?->name ?? "mặt hàng #{$receivedItem['id']}";
                throw ValidationException::withMessages([
                    'items' => "Tổng số lượng kiểm đếm (Đạt + Hỏng + Hết hạn + Thiếu = {$totalInspection}) của {$ingredientName} phải bằng chính xác số lượng Kho duyệt ({$maxAllowed}).",
                ]);
            }

            if (array_key_exists('received_quantity', $receivedItem) && abs($breakdownTotal - (float) $receivedItem['received_quantity']) > 0.0005) {
                throw ValidationException::withMessages([
                    'items' => 'Tổng số lượng tốt/hỏng/hết hạn/sai hàng phải bằng số lượng thực nhận.',
                ]);
            }
            $receivedItem['received_quantity'] = $breakdownTotal;
            $receivedItem['received_temperature_min_c'] = $receivedItem['received_temperature_min_c'] ?? $data['received_temperature_min_c'] ?? null;
            $receivedItem['received_temperature_max_c'] = $receivedItem['received_temperature_max_c'] ?? $data['received_temperature_max_c'] ?? null;
            if (empty($receivedItem['received_condition'])) {
                $receivedItem['received_condition'] = $receivedItem['received_damaged_quantity'] > 0
                    || $receivedItem['received_expired_quantity'] > 0
                    || $receivedItem['received_wrong_item_quantity'] > 0
                    ? 'damaged'
                    : ($receivedItem['received_quantity'] < (float) ($supplyRequest->items->firstWhere('id', $receivedItem['id'])?->effective_dispatched_quantity ?? 0) ? 'shortage' : 'good');
            }
        }
        unset($receivedItem);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        $hasShortage = false;
        $hasDamage = false;
        if (! empty($data['items'])) {
            foreach ($supplyRequest->items as $item) {
                foreach ($data['items'] as $recItem) {
                    if ($recItem['id'] == $item->id && isset($recItem['received_quantity'])) {
                        $dispatched = (float) $item->effective_dispatched_quantity;
                        $received = (float) $recItem['received_quantity'];
                        $hasDamage = $hasDamage
                            || (float) ($recItem['received_damaged_quantity'] ?? 0) > 0
                            || (float) ($recItem['received_expired_quantity'] ?? 0) > 0
                            || (float) ($recItem['received_wrong_item_quantity'] ?? 0) > 0;
                        if ($received < $dispatched || (float) ($recItem['received_missing_quantity'] ?? 0) > 0) {
                            $hasShortage = true;
                            break 2;
                        }
                    }
                }
            }
        }

        $receiptPhotoPath = null;
        $receiptPhotoHash = null;
        if ($request->hasFile('receipt_photo')) {
            $file = $request->file('receipt_photo');
            $receiptPhotoHash = hash_file('sha256', $file->getRealPath());
            $receiptPhotoPath = $file->store("restaurants/{$user->restaurant_id}/supply_receipts", 'local');
        }

        $signaturePath = null;
        $signatureHash = null;
        if ($request->hasFile('receiver_signature')) {
            $file = $request->file('receiver_signature');
            $signatureHash = hash_file('sha256', $file->getRealPath());
            $signaturePath = $file->store("restaurants/{$user->restaurant_id}/supply_receipts", 'local');
        }

        $existingReceiptPhotoPath = $receiptPhotoPath ?: $pendingReceivingReport?->receipt_photo_path ?: $supplyRequest->receipt_photo_path;
        $existingSignaturePath = $signaturePath ?: $pendingReceivingReport?->receiver_signature_path ?: $supplyRequest->receiver_signature_path;
        if (($hasShortage || $hasDamage) && (blank($existingReceiptPhotoPath) || blank($existingSignaturePath))) {
            return response()->json([
                'success' => false,
                'message' => 'Bắt buộc chụp ảnh thực tế và ký tên xác nhận khi số lượng thực nhận ít hơn số lượng Kho Tổng xuất.',
                'error' => 'evidence_required',
            ], 422);
        }

        if ($hasShortage || $hasDamage) {
            try {
                $report = $this->warehouseService->saveReceivingReport(
                    $supplyRequest,
                    $user,
                    $data['items'] ?? [],
                    $receiptPhotoPath,
                    $signaturePath,
                    $data['notes'] ?? null,
                    $receiptPhotoHash,
                    $signatureHash,
                    isset($data['received_temperature_min_c']) ? (float) $data['received_temperature_min_c'] : null,
                    isset($data['received_temperature_max_c']) ? (float) $data['received_temperature_max_c'] : null,
                );

                return response()->json([
                    'success' => true,
                    'requires_receiving_report' => true,
                    'message' => 'Đã lưu kết quả đối soát. Vui lòng lập biên bản và xác nhận lần cuối trước khi nhập kho.',
                    'data' => $report,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        if ($pendingReceivingReport?->isPendingBranchConfirmation()) {
            try {
                $this->warehouseService->voidPendingReceivingReport($pendingReceivingReport, $user);
                $supplyRequest->refresh();
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
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
            app(DeliveryManifestService::class)->syncFromSupplyRequest($updated);

            $msg = $updated->status === SupplyRequest::STATUS_DISPUTED
                ? 'Đã xác nhận biên bản; hàng đạt đã nhập tồn, hàng lỗi đã được cách ly và gửi các bên xử lý.'
                : 'Đã tự động nhập kho toàn bộ nguyên liệu đạt.';

            return response()->json([
                'success' => true,
                'message' => $msg,
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
     * Xác nhận biên bản nhận hàng lần cuối tại chi nhánh.
     */
    public function confirmReceivingReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $report = SupplyRequestReceivingReport::where('restaurant_id', $user->restaurant_id)
            ->where('supply_request_id', $id)
            ->with(['items.ingredient.unit', 'supplyRequest.toBranch', 'supplyRequest.transporter'])
            ->firstOrFail();

        try {
            $result = $this->warehouseService->confirmReceivingReport($report, $user);
            app(DeliveryManifestService::class)->syncFromSupplyRequest($result['request']);

            return response()->json([
                'success' => true,
                'message' => $result['idempotent']
                    ? 'Biên bản đã được xác nhận trước đó; hệ thống không hạch toán trùng.'
                    : 'Đã xác nhận biên bản. Nguyên liệu đạt đã nhập kho, nguyên liệu lỗi đã cách ly và biên bản đã gửi các bên xử lý.',
                'data' => $result['report'],
                'supply_request' => $result['request'],
                'idempotent_replay' => $result['idempotent'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Nhân viên giao xác nhận đã đọc và đồng ý với biên bản đối soát.
     */
    public function confirmReceivingReportByDriver(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $user = $request->user();
        $report = SupplyRequestReceivingReport::where('restaurant_id', $user->restaurant_id)
            ->whereKey($id)
            ->with(['supplyRequest.transporter', 'supplyRequest.toBranch'])
            ->firstOrFail();

        try {
            $updated = $this->warehouseService->confirmReceivingReportByDriver($report, $user, $data['notes'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Đã xác nhận biên bản nhận hàng với tư cách nhân viên giao.',
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
     * Chủ doanh nghiệp/Trưởng Kho Tổng ghi nhận kết luận xử lý biên bản.
     */
    public function reviewReceivingReport(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['notes' => 'required|string|max:3000']);
        $user = $request->user();
        $report = SupplyRequestReceivingReport::where('restaurant_id', $user->restaurant_id)
            ->whereKey($id)
            ->with(['supplyRequest.transporter', 'supplyRequest.toBranch'])
            ->firstOrFail();

        try {
            $updated = $this->warehouseService->reviewReceivingReport($report, $user, $data['notes']);

            return response()->json([
                'success' => true,
                'message' => 'Đã ghi nhận kết luận xử lý biên bản; phần hàng lỗi tiếp tục được theo dõi tại khu cách ly.',
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
                'branch_id' => $supplyRequest->to_branch_id,
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
     * Phân bổ thông minh đơn cấp phát
     */
    public function smartAllocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'supply_request_ids' => 'required|array|min:1',
            'supply_request_ids.*' => 'integer',
        ]);

        $suggestions = $this->warehouseService->suggestSmartAllocation($user->restaurant_id, $validated['supply_request_ids']);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Tạo đơn giao bù cho số lượng thiếu hụt
     */
    public function createBackorder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $parentRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $validated = $request->validate([
            'shortage_items' => 'required|array|min:1',
            'shortage_items.*.ingredient_id' => 'required|integer',
            'shortage_items.*.shortage_quantity' => 'required|numeric|gt:0',
        ]);

        $backorder = $this->warehouseService->createBackorder($parentRequest, $validated['shortage_items'], $user);

        return response()->json([
            'success' => true,
            'message' => "Đã tự động tạo Đơn giao bù #{$backorder->request_code}.",
            'backorder' => $backorder,
        ]);
    }

    /* -------------------------------------------------------------------------- */
    /* Proxy Forwarders (Giữ tương thích ngược nếu có Controller khác gọi chéo) */
    /* -------------------------------------------------------------------------- */

    public function centralWarehousePricesPage(Request $request): Response
    {
        return app(CentralWarehousePriceController::class)->centralWarehousePricesPage($request);
    }

    public function updateIngredientPrices(Request $request): JsonResponse
    {
        return app(CentralWarehousePriceController::class)->updateIngredientPrices($request);
    }

    public function proposeIngredientPrices(Request $request): JsonResponse
    {
        return app(CentralWarehousePriceController::class)->proposeIngredientPrices($request);
    }

    public function assignWarehouseTask(Request $request): JsonResponse
    {
        return app(WarehouseTaskController::class)->assignWarehouseTask($request);
    }

    public function updateWarehouseTaskStatus(Request $request, int $id): JsonResponse
    {
        return app(WarehouseTaskController::class)->updateWarehouseTaskStatus($request, $id);
    }

    public function viewProof(Request $request, int $id, string $type)
    {
        return app(WarehouseTaskController::class)->viewProof($request, $id, $type);
    }

    public function taskBoardData(Request $request): JsonResponse
    {
        return app(WarehouseTaskController::class)->taskBoardData($request);
    }

    /* -------------------------------------------------------------------------- */
    /* Helper Functions */
    /* -------------------------------------------------------------------------- */

    private function canViewAllSupplyRequests(User $user): bool
    {
        return $user->canViewAllBranches()
            || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']);
    }

    private function assertWarehouseStaffCanOperate(User $user): void
    {
        if ($user->hasRole('warehouse_staff')) {
            $this->staffAccess->assertCanOperate($user);
        }
    }

    private function assertAssignedWarehouseTask(User $user, SupplyRequest $supplyRequest, array $taskTypes): void
    {
        if (! $user->hasRole('warehouse_staff')) {
            return;
        }

        abort_unless(
            WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('supply_request_id', $supplyRequest->id)
                ->where('assigned_to', $user->id)
                ->whereIn('task_type', $taskTypes)
                ->whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->exists(),
            403,
            'Bạn chỉ được xử lý đơn cấp phát khi đã được giao đúng nhiệm vụ Kho Tổng.'
        );
    }

    private function preferredCentralWarehouseStaff(User $actor): ?User
    {
        $centralBranch = $this->warehouseService->getCentralWarehouse($actor->restaurant_id);

        if ($actor->hasRole('warehouse_staff')
            && $actor->status === 'active'
            && $actor->warehouse_staff_status === 'active'
            && $centralBranch
            && (int) ($actor->warehouse_branch_id ?: $actor->branch_id) === (int) $centralBranch->id) {
            return $actor;
        }

        return User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('warehouse_staff_status')
                    ->orWhere('warehouse_staff_status', 'active');
            })
            ->whereHas('roles', fn ($query) => $query->where('name', 'warehouse_staff'))
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('id')
            ->first();
    }

    private function canApproveSupplyRequests(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasRole('warehouse_manager')
            || $user->can('supply_requests.approve');
    }

    private function canDispatchSupplyRequests(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->hasRole('warehouse_manager')
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
                'items' => 'Danh sách nguyên liệu xử lý không khớp với đơn cấp phát này.',
            ]);
        }
    }
}
