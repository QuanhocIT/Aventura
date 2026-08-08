<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderSplitService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /** Chuyển nhanh đơn tại bàn sang mang về và cộng phí hộp/túi nếu đã cấu hình. */
    public function convertToTakeaway(Request $request, Order $order): RedirectResponse
    {
        abort_unless(
            $request->user()->can('manage_orders') || $request->user()->can('create_orders'),
            403,
        );
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);
        abort_unless($order->channel === 'dine_in' && $order->table_id, 422, 'Đơn này không còn ở trạng thái tại bàn.');
        abort_unless(! in_array($order->status, ['completed', 'cancelled'], true), 422, 'Đơn đã kết thúc, không thể chuyển mang về.');
        abort_unless(! in_array($order->payment_status, ['paid', 'refunded'], true), 422, 'Đơn đã thanh toán, không thể tự động cộng lại phí mang về.');

        $packagingSetting = DB::table('restaurant_settings')
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->where('key_name', 'takeaway_packaging_fee')
            ->value('value');
        $packagingFee = is_numeric($packagingSetting)
            ? (float) $packagingSetting
            : (float) (json_decode((string) $packagingSetting, true) ?? 0);

        DB::transaction(function () use ($order, $request, $packagingFee): void {
            $lockedOrder = Order::whereKey($order->id)
                ->where('restaurant_id', $request->user()->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldTableId = $lockedOrder->table_id;
            $lockedOrder->channel = 'takeaway';
            $lockedOrder->table_id = null;
            $lockedOrder->service_charge = (float) $lockedOrder->service_charge + $packagingFee;
            $lockedOrder->total_amount = max(
                0.0,
                (float) $lockedOrder->subtotal
                    - (float) $lockedOrder->discount_amount
                    + (float) $lockedOrder->service_charge
                    + (float) $lockedOrder->tax_amount,
            );
            $lockedOrder->note = trim(($lockedOrder->note ? $lockedOrder->note.' ' : '')
                .'[Chuyển sang mang về'.($packagingFee > 0 ? ', đã cộng phí hộp/túi' : '').']');
            $lockedOrder->save();

            $table = RestaurantTable::whereKey($oldTableId)
                ->where('restaurant_id', $lockedOrder->restaurant_id)
                ->where('branch_id', $lockedOrder->branch_id)
                ->lockForUpdate()
                ->first();
            if ($table && ! $table->orders()->activeForService()->exists()) {
                $table->update(['status' => 'available']);
            }
        });

        return back()->with('success', $packagingFee > 0
            ? 'Đã chuyển đơn sang mang về và cộng phí hộp/túi.'
            : 'Đã chuyển đơn sang mang về.');
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
        abort_unless(
            $request->user()->can('manage_orders') || $request->user()->can('split_orders'),
            403
        );
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
        abort_unless(
            $request->user()->can('manage_orders') || $request->user()->can('split_orders'),
            403
        );
        abort_unless($order->restaurant_id === $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch((int) $order->branch_id), 403);
    }
}
