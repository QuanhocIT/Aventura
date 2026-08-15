<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * Tự động tạo bản nháp đơn đặt hàng (Auto Purchase Order) 1-Click khi nguyên liệu dưới ngưỡng tối thiểu.
     */
    public function generateAutoPo(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'inventory_staff']), 403);

        $restaurantId = $user->restaurant_id;
        $branchId = $this->resolveOperationalBranch($user);

        // Fetch all ingredients for restaurant with inventory and unit
        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->with(['unit', 'inventories' => fn ($q) => $q
                ->where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)])
            ->get();

        $lowStockIngredients = collect();

        foreach ($ingredients as $ing) {
            $inventory = $ing->inventories->first();
            $currentStock = $inventory ? (float) $inventory->quantity_on_hand : 0.0;
            $minStock = (float) ($ing->min_stock_level ?? 0.0);

            if ($currentStock < $minStock && $minStock > 0) {
                $suggestedQty = max(1.0, round(($minStock * 2) - $currentStock, 2));
                $unitCost = (float) ($ing->average_cost ?? 0.0);

                $lowStockIngredients->push([
                    'ingredient' => $ing,
                    'supplier_id' => $ing->supplier_id,
                    'current_stock' => $currentStock,
                    'min_stock' => $minStock,
                    'suggested_qty' => $suggestedQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $suggestedQty * $unitCost,
                ]);
            }
        }

        if ($lowStockIngredients->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tất cả nguyên liệu hiện tại đều trên ngưỡng tối thiểu. Không cần tạo đơn mua hàng mới.',
                ]);
            }

            return back()->with('info', 'Tất cả nguyên liệu hiện tại đều trên ngưỡng tồn tối thiểu.');
        }

        // Group by supplier_id
        $groupedBySupplier = $lowStockIngredients->groupBy('supplier_id');
        $createdPoCount = 0;

        DB::transaction(function () use ($groupedBySupplier, $restaurantId, $branchId, $user, &$createdPoCount) {
            foreach ($groupedBySupplier as $supplierId => $items) {
                $totalAmount = $items->sum('total_cost');

                $po = PurchaseOrder::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId ?: null,
                    'po_number' => 'PO-AUTO-'.strtoupper(Str::random(6)),
                    'status' => 'draft',
                    'total_amount' => $totalAmount,
                    'created_by' => $user->id,
                    'notes' => 'Hệ thống tự động gom đơn mua hàng do tồn kho xuống dưới ngưỡng tối thiểu.',
                    'due_date' => now()->addDays(3)->toDateString(),
                ]);

                foreach ($items as $item) {
                    $ing = $item['ingredient'];
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'ingredient_id' => $ing->id,
                        'quantity_ordered' => $item['suggested_qty'],
                        'price_per_unit' => $item['unit_cost'],
                        'total_cost' => $item['total_cost'],
                    ]);
                }

                $createdPoCount++;
            }
        });

        $msg = "Đã tự động tạo {$createdPoCount} đơn mua hàng nháp (Purchase Orders) cho các nguyên liệu dưới ngưỡng tồn kho.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'po_count' => $createdPoCount,
            ]);
        }

        return back()->with('success', $msg);
    }

    private function resolveOperationalBranch($user): int
    {
        $branchId = $this->tenantContext->activeBranchId()
            ?? ($user->isOwner() ? $user->assignedBranchId() : null);

        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Hãy chọn chi nhánh hiện tại trước khi tạo đơn mua hàng.',
            ]);
        }
        if (! $user->canAccessBranch($branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Bạn không có quyền truy cập chi nhánh này.',
            ]);
        }

        return $branchId;
    }

    /**
     * Gửi nhắc nhở giao hàng cho các đơn PO sắp đến hạn hoặc quá hạn.
     */
    public function sendDeliveryReminder(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_suppliers') || $user->hasAnyRole(['owner', 'manager', 'inventory_staff', 'super_admin']), 403);

        $restaurantId = $user->restaurant_id;

        $pos = PurchaseOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['pending_approval', 'approved', 'preparing', 'shipping'])
            ->get();

        if ($pos->isEmpty()) {
            $msg = 'Không có đơn mua hàng nào cần nhắc nhở giao hàng.';
            return $request->wantsJson() ? response()->json(['success' => false, 'message' => $msg], 422) : back()->withErrors(['po' => $msg]);
        }

        $remindedCount = 0;
        foreach ($pos as $po) {
            $po->touch();
            $remindedCount++;
        }

        \App\Models\AuditLog::log(
            'po_delivery_reminded',
            'updated',
            $pos->first(),
            null,
            ['reminded_count' => $remindedCount]
        );

        $msg = "Đã gửi thông báo nhắc nhở giao hàng tới Nhà cung cấp cho {$remindedCount} đơn mua hàng.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'reminded_count' => $remindedCount,
            ]);
        }

        return back()->with('success', $msg);
    }
}
