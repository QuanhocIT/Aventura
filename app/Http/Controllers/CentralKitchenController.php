<?php

namespace App\Http\Controllers;

use App\Models\CentralBom;
use App\Models\WorkOrder;
use App\Services\CentralKitchenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Ingredient;

class CentralKitchenController extends Controller
{
    public function __construct(
        protected CentralKitchenService $kitchenService
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $boms = CentralBom::where('restaurant_id', $user->restaurant_id)
            ->with(['outputIngredient.unit', 'items.inputIngredient.unit'])
            ->orderBy('id', 'desc')
            ->get();

        $workOrders = WorkOrder::where('restaurant_id', $user->restaurant_id)
            ->with(['outputIngredient.unit', 'createdBatch', 'items.inputIngredient.unit', 'branch', 'producer'])
            ->orderBy('id', 'desc')
            ->get();

        $ingredients = Ingredient::where('restaurant_id', $user->restaurant_id)
            ->with(['unit'])
            ->get();

        return Inertia::render('inventory/CentralKitchen', [
            'boms'        => $boms,
            'workOrders'  => $workOrders,
            'ingredients' => $ingredients,
        ]);
    }

    public function getBoms(Request $request): JsonResponse
    {
        $user = $request->user();
        $boms = CentralBom::where('restaurant_id', $user->restaurant_id)
            ->with(['outputIngredient', 'items.inputIngredient'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['boms' => $boms]);
    }

    public function storeBom(Request $request): JsonResponse
    {
        $user = $request->user();
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
        $workOrders = WorkOrder::where('restaurant_id', $user->restaurant_id)
            ->with(['outputIngredient', 'createdBatch', 'items.inputIngredient', 'branch', 'producer'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['work_orders' => $workOrders]);
    }

    public function storeWorkOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->input('branch_id', $user->branch_id);

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
        $workOrder = WorkOrder::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

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
}
