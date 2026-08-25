<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Services\CentralWarehouseSupplyChainService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralWarehouseSupplyChainController extends Controller
{
    public function __construct(protected CentralWarehouseSupplyChainService $service) {}

    public function alerts(Request $request): JsonResponse
    {
        $this->authorizeWarehouse($request);

        return response()->json($this->service->alerts((int) $request->user()->restaurant_id));
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $this->authorizeWarehouse($request);

        return response()->json($this->service->reconciliation((int) $request->user()->restaurant_id));
    }

    public function supplierOptions(Request $request, int $ingredientId): JsonResponse
    {
        $this->authorizeWarehouse($request);
        $ingredient = Ingredient::where('restaurant_id', $request->user()->restaurant_id)
            ->with(['supplierOptions.supplier'])
            ->findOrFail($ingredientId);

        return response()->json(['ingredient_id' => $ingredient->id, 'suppliers' => $ingredient->supplierOptions]);
    }

    public function syncSupplierOptions(Request $request): JsonResponse
    {
        $this->authorizeWarehouse($request);
        $data = $request->validate([
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'suppliers' => 'required|array|min:1',
            'suppliers.*.supplier_id' => ['required', 'integer', TenantRule::exists('suppliers')],
            'suppliers.*.priority' => 'nullable|integer|min:1|max:999',
            'suppliers.*.is_primary' => 'nullable|boolean',
            'suppliers.*.is_active' => 'nullable|boolean',
            'suppliers.*.lead_time_days' => 'nullable|integer|min:0|max:365',
            'suppliers.*.minimum_order_quantity' => 'nullable|numeric|min:0',
            'suppliers.*.notes' => 'nullable|string|max:500',
        ]);

        return response()->json([
            'success' => true,
            'suppliers' => $this->service->syncSupplierOptions(
                (int) $request->user()->restaurant_id,
                (int) $data['ingredient_id'],
                $data['suppliers'],
            ),
        ]);
    }

    private function authorizeWarehouse(Request $request): void
    {
        abort_unless(
            $request->user()->isOwner()
                || $request->user()->isSuperAdmin()
                || $request->user()->hasRole('warehouse_manager')
                || $request->user()->can('warehouse.view'),
            403,
            'Bạn không có quyền xem dữ liệu chuỗi cung ứng Kho Tổng.'
        );
    }
}
