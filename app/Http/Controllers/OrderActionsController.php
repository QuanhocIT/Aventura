<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderSplitService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tách bill / gộp đơn / chuyển bàn — yêu cầu quyền manage_orders. */
class OrderActionsController extends Controller
{
    public function __construct(private OrderSplitService $service) {}

    public function split(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $newOrder = $this->service->splitOrder($order, $data['item_ids'], $request->user());

        return back()->with('success', "Đã tách bill mới {$newOrder->order_number}.");
    }

    public function merge(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'target_order_id' => ['required', 'integer'],
        ]);

        $target = Order::withoutGlobalScopes()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->where('branch_id', $order->branch_id)
            ->findOrFail($data['target_order_id']);

        $this->service->mergeOrders($order, $target, $request->user());

        return back()->with('success', "Đã gộp bill {$order->order_number} vào {$target->order_number}.");
    }

    public function moveTable(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'table_id' => ['required', 'integer'],
        ]);

        $this->service->moveTable($order, (int) $data['table_id'], $request->user());

        return back()->with('success', 'Đã chuyển bàn thành công.');
    }

    /** Danh sách món của đơn — cho dialog tách bill. */
    public function items(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        return response()->json(
            $order->items()->with('product:id,name')->get()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product?->name ?? 'Món ăn',
                'quantity' => (float) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])
        );
    }

    /** Danh sách bàn trống — cho dialog chuyển bàn. */
    public function availableTables(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage_orders'), 403);
        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 403, 'Vui lòng chọn chi nhánh trước khi chuyển bàn.');

        return response()->json(
            RestaurantTable::where('restaurant_id', $request->user()->restaurant_id)
                ->where('branch_id', $branchId)
                ->where('status', 'available')
                ->whereDoesntHave('orders', fn ($query) => $query->activeForService())
                ->orderBy('name')
                ->get(['id', 'name', 'capacity'])
        );
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($request->user()->can('manage_orders'), 403);
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);
    }
}
