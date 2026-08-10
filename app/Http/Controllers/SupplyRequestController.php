<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('is_central_warehouse', false)
            ->where('status', 'active')
            ->get();

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver'])
            ->orderByDesc('id')
            ->get();

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->with(['unit'])
            ->get();

        $supplyAnalytics = $this->buildSupplyRequestAnalytics(
            $requests,
            $branches,
            $ingredients,
            $centralBranch,
        );

        return Inertia::render('inventory/CentralWarehouse', [
            'centralBranch' => $centralBranch,
            'branches' => $branches,
            'supplyRequests' => $requests,
            'ingredients' => $ingredients,
            'canManageWarehouse' => $isOwnerOrSuperAdmin || $user->can('warehouse.manage'),
            'canApproveRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.approve'),
            'canDispatchRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.dispatch'),
            'supplyAnalytics' => $supplyAnalytics,
        ]);
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
            ],
            'daily' => $daily,
            'branches' => $branchStats,
            'top_ingredients' => $topIngredients,
            'recommendations' => array_slice($recommendations, 0, 10),
            'insights' => $insights,
            'today_requests' => $todayList,
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
            ->where('is_central_warehouse', false)
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

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
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
                $data['notes'] ?? null
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
     * Kho Tổng phê duyệt đơn
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        $data = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.approved_quantity' => 'required_with:items|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $this->approvalService->submitRequest('warehouse_supply_approve', [
                'supply_request_id' => $supplyRequest->id,
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
                'message' => 'Đã duyệt đơn cấp phát thành công.',
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
     * Kho Tổng xuất kho giao hàng
     */
    public function dispatch(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'seal_code' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $this->authorizeSupplyRequestScope($user, $supplyRequest);

        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $this->approvalService->submitRequest('warehouse_supply_dispatch', [
                'supply_request_id' => $supplyRequest->id,
                'seal_code' => $request->seal_code,
            ], $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu xuất kho đã gửi Chủ nhà hàng.'], 202);
        }

        try {
            $updated = $this->warehouseService->dispatchSupplyRequest($supplyRequest, $user, $request->seal_code);

            return response()->json([
                'success' => true,
                'message' => 'Đã xuất kho Tổng và tạo Phiếu giao hàng thành công.',
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
     * Chi nhánh xác nhận đã nhận hàng
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless($user->canAccessBranch((int) $supplyRequest->to_branch_id), 403, 'Bạn chỉ có thể xác nhận hàng cho chi nhánh thuộc phạm vi tài khoản.');

        $data = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|integer',
            'items.*.received_quantity' => 'required_with:items|numeric|min:0',
        ]);
        $this->assertItemsBelongToSupplyRequest(
            $supplyRequest,
            collect($data['items'] ?? [])->pluck('id')->all()
        );

        try {
            $updated = $this->warehouseService->receiveSupplyRequest(
                $supplyRequest,
                $user,
                $data['items'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã nghiệm thu và nhập hàng vào tồn kho Chi nhánh thành công.',
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

        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
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
     * Kho Tá»•ng cáº­p nháº­t Ä‘Æ¡n giÃ¡ nguyÃªn liá»‡u dÃ¹ng chung cho chuá»—i.
     */
    public function updateIngredientPrices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prices' => 'required|array|min:1',
            'prices.*.ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'prices.*.average_cost' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $this->approvalService->submitRequest('warehouse_price_update', $data, $user);

            return response()->json(['success' => true, 'message' => 'Yêu cầu cập nhật đơn giá đã gửi Chủ nhà hàng phê duyệt.']);
        }
        $updatedIds = [];

        foreach ($data['prices'] as $priceRow) {
            $ingredient = Ingredient::where('restaurant_id', $user->restaurant_id)
                ->whereKey((int) $priceRow['ingredient_id'])
                ->firstOrFail();

            $ingredient->update([
                'average_cost' => round((float) $priceRow['average_cost'], 2),
            ]);

            $updatedIds[] = $ingredient->id;
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật đơn giá nguyên liệu đồng bộ toàn chuỗi.',
            'data' => Ingredient::where('restaurant_id', $user->restaurant_id)
                ->whereIn('id', $updatedIds)
                ->with('unit')
                ->get(),
        ]);
    }

    private function canViewAllSupplyRequests(User $user): bool
    {
        return $user->canViewAllBranches()
            || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff']);
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
                'items' => 'Danh sach nguyen lieu xu ly khong khop voi don cap phat nay.',
            ]);
        }
    }
}
