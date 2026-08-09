<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\CentralWarehouseService;
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
            ->where('status', 'active')
            ->get();

        $requests = SupplyRequest::where('restaurant_id', $restaurantId)
            ->with(['items.ingredient.unit', 'fromBranch', 'toBranch', 'creator', 'approver', 'dispatcher', 'receiver'])
            ->orderByDesc('id')
            ->get();

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->with(['unit'])
            ->get();

        return Inertia::render('inventory/CentralWarehouse', [
            'centralBranch' => $centralBranch,
            'branches' => $branches,
            'supplyRequests' => $requests,
            'ingredients' => $ingredients,
            'canManageWarehouse' => $isOwnerOrSuperAdmin || $user->can('warehouse.manage'),
            'canApproveRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.approve'),
            'canDispatchRequests' => $isOwnerOrSuperAdmin || $user->can('supply_requests.dispatch'),
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
            'items.*.ingredient_id' => ['required', TenantRule::exists('ingredients')],
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
