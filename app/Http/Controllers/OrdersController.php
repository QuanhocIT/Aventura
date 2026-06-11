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

        // Auto-generate 20 tables in 1 area if there are no tables for this restaurant
        $tableCount = \App\Models\RestaurantTable::where('restaurant_id', $restaurantId)->count();
        if ($tableCount === 0) {
            $branchId = $user->branch_id;
            $area = \App\Models\Area::firstOrCreate([
                'restaurant_id' => $restaurantId,
                'name' => 'Khu vực chính',
            ], [
                'branch_id' => $branchId,
                'code' => 'khu-vuc-chinh',
                'display_order' => 1,
                'status' => 'active',
            ]);

            $names = [];
            $letters = ['A', 'B', 'C', 'D'];
            foreach ($letters as $letter) {
                for ($i = 1; $i <= 5; $i++) {
                    $names[] = $letter . $i;
                }
            }

            foreach ($names as $name) {
                \App\Models\RestaurantTable::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'area_id' => $area->id,
                    'name' => $name,
                    'capacity' => 4,
                    'status' => 'available',
                    'qr_token' => \Illuminate\Support\Str::random(32),
                ]);
            }
        }

        $tables = \App\Models\RestaurantTable::where('restaurant_id', $restaurantId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'capacity' => $t->capacity,
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
 
             // Ghi log kiểm toán
             \App\Models\AuditLog::log('order_created', 'created', $order, null, [
                 'total_amount' => (float) $order->total_amount,
                 'items_count' => count($itemsToCreate)
             ]);
         });
 
         return redirect()->route('orders.index')->with('success', 'Đã gửi đơn hàng mới xuống nhà bếp thành công!');
     }
 
     public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $statusFilter = $request->get('status', 'all');
        $dateFilter   = $request->get('date', today()->toDateString());

        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['table.area', 'items.product'])
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
            'items'          => $o->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'quantity' => (float) $item->quantity,
                'notes' => $item->notes,
            ])->toArray(),
        ]);

        $summary = [
            'total'     => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->count(),
            'pending'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'pending')->count(),
            'preparing' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'preparing')->count(),
            'completed' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->count(),
            'cancelled' => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'cancelled')->count(),
            'revenue'   => Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $dateFilter)->where('status', 'completed')->sum('total_amount'),
        ];

        return Inertia::render('orders/Index', [
            'orders'        => $orders,
            'summary'       => $summary,
            'filters'       => ['status' => $statusFilter, 'date' => $dateFilter],
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
        });

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
            'items.*.id' => ['required', 'exists:order_items,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Fraud prevention: Lock order once sent to kitchen (status is not pending)
        if ($order->status !== 'pending' && !$request->user()->hasRole('owner')) {
            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $item = \App\Models\OrderItem::where('order_id', $order->id)
                        ->find($itemData['id']);

                    if ($item && (float) $itemData['quantity'] < (float) $item->quantity) {
                        return back()->withErrors([
                            'items' => 'Đơn hàng đã được chuyển bếp và khóa. Bạn không thể xóa hoặc giảm số lượng món ăn, chỉ có thể tăng thêm.'
                        ]);
                    }
                }
            }
        }

        $oldValues = [
            'subtotal' => (float) $order->subtotal,
            'discount_amount' => (float) $order->discount_amount,
            'total_amount' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($item) => ['id' => $item->id, 'unit_price' => (float) $item->unit_price, 'quantity' => (float) $item->quantity])->toArray(),
        ];

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $data) {
            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $item = \App\Models\OrderItem::where('order_id', $order->id)
                        ->findOrFail($itemData['id']);

                    $item->update([
                        'unit_price' => $itemData['unit_price'],
                        'quantity' => $itemData['quantity'],
                        'line_total' => $itemData['unit_price'] * $itemData['quantity'],
                    ]);
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

        // 1. Áp mã giảm giá được xử lý tự động và bất đồng bộ qua OrderObserver & Redis Queue
        if ($oldValues['discount_amount'] !== $newValues['discount_amount']) {
            $changed = true;
        }

        // 2. Sửa đổi giá sản phẩm
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

        // 3. Sửa đơn tổng quát
        if ($changed || $oldValues['subtotal'] !== $newValues['subtotal']) {
            \App\Models\AuditLog::log('order_updated', 'updated', $order, $oldValues, $newValues);
        }

        return back()->with('success', 'Đã cập nhật thông tin đơn hàng và ghi nhận nhật ký kiểm toán.');
    }
}
