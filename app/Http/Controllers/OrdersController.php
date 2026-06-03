<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();
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
         $restaurantId = $user->restaurant_id;
         $branchId = $user->branch_id;
 
         $data = $request->validate([
             'table_id' => ['nullable', 'exists:restaurant_tables,id'],
             'note' => ['nullable', 'string', 'max:500'],
             'items' => ['required', 'array', 'min:1'],
             'items.*.product_id' => ['required', 'exists:products,id'],
             'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
             'items.*.notes' => ['nullable', 'string', 'max:255'],
         ]);
 
         \Illuminate\Support\Facades\DB::transaction(function () use ($restaurantId, $branchId, $user, $data) {
             // Sinh mã hóa đơn duy nhất
             $orderNumber = 'ORD-' . strtoupper(uniqid());

             // Pre-load tất cả products trong 1 query thay vì N queries trong loop
             $productIds = array_column($data['items'], 'product_id');
             $products = \App\Models\Product::where('restaurant_id', $restaurantId)
                 ->whereIn('id', $productIds)
                 ->get()
                 ->keyBy('id');

             // Tính tổng tiền tạm tính
             $subtotal = 0;
             $itemsToCreate = [];

             foreach ($data['items'] as $itemData) {
                 $product = $products->get($itemData['product_id'])
                     ?? abort(422, 'Sản phẩm không tìm thấy: ' . $itemData['product_id']);
                 $lineTotal = (float) $product->price * (float) $itemData['quantity'];
                 $subtotal += $lineTotal;

                 $itemsToCreate[] = [
                     'restaurant_id' => $restaurantId,
                     'product_id' => $product->id,
                     'quantity' => (float) $itemData['quantity'],
                     'unit_price' => (float) $product->price,
                     'discount_amount' => 0,
                     'line_total' => $lineTotal,
                     'status' => 'pending',
                     'notes' => $itemData['notes'] ?? null,
                 ];
             }
 
             $order = Order::create([
                 'restaurant_id' => $restaurantId,
                 'branch_id' => $branchId,
                 'table_id' => $data['table_id'] ?? null,
                 'created_by' => $user->id,
                 'order_number' => $orderNumber,
                 'channel' => $data['table_id'] ? 'dine_in' : 'takeaway',
                 'status' => 'pending',
                 'payment_status' => 'unpaid',
                 'subtotal' => $subtotal,
                 'discount_amount' => 0,
                 'total_amount' => $subtotal,
                 'note' => $data['note'] ?? null,
             ]);
 
             foreach ($itemsToCreate as $item) {
                 $item['order_id'] = $order->id;
                 \App\Models\OrderItem::create($item);
             }

             if ($order->table_id) {
                 \App\Models\RestaurantTable::where('id', $order->table_id)->update(['status' => 'occupied']);
             }
 
             // Ghi log kiểm toán
             \App\Models\AuditLog::log('order_created', 'created', $order, null, [
                 'total_amount' => (float) $order->total_amount,
                 'items_count' => count($itemsToCreate)
             ]);
         });
 
         event(new \App\Events\Kitchen\KitchenUpdated($restaurantId));

         if ($request->user()->hasRole('cashier') || url()->previous() === route('dashboard')) {
            return redirect()->back()->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
        }

        return redirect()->route('orders.index')->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
    }
 
     public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $statusFilter = $request->get('status', 'all');
        $dateFilter   = $request->get('date', today()->toDateString());

        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['table.area', 'items'])
            ->whereDate('created_at', $dateFilter)
            ->latest();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $orders = $query->get()->map(fn ($o) => [
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

        $summary = [
            'total'     => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->count(),
            'pending'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'pending')->count(),
            'preparing' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'preparing')->count(),
            'completed' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->count(),
            'cancelled' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'cancelled')->count(),
            'revenue'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->sum('total_amount'),
        ];

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

        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,preparing,completed,cancelled'],
        ]);

        $user = $request->user();
        $oldStatus = $order->status;
        $newStatus = $data['status'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $user, $oldStatus, $newStatus) {
            // Nếu chuyển thành completed và trạng thái cũ khác completed
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $order->load(['items.product.recipes.ingredient.unit']);

                foreach ($order->items as $item) {
                    $product = $item->product;
                    if ($product && $product->track_inventory) {
                        foreach ($product->recipes as $recipe) {
                            $recipeQuantity = (float) $recipe->quantity;
                            $itemQuantity = (float) $item->quantity;
                            $wasteRate = (float) $recipe->waste_rate;

                            // Lượng dùng = (định lượng * số lượng bán) * (1 + tỉ lệ hao hụt / 100)
                            $totalUsed = ($recipeQuantity * $itemQuantity) * (1 + ($wasteRate / 100));

                            // Tìm hoặc tạo bản ghi kho cho chi nhánh và nguyên liệu
                            $inventory = \App\Models\Inventory::firstOrCreate([
                                'restaurant_id' => $order->restaurant_id,
                                'branch_id' => $order->branch_id,
                                'ingredient_id' => $recipe->ingredient_id,
                            ], [
                                'quantity_on_hand' => 0,
                                'theoretical_quantity' => 0,
                                'last_cost' => $recipe->ingredient->average_cost ?? 0,
                            ]);

                            $oldQty = (float) $inventory->quantity_on_hand;
                            $oldTheoretical = (float) $inventory->theoretical_quantity;

                            // Trừ kho vật lý và tồn lý thuyết (clamping max(0, ...))
                            $inventory->update([
                                'quantity_on_hand' => max(0.0, $oldQty - $totalUsed),
                                'theoretical_quantity' => max(0.0, $oldTheoretical - $totalUsed),
                            ]);

                            // Tạo giao dịch nhập/xuất kho (loại usage, hướng out)
                            \App\Models\InventoryTransaction::create([
                                'restaurant_id' => $order->restaurant_id,
                                'branch_id' => $order->branch_id,
                                'ingredient_id' => $recipe->ingredient_id,
                                'inventory_id' => $inventory->id,
                                'order_id' => $order->id,
                                'performed_by' => $user->id,
                                'type' => 'usage',
                                'direction' => 'out',
                                'quantity' => $totalUsed,
                                'unit_cost' => $recipe->ingredient->average_cost ?? 0,
                                'total_cost' => $totalUsed * ($recipe->ingredient->average_cost ?? 0),
                                'notes' => "Khấu hao nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                                'occurred_at' => now(),
                            ]);

                            // Cập nhật trạng thái bản ghi kho đệm (inventory_reservations) từ holding sang committed
                            \App\Models\InventoryReservation::where('order_id', $order->id)
                                ->where('ingredient_id', $recipe->ingredient_id)
                                ->where('status', 'holding')
                                ->update(['status' => 'committed']);
                        }
                    }
                }

                // Cập nhật payment_status thành paid khi hoàn thành đơn
                $order->payment_status = 'paid';
            }

            // Nếu chuyển thành cancelled và trạng thái cũ khác cancelled
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                // Giải phóng các bản ghi giữ kho đệm (inventory_reservations) từ holding sang released
                \App\Models\InventoryReservation::where('order_id', $order->id)
                    ->where('status', 'holding')
                    ->update(['status' => 'released']);

                // Ghi nhận nhật ký kiểm toán tĩnh
                \App\Models\AuditLog::log('order_cancelled', 'deleted', $order, ['status' => $oldStatus], ['status' => 'cancelled']);
            }

            $order->update([
                'status'       => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : $order->completed_at,
                'cancelled_at' => $newStatus === 'cancelled' ? now() : $order->cancelled_at,
                'payment_status' => $order->payment_status,
            ]);

            if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
                \App\Models\RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }
        });

        event(new \App\Events\Kitchen\KitchenUpdated($order->restaurant_id));

        return back()->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    /**
     * Tách đơn hàng hiện tại sang một bàn trống mới (Cashier / Staff).
     */
    public function split(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

        $data = $request->validate([
            'table_id' => ['required', 'exists:restaurant_tables,id'],
            'items' => ['required', 'array'],
            'items.*.order_item_id' => ['required', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = $request->user();
        $oldAmount = (float) $order->total_amount;

        $newOrder = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data, $user) {
            // 1. Tạo đơn hàng mới ở bàn trống
            $newOrder = Order::create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'table_id' => $data['table_id'],
                'created_by' => $user->id,
                'order_number' => $order->order_number . '-SPLIT-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)),
                'channel' => $order->channel,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'is_split' => true,
                'split_from_order_id' => $order->id,
                'is_red_flagged' => true, // Đánh dấu đỏ lập tức
                'is_override_split_penalty' => false,
            ]);

            // 2. Chuyển các món ăn chỉ định sang đơn mới
            foreach ($data['items'] as $itemData) {
                $originalItem = \App\Models\OrderItem::where('order_id', $order->id)
                    ->findOrFail($itemData['order_item_id']);

                $splitQty = (float) $itemData['quantity'];
                $origQty = (float) $originalItem->quantity;

                if ($splitQty >= $origQty) {
                    // Chuyển toàn bộ món
                    $originalItem->update(['order_id' => $newOrder->id]);
                } else {
                    // Tách một phần
                    $originalItem->update([
                        'quantity' => $origQty - $splitQty,
                        'line_total' => ($origQty - $splitQty) * $originalItem->unit_price,
                    ]);

                    \App\Models\OrderItem::create([
                        'restaurant_id' => $order->restaurant_id,
                        'order_id' => $newOrder->id,
                        'product_id' => $originalItem->product_id,
                        'quantity' => $splitQty,
                        'unit_price' => $originalItem->unit_price,
                        'line_total' => $splitQty * $originalItem->unit_price,
                        'status' => $originalItem->status,
                        'notes' => $originalItem->notes,
                    ]);
                }
            }

            // 3. Tính toán lại subtotal & total_amount cho cả 2 đơn
            $origSub = $order->items()->sum('line_total');
            $order->update([
                'subtotal' => $origSub,
                'total_amount' => max(0.0, $origSub - $order->discount_amount),
            ]);

            $newSub = $newOrder->items()->sum('line_total');
            $newOrder->update([
                'subtotal' => $newSub,
                'total_amount' => $newSub,
            ]);

            return $newOrder;
        });

        // 4. Ghi Audit Log cho cả 2 đơn
        \App\Models\AuditLog::log('order_split', 'created', $newOrder, null, [
            'total_amount' => (float) $newOrder->total_amount,
            'split_from_order_id' => $order->id
        ]);

        \App\Models\AuditLog::log('order_updated', 'updated', $order, [
            'total_amount' => $oldAmount
        ], [
            'total_amount' => (float) $order->total_amount
        ]);

        return back()->with('success', 'Đã tách đơn hàng ra bàn trống thành công. Giao dịch này đã được đánh dấu đỏ cảnh báo.');
    }

    /**
     * Phê duyệt đối soát đơn bị tách để xóa khoản phạt âm tiền (Chỉ Owner).
     */
    public function overrideSplitPenalty(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless((bool) $order->is_split, 422);

        $order->update([
            'is_override_split_penalty' => true,
            'is_red_flagged' => false, // Gỡ đánh dấu đỏ
        ]);

        \App\Models\AuditLog::log('order_split_override', 'updated', $order, [
            'is_override_split_penalty' => false
        ], [
            'is_override_split_penalty' => true
        ]);

        return back()->with('success', 'Đã phê duyệt đối soát đơn tách. Khoản phạt âm tiền của ca làm việc đã được vô hiệu hóa.');
    }

    /**
     * Sửa đổi thông tin đơn hàng / giá món / áp dụng giảm giá (Cashier / Manager).
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);

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

        $restaurantId = $order->restaurant_id;
        $isLocked = $order->status !== 'pending';

        // Order Locking: If locked, cannot delete items or decrease quantity of existing items
        if ($isLocked && isset($data['items'])) {
            $payloadItemIds = collect($data['items'])->pluck('id')->filter()->toArray();
            $dbItems = $order->items()->where('status', '!=', 'cancelled')->get();

            foreach ($dbItems as $dbItem) {
                // If an existing item is missing from the payload, it means it is deleted
                if (!in_array($dbItem->id, $payloadItemIds)) {
                    return back()->withErrors(['items' => 'Đơn hàng đã được gửi xuống bếp và khóa. Bạn không thể xóa món ăn khỏi đơn.']);
                }

                // If quantity is decreased
                $payloadItem = collect($data['items'])->firstWhere('id', $dbItem->id);
                if ($payloadItem && (float) $payloadItem['quantity'] < (float) $dbItem->quantity) {
                    return back()->withErrors(['items' => 'Đơn hàng đã được gửi xuống bếp và khóa. Bạn không thể giảm số lượng món ăn.']);
                }
            }
        }

        $oldValues = [
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'unit_price' => (float) $item->unit_price, 'quantity' => (float) $item->quantity])->toArray(),
        ];

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data, $restaurantId) {
            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if (!empty($itemData['id'])) {
                        $item = \App\Models\OrderItem::where('order_id', $order->id)
                            ->findOrFail($itemData['id']);

                        $item->update([
                            'unit_price' => $itemData['unit_price'] ?? $item->unit_price,
                            'quantity' => $itemData['quantity'],
                            'line_total' => ($itemData['unit_price'] ?? $item->unit_price) * $itemData['quantity'],
                            'notes' => $itemData['notes'] ?? $item->notes,
                        ]);
                    } else {
                        // Thêm món mới tinh
                        $product = \App\Models\Product::where('restaurant_id', $restaurantId)
                            ->findOrFail($itemData['product_id']);

                        $unitPrice = $itemData['unit_price'] ?? (float) $product->price;
                        $lineTotal = $unitPrice * (float) $itemData['quantity'];

                        \App\Models\OrderItem::create([
                            'restaurant_id' => $restaurantId,
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => (float) $itemData['quantity'],
                            'unit_price' => $unitPrice,
                            'discount_amount' => 0,
                            'line_total' => $lineTotal,
                            'status' => 'pending',
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }
            }

            $subtotal = $order->items()->sum('line_total');
            $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : $order->discount_amount;

            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => max(0.0, $subtotal - $discount),
                'note' => isset($data['note']) ? $data['note'] : $order->note,
            ]);
        });

        $order->refresh();

        $newValues = [
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'unit_price' => (float) $item->unit_price, 'quantity' => (float) $item->quantity])->toArray(),
        ];

        $changed = false;

        if ($oldValues['discount_amount'] !== $newValues['discount_amount']) {
            $changed = true;
        }

        foreach ($oldValues['items'] as $index => $oldItem) {
            $newItem = collect($newValues['items'])->firstWhere('id', $oldItem['id']);
            if ($newItem && $oldItem['unit_price'] !== $newItem['unit_price']) {
                \App\Models\AuditLog::log('price_modified', 'updated', $order, [
                    'item_id' => $oldItem['id'],
                    'unit_price' => $oldItem['unit_price']
                ], [
                    'item_id' => $newItem['id'],
                    'unit_price' => $newItem['unit_price']
                ]);
                $changed = true;
            }
        }

        if ($changed || $oldValues['subtotal'] !== $newValues['subtotal']) {
            \App\Models\AuditLog::log('order_updated', 'updated', $order, $oldValues, $newValues);
        }

        event(new \App\Events\Kitchen\KitchenUpdated($order->restaurant_id));

        return back()->with('success', 'Đã cập nhật thông tin đơn hàng và ghi nhận nhật ký kiểm toán.');
    }

    /**
     * Xác nhận đơn hàng QR từ khách hàng và lấy gợi ý upselling AI.
     */
    public function confirmQr(Request $request, Order $order): \Illuminate\Http\JsonResponse
    {
        abort_if($order->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_if($order->status !== 'pending', 422, 'Đơn hàng này đã được xác nhận trước đó.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'cashier_user_id' => $request->user()->id,
            ]);

            if ($order->table_id) {
                \App\Models\RestaurantTable::where('id', $order->table_id)->update(['status' => 'occupied']);
            }

            \App\Models\AuditLog::log('order_confirmed', 'updated', $order, ['status' => 'pending'], ['status' => 'confirmed']);
        });

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
        abort_unless($user->hasRole('cashier') || $user->hasRole('owner') || $user->hasRole('manager'), 403);
        abort_if($order->restaurant_id !== $user->restaurant_id, 403);
        abort_if($order->payment_status === 'paid', 422, 'Đơn hàng này đã được thanh toán rồi.');

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,card,ewallet'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data, $user) {
            // 1. Tạo Payment record
            \App\Models\Payment::create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'processed_by' => $user->id,
                'payment_method' => $data['payment_method'],
                'status' => 'paid',
                'amount' => $order->total_amount,
                'cash_received' => $data['cash_received'] ?? $order->total_amount,
                'change_amount' => $data['change_amount'] ?? 0,
                'paid_at' => now(),
            ]);

            // 2. Trừ kho nguyên liệu
            $order->load(['items.product.recipes.ingredient.unit']);
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product && $product->track_inventory) {
                    foreach ($product->recipes as $recipe) {
                        $recipeQuantity = (float) $recipe->quantity;
                        $itemQuantity = (float) $item->quantity;
                        $wasteRate = (float) $recipe->waste_rate;

                        $totalUsed = ($recipeQuantity * $itemQuantity) * (1 + ($wasteRate / 100));

                        $inventory = \App\Models\Inventory::firstOrCreate([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                        ], [
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => $recipe->ingredient->average_cost ?? 0,
                        ]);

                        $oldQty = (float) $inventory->quantity_on_hand;
                        $oldTheoretical = (float) $inventory->theoretical_quantity;

                        $inventory->update([
                            'quantity_on_hand' => max(0.0, $oldQty - $totalUsed),
                            'theoretical_quantity' => max(0.0, $oldTheoretical - $totalUsed),
                        ]);

                        \App\Models\InventoryTransaction::create([
                            'restaurant_id' => $order->restaurant_id,
                            'branch_id' => $order->branch_id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'inventory_id' => $inventory->id,
                            'order_id' => $order->id,
                            'performed_by' => $user->id,
                            'type' => 'usage',
                            'direction' => 'out',
                            'quantity' => $totalUsed,
                            'unit_cost' => $recipe->ingredient->average_cost ?? 0,
                            'total_cost' => $totalUsed * ($recipe->ingredient->average_cost ?? 0),
                            'notes' => "Khấu hao nguyên vật liệu cho đơn hàng {$order->order_number} (Món: {$product->name})",
                            'occurred_at' => now(),
                        ]);

                        \App\Models\InventoryReservation::where('order_id', $order->id)
                            ->where('ingredient_id', $recipe->ingredient_id)
                            ->where('status', 'holding')
                            ->update(['status' => 'committed']);
                    }
                }
            }

            // 3. Cập nhật Order status thành completed & payment_status paid
            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'completed_at' => now(),
                'cashier_user_id' => $user->id,
            ]);

            // 4. Giải phóng bàn
            if ($order->table_id) {
                \App\Models\RestaurantTable::where('id', $order->table_id)->update(['status' => 'available']);
            }

            \App\Models\AuditLog::log('order_paid', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'paid']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thanh toán đơn hàng thành công!',
            ]);
        }

        return back()->with('success', 'Thanh toán đơn hàng thành công!');
    }

    public function toggleAutoPaySetting(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner'), 403);

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
