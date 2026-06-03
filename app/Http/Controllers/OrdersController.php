<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\OrderService;
use App\Repositories\OrderRepositoryInterface;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected OrderRepositoryInterface $orderRepository
    ) {}
    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('create_orders'), 403);
        $restaurantId = $user->restaurant_id;

        $categories = \App\Models\ProductCategory::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $products = \App\Models\Product::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->with(['category'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'sku' => $p->sku ?? '—',
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
            ]);

        $tables = \App\Models\RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('status', 'available')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ]);

        return Inertia::render('orders/Create', [
            'products' => $products,
            'categories' => $categories,
            'tables' => $tables,
        ]);
     }
 
     /**
      * Lưu đơn hàng mới từ giao diện POS bán hàng SPA.
      */
     public function store(Request $request): RedirectResponse
     {
         $user = $request->user();
         abort_unless($user->can('create_orders'), 403);

         $data = $request->validate([
             'table_id' => ['nullable', 'exists:restaurant_tables,id'],
             'note' => ['nullable', 'string', 'max:500'],
             'items' => ['required', 'array', 'min:1'],
             'items.*.product_id' => ['required', 'exists:products,id'],
             'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
             'items.*.notes' => ['nullable', 'string', 'max:255'],
         ]);

         $this->orderService->createOrder($data, $user);

         if ($user->can('create_orders') || url()->previous() === route('dashboard')) {
             return redirect()->back()->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
         }

         return redirect()->route('orders.index')->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
     }
 
     public function index(Request $request): Response
     {
         $user = $request->user();
         abort_unless($user->can('create_orders') || $user->can('manage_orders') || $user->can('process_payments'), 403);
         $restaurantId = $user->restaurant_id;

         $statusFilter = $request->get('status', 'all');
         $dateFilter   = $request->get('date', today()->toDateString());

         $ordersQuery = $this->orderRepository->getOrdersQuery($restaurantId, [
             'status' => $statusFilter,
             'date' => $dateFilter,
         ]);

         $orders = $ordersQuery->get()->map(fn ($o) => [
             'id'             => $o->id,
             'order_number'   => $o->order_number,
             'status'         => $o->status,
             'payment_status' => $o->payment_status,
             'channel'        => $o->channel,
             'table_name'     => $o->table?->name,
             'area_name'      => $o->table?->area?->name,
             'total_amount'   => (float) $o->total_amount,
             'items_count'    => $o->items->count(),
             'created_at'     => $o->created_at->format('H:i'),
             'completed_at'   => $o->completed_at?->format('H:i'),
         ]);

         $summary = $this->orderRepository->getSummaryStats($restaurantId, $dateFilter);

         $autoPaySetting = \Illuminate\Support\Facades\DB::table('restaurant_settings')
             ->where('restaurant_id', $restaurantId)
             ->where('key_name', 'auto_pay_on_last_shift_close')
             ->value('value');
         $autoPayEnabled = filter_var(json_decode($autoPaySetting ?? 'false'), FILTER_VALIDATE_BOOLEAN);

         return Inertia::render('orders/Index', [
             'orders'        => $orders,
             'summary'       => $summary,
             'filters'       => ['status' => $statusFilter, 'date' => $dateFilter],
             'autoPayEnabled'=> $autoPayEnabled,
         ]);
     }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('manage_kitchen') || $user->can('create_orders'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,preparing,completed,cancelled'],
        ]);

        $this->orderService->updateOrderStatus($order, $data['status'], $user);

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    /**
     * Tách đơn hàng hiện tại sang một bàn trống mới (Cashier / Staff).
     */
    public function split(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);

        $data = $request->validate([
            'table_id' => ['required', 'exists:restaurant_tables,id'],
            'items' => ['required', 'array'],
            'items.*.order_item_id' => ['required', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->orderService->splitOrder($order, $data, $user);

        return back()->with('success', 'Đã tách đơn hàng ra bàn trống thành công. Giao dịch này đã được đánh dấu đỏ cảnh báo.');
    }

    /**
     * Phê duyệt đối soát đơn bị tách để xóa khoản phạt âm tiền (Chỉ Owner).
     */
    public function overrideSplitPenalty(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('override_split_penalty'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_unless((bool) $order->is_split, 422);

        $this->orderService->overrideSplitPenalty($order, $user);

        return back()->with('success', 'Đã phê duyệt đối soát đơn tách. Khoản phạt âm tiền của ca làm việc đã được vô hiệu hóa.');
    }

    /**
     * Sửa đổi thông tin đơn hàng / giá món / áp dụng giảm giá (Cashier / Manager).
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);

        $data = $request->validate([
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'exists:order_items,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Order Locking: Cannot delete existing items or decrease quantity of existing items once they have been created (saved to DB)
        if (isset($data['items'])) {
            $payloadItemIds = collect($data['items'])->pluck('id')->filter()->toArray();
            $dbItems = $order->items()->where('status', '!=', 'cancelled')->get();

            foreach ($dbItems as $dbItem) {
                // If an existing item is missing from the payload, it means it is deleted
                if (!in_array($dbItem->id, $payloadItemIds)) {
                    return back()->withErrors(['items' => 'Món ăn đã được tạo và gửi thông báo. Bạn không thể xóa món ăn khỏi đơn.']);
                }

                // If quantity is decreased
                $payloadItem = collect($data['items'])->firstWhere('id', $dbItem->id);
                if ($payloadItem && (float) $payloadItem['quantity'] < (float) $dbItem->quantity) {
                    return back()->withErrors(['items' => 'Món ăn đã được tạo và gửi thông báo. Bạn không thể giảm số lượng món ăn.']);
                }
            }
        }

        $this->orderService->updateOrder($order, $data, $user);

        return back()->with('success', 'Đã cập nhật thông tin đơn hàng và ghi nhận nhật ký kiểm toán.');
    }

    /**
     * Xác nhận đơn hàng QR từ khách hàng và lấy gợi ý upselling AI.
     */
    public function confirmQr(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_if($order->status !== 'pending', 422, 'Đơn hàng này đã được xác nhận trước đó.');

        $user = $request->user();
        abort_unless($user->can('manage_orders') || $user->can('create_orders'), 403);

        $this->orderService->confirmQr($order, $user);

        // Lấy gợi ý Upselling từ PromotionController
        $promotionController = new \App\Http\Controllers\PromotionController();
        $itemNames = $order->items->map(fn($item) => $item->product?->name)->filter()->toArray();
        $suggestionResult = $promotionController->getUpsellSuggestion(new Request(['items' => $itemNames]));
        $upsell = $suggestionResult->getData();

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận đơn hàng QR thành công!',
            'upsell' => $upsell,
        ]);
    }

    /**
     * Thanh toán đơn hàng (Chỉ cashier/owner).
     */
    public function pay(Request $request, Order $order): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('process_payments'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_if($order->payment_status === 'paid', 422, 'Đơn hàng này đã được thanh toán rồi.');

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,card,ewallet'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->orderService->payOrder($order, $data, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thanh toán đơn hàng thành công!',
            ]);
        }

        return back()->with('success', 'Thanh toán đơn hàng thành công!');
    }

    public function toggleAutoPaySetting(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('approve_requests') || $user->can('manage_restaurant_settings'), 403);

        $restaurantId = $user->restaurant_id;

        $setting = \Illuminate\Support\Facades\DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->where('key_name', 'auto_pay_on_last_shift_close')
            ->first();

        if ($setting) {
            $currentVal = filter_var(json_decode($setting->value) ?? $setting->value, FILTER_VALIDATE_BOOLEAN);
            $newVal = !$currentVal;
            \Illuminate\Support\Facades\DB::table('restaurant_settings')
                ->where('id', $setting->id)
                ->update(['value' => json_encode($newVal), 'updated_at' => now()]);
        } else {
            $newVal = true;
            \Illuminate\Support\Facades\DB::table('restaurant_settings')->insert([
                'restaurant_id' => $restaurantId,
                'key_name' => 'auto_pay_on_last_shift_close',
                'value' => json_encode($newVal),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Đã cập nhật chế độ tự động thanh toán đơn ca cuối.');
    }
}
