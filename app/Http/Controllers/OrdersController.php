<?php

namespace App\Http\Controllers;

use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Delivery\DeliveryDetail;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->where('is_active', true)
            ->where('is_available', true)
            ->with(['category'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'sku' => $p->sku ?? '—',
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
                'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
                'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
                'is_paused' => $p->paused_until && $p->paused_until->isFuture(),
                'is_out_of_stock' => $p->out_of_stock_until && $p->out_of_stock_until->isFuture(),
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
     public function store(StoreOrderRequest $request): RedirectResponse
     {
         $user = $request->user();
         $data = $request->validated();

         if (isset($data['table_id']) && isset($data['guests_count'])) {
             $table = \App\Models\RestaurantTable::find($data['table_id']);
             if ($table && (int) $data['guests_count'] > (int) $table->capacity) {
                 return back()->withErrors(['guests_count' => "Số lượng khách ({$data['guests_count']}) vượt quá sức chứa tối đa của bàn {$table->name} (Tối đa {$table->capacity} chỗ). Vui lòng chọn ghép bàn hoặc chuyển bàn lớn hơn."]);
             }
         }

         // Check kitchen availability status for products
         if (isset($data['items'])) {
             $productIds = collect($data['items'])->pluck('product_id')->toArray();
             $products = \App\Models\Product::whereIn('id', $productIds)->where('restaurant_id', $user->restaurant_id)->get();
             foreach ($products as $product) {
                 $isKitchenPaused = $product->paused_until && $product->paused_until->isFuture();
                 $isKitchenOutOfStock = $product->out_of_stock_until && $product->out_of_stock_until->isFuture();
                 if (!$product->is_active || !$product->is_available || $isKitchenPaused || $isKitchenOutOfStock) {
                     return back()->withErrors(['items' => "Món ăn {$product->name} tạm thời ngừng phục vụ."]);
                 }
             }
         }

         $order = DB::transaction(function () use ($data, $user) {
             $order = $this->orderService->createOrder($data, $user);

             if (($data['channel'] ?? '') === 'delivery') {
                 DeliveryDetail::create([
                     'restaurant_id' => $user->restaurant_id,
                     'order_id'      => $order->id,
                     'customer_name' => $data['delivery_customer_name'],
                     'phone'         => $data['delivery_phone'],
                     'address'       => $data['delivery_address'],
                     'latitude'      => $data['delivery_lat'] ?? null,
                     'longitude'     => $data['delivery_lng'] ?? null,
                     'delivery_fee'  => $data['delivery_fee'] ?? 0,
                     'cod_amount'    => $data['cod_amount'] ?? 0,
                     'notes'         => $data['delivery_notes'] ?? null,
                     'delivery_status' => 'pending',
                 ]);
             }

             return $order;
         });

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
            'bypass_code' => ['nullable', 'string'],
        ]);

        if ($data['status'] === 'cancelled' && !$user->can('approve_requests')) {
            $bypassSetting = \Illuminate\Support\Facades\DB::table('restaurant_settings')
                ->where('restaurant_id', $order->restaurant_id)
                ->where('key_name', 'manager_bypass_code')
                ->value('value');
            $expectedCode = $bypassSetting ? json_decode($bypassSetting) : 'MANAGER123';
            if (($data['bypass_code'] ?? null) !== $expectedCode) {
                return back()->withErrors(['status' => 'Bạn không có quyền hủy đơn hàng. Liên hệ quản lý.']);
            }
        }

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
        abort_unless($user->can('manage_orders') || $user->can('split_orders'), 403);

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
            'guests_count' => ['nullable', 'integer', 'min:1'],
            'bypass_code' => ['nullable', 'string'],
        ]);

        if (isset($data['guests_count']) && $order->table_id) {
            $table = \App\Models\RestaurantTable::find($order->table_id);
            if ($table && (int) $data['guests_count'] > (int) $table->capacity) {
                return back()->withErrors(['guests_count' => "Số lượng khách ({$data['guests_count']}) vượt quá sức chứa tối đa của bàn {$table->name} (Tối đa {$table->capacity} chỗ). Vui lòng chọn ghép bàn hoặc chuyển bàn lớn hơn."]);
            }
        }

        if (isset($data['items'])) {
            $productIds = collect($data['items'])->pluck('product_id')->filter()->unique()->toArray();
            $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($data['items'] as $itemData) {
                if (!empty($itemData['product_id'])) {
                    $prod = $products->get($itemData['product_id']);
                    if ($prod && isset($itemData['unit_price']) && (float) $itemData['unit_price'] < (float) $prod->price) {
                        if (!$user->can('approve_requests')) {
                            $bypassSetting = \Illuminate\Support\Facades\DB::table('restaurant_settings')
                                ->where('restaurant_id', $order->restaurant_id)
                                ->where('key_name', 'manager_bypass_code')
                                ->value('value');
                            $expectedCode = $bypassSetting ? json_decode($bypassSetting) : 'MANAGER123';
                            if (($data['bypass_code'] ?? null) !== $expectedCode) {
                                return back()->withErrors(['items' => 'Giảm giá món ăn trực tiếp yêu cầu quyền phê duyệt của quản lý.']);
                            }
                        }
                    }
                }
            }
        }

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
        $upsellRequest = new \Illuminate\Http\Request(['items' => $itemNames]);
        $upsellRequest->setUserResolver(fn() => $user);
        $suggestionResult = $promotionController->getUpsellSuggestion($upsellRequest);
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
            'payment_method' => ['required', 'in:cash,bank_transfer,card,ewallet,debt'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        if (isset($data['customer_id']) && $data['customer_id']) {
            $order->update(['customer_id' => $data['customer_id']]);
        }

        if ($data['payment_method'] === 'debt') {
            $customerId = $order->customer_id ?: ($data['customer_id'] ?? null);
            if (!$customerId) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Giao dịch ghi nợ yêu cầu thông tin khách hàng.'], 422);
                }
                return back()->withErrors(['customer_id' => 'Giao dịch ghi nợ yêu cầu thông tin khách hàng.']);
            }

            $customer = \App\Models\Customer::find($customerId);
            if (!$customer || (!$customer->is_vip && !$customer->is_b2b)) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Khách hàng không được cấp quyền ghi nợ (Yêu cầu VIP/B2B).'], 422);
                }
                return back()->withErrors(['customer_id' => 'Khách hàng không được cấp quyền ghi nợ (Yêu cầu VIP/B2B).']);
            }

            $newDebt = (float)$customer->current_debt + (float)$order->total_amount;
            if ($newDebt > (float)$customer->credit_limit) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Hạn mức tín dụng của khách hàng không đủ.'], 422);
                }
                return back()->withErrors(['customer_id' => 'Hạn mức tín dụng của khách hàng không đủ.']);
            }

            // Perform transaction
            DB::transaction(function () use ($order, $customer, $data, $user) {
                // Apply membership discount
                if (!str_contains($order->note ?? '', '[Ưu đãi Hội viên')) {
                    $lvl = $customer->membership_level ?? 'silver';
                    $loyaltyDiscount = 0.0;
                    if ($lvl === 'diamond') {
                        $loyaltyDiscount = round($order->subtotal * 0.10, 2);
                    } elseif ($lvl === 'gold') {
                        $loyaltyDiscount = round($order->subtotal * 0.05, 2);
                    }
                    
                    if ($loyaltyDiscount > 0) {
                        $order->discount_amount = (float) $order->discount_amount + $loyaltyDiscount;
                        $order->total_amount = max(0.0, (float) $order->subtotal - (float) $order->discount_amount);
                        $order->note = ($order->note ? $order->note . ' ' : '') . "[Ưu đãi Hội viên " . ($lvl === 'diamond' ? 'Kim Cương' : 'Vàng') . ": -" . number_format($loyaltyDiscount) . "đ]";
                        $order->save();
                    }
                }

                // Increment customer debt
                $customer->increment('current_debt', $order->total_amount);

                // Create AccountReceivable record
                \App\Models\AccountReceivable::create([
                    'restaurant_id' => $order->restaurant_id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'amount' => $order->total_amount,
                    'received_amount' => 0,
                    'due_date' => now()->addDays(30)->toDateString(), // 30-day payment term
                    'status' => 'unpaid',
                ]);

                // Create Payment record
                \App\Models\Payment::create([
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'order_id' => $order->id,
                    'processed_by' => $user->id,
                    'payment_method' => 'debt',
                    'status' => 'unpaid',
                    'amount' => $order->total_amount,
                    'cash_received' => 0,
                    'change_amount' => 0,
                    'paid_at' => null,
                ]);

                // Deduct inventory
                app(\App\Services\InventoryService::class)->deductInventoryForOrder($order, $user);

                // Update order to completed and payment_status to unpaid
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'unpaid',
                    'completed_at' => now(),
                    'cashier_user_id' => $user->id,
                ]);

                // Release table
                if ($order->table_id) {
                    \App\Models\RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
                }

                // Customer updates
                $customer->update(['last_order_at' => now()]);
                \App\Services\CdpService::calculateRfmForCustomer($customer);

                \App\Models\AuditLog::log('order_paid_with_debt', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'unpaid']);
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ghi nợ đơn hàng thành công!',
                ]);
            }
            return back()->with('success', 'Ghi nợ đơn hàng thành công!');
        }

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

    /**
     * Yêu cầu hoàn tiền cho đơn hàng đã thanh toán.
     * - Chỉ áp dụng cho đơn đã paid
     * - Bắt buộc nhập lý do hoàn tiền
     * - Không phải Owner/Manager → phải có bypass code
     * - Ghi audit log với chi tiết old_values / new_values
     * - Hoàn tồn kho nếu refund toàn bộ đơn
     */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('process_payments') || $user->can('approve_requests'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($order->payment_status === 'paid', 422, 'Chỉ có thể hoàn tiền đơn đã thanh toán.');

        $data = $request->validate([
            'reason'       => ['required', 'string', 'min:10', 'max:500'],
            'refund_amount'=> ['required', 'numeric', 'min:1000', "max:{$order->total_amount}"],
            'bypass_code'  => ['nullable', 'string'],
            'refund_type'  => ['required', 'in:full,partial'],
        ]);

        // Nếu không phải Owner/Manager → phải có bypass code
        if (!$user->can('approve_requests')) {
            $bypassSetting = \Illuminate\Support\Facades\DB::table('restaurant_settings')
                ->where('restaurant_id', $order->restaurant_id)
                ->where('key_name', 'manager_bypass_code')
                ->value('value');
            $expectedCode = $bypassSetting ? json_decode($bypassSetting) : 'MANAGER123';
            if (($data['bypass_code'] ?? null) !== $expectedCode) {
                return back()->withErrors(['bypass_code' => 'Hoàn tiền yêu cầu mã phê duyệt của quản lý.']);
            }
        }

        DB::transaction(function () use ($order, $data, $user) {
            $oldPaymentStatus = $order->payment_status;

            $order->update([
                'payment_status'  => $data['refund_type'] === 'full' ? 'refunded' : 'partial_refund',
                'refund_amount'   => $data['refund_amount'],
                'refund_reason'   => $data['reason'],
                'refunded_at'     => now(),
                'refunded_by'     => $user->id,
            ]);

            // Hoàn tồn kho nếu refund toàn bộ
            if ($data['refund_type'] === 'full') {
                app(\App\Services\InventoryService::class)->restoreStockForOrder($order);
                $order->update(['status' => 'cancelled']);
            }

            // Ghi audit log
            \App\Models\AuditLog::create([
                'restaurant_id' => $order->restaurant_id,
                'user_id'       => $user->id,
                'action'        => 'refund_processed',
                'auditable_type'=> Order::class,
                'auditable_id'  => $order->id,
                'old_values'    => json_encode(['payment_status' => $oldPaymentStatus]),
                'new_values'    => json_encode([
                    'payment_status' => $order->payment_status,
                    'refund_amount'  => $data['refund_amount'],
                    'refund_reason'  => $data['reason'],
                ]),
                'ip_address'    => $request->ip(),
            ]);
        });

        return back()->with('success', 'Đã xử lý hoàn tiền thành công. Nhật ký kiểm toán đã được ghi nhận.');
    }
}

