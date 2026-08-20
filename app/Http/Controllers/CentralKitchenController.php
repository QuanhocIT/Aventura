<?php

namespace App\Http\Controllers;

use App\Models\CentralBom;
use App\Models\WorkOrder;
use App\Services\CentralKitchenService;
use App\Services\CentralWarehouseService;
use App\Models\RestaurantBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Ingredient;

class CentralKitchenController extends Controller
{
    public function __construct(
        protected CentralKitchenService $kitchenService,
        protected CentralWarehouseService $warehouseService
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->centralBranch($user->restaurant_id);
        $boms = $this->centralBomsQuery($user->restaurant_id, $centralBranch?->id)
            ->with(['outputIngredient.unit', 'items.inputIngredient.unit'])
            ->orderBy('id', 'desc')
            ->get();

        $workOrders = WorkOrder::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['outputIngredient.unit', 'createdBatch', 'items.inputIngredient.unit', 'branch', 'producer'])
            ->orderBy('id', 'desc')
            ->get();

        $ingredients = $this->centralIngredientQuery($user->restaurant_id, $centralBranch?->id)
            ->with(['unit'])
            ->get();

        return Inertia::render('inventory/CentralKitchen', [
            'boms'        => $boms,
            'workOrders'  => $workOrders,
            'ingredients' => $ingredients,
            'canManageWarehouse' => $this->canManageWarehouse($user),
        ]);
    }

    public function getBoms(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->centralBranch($user->restaurant_id);
        $boms = $this->centralBomsQuery($user->restaurant_id, $centralBranch?->id)
            ->with(['outputIngredient', 'items.inputIngredient'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['boms' => $boms]);
    }

    public function storeBom(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            403,
            'Bạn không có quyền thiết lập định mức BOM sơ chế.'
        );
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'output_ingredient_id'   => 'required|integer',
            'standard_output_qty'     => 'required|numeric|gt:0',
            'expected_yield_percent'  => 'nullable|numeric|between:0,100',
            'allowed_wastage_percent' => 'nullable|numeric|between:0,100',
            'instructions'            => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.input_ingredient_id' => 'required|integer',
            'items.*.required_quantity'   => 'required|numeric|gt:0',
            'items.*.unit_symbol'         => 'nullable|string',
        ]);

        $bom = $this->kitchenService->createBom($user->restaurant_id, $validated, $user);

        return response()->json([
            'message' => 'Tạo định mức sơ chế BOM thành công.',
            'bom'     => $bom,
        ]);
    }

    public function getWorkOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->centralBranch($user->restaurant_id);
        $workOrders = WorkOrder::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['outputIngredient', 'createdBatch', 'items.inputIngredient', 'branch', 'producer'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['work_orders' => $workOrders]);
    }

    public function storeWorkOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseManage($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $branchId = $request->input('branch_id', $centralBranch->id);
        abort_unless((int) $branchId === (int) $centralBranch->id, 422, 'Lệnh sơ chế chỉ được thực hiện tại Kho Tổng.');

        $validated = $request->validate([
            'output_ingredient_id' => 'required|integer',
            'target_quantity'      => 'required|numeric|gt:0',
            'central_bom_id'       => 'nullable|integer',
            'production_date'      => 'nullable|date',
            'expiry_date'          => 'nullable|date',
            'notes'                => 'nullable|string',
            'items'                => 'nullable|array',
        ]);

        $workOrder = $this->kitchenService->createWorkOrder($user->restaurant_id, (int) $branchId, $validated, $user);

        return response()->json([
            'message'    => 'Tạo lệnh sơ chế sản xuất thành công.',
            'work_order' => $workOrder,
        ]);
    }

    public function executeWorkOrder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseManage($user);
        $centralBranch = $this->centralBranch($user->restaurant_id);
        abort_unless($centralBranch, 422, 'ChÆ°a thiáº¿t láº­p Kho Tá»•ng.');
        $workOrder = WorkOrder::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'actual_yield_quantity'   => 'required|numeric|gt:0',
            'actual_wastage_quantity' => 'nullable|numeric|ge:0',
            'items'                   => 'nullable|array',
        ]);

        $executed = $this->kitchenService->executeWorkOrder(
            $workOrder,
            $user,
            (float) $validated['actual_yield_quantity'],
            isset($validated['actual_wastage_quantity']) ? (float) $validated['actual_wastage_quantity'] : null,
            $validated['items'] ?? null
        );

        return response()->json([
            'message'    => "Hoàn tất sơ chế đơn #{$executed->work_order_code}. Đã nhập kho lô mới {$executed->created_batch_code}.",
            'work_order' => $executed,
        ]);
    }

    private function centralBranch(int $restaurantId): ?RestaurantBranch
    {
        return $this->warehouseService->getCentralWarehouse($restaurantId);
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

    private function centralBomsQuery(int $restaurantId, ?int $centralBranchId)
    {
        $query = CentralBom::where('restaurant_id', $restaurantId);

        if (! $centralBranchId) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('outputIngredient', fn ($ingredient) => $ingredient
                ->whereNull('branch_id')
                ->orWhere('branch_id', $centralBranchId))
            ->whereDoesntHave('items.inputIngredient', fn ($ingredient) => $ingredient
                ->whereNotNull('branch_id')
                ->where('branch_id', '!=', $centralBranchId));
    }

    private function canManageWarehouse($user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage');
    }

    private function authorizeWarehouseView($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.view'),
            403,
            'Bạn không có quyền xem nghiệp vụ Kho Tổng.'
        );
    }

    private function authorizeWarehouseManage($user): void
    {
        abort_unless(
            $this->canManageWarehouse($user),
            403,
            'Bạn không có quyền điều hành Kho Tổng.'
        );
    }
}
