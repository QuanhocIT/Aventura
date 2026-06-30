<?php

namespace App\Http\Controllers;

use App\Events\QrOrderPlaced;
use App\Events\TableInteraction;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;
use App\Models\InventoryReservation;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class QrOrderController extends Controller
{
    /**
     * Renders giao diện thực đơn gọi món tại bàn cho khách hàng.
     */
    public function showMenu(int $restaurantId, string $tableToken): Response
    {
        // Thiết lập TenantContext để đảm bảo các truy vấn scope hoạt động chính xác
        app(TenantContext::class)->setRestaurantId($restaurantId);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('qr_token', $tableToken)
            ->whereNull('deleted_at')
            ->first();

        if (! $table) {
            abort(404, 'Bàn ăn không tồn tại hoặc mã QR đã hết hạn.');
        }

        $categories = ProductCategory::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->orderBy('display_order', 'asc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $products = Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with(['category', 'recipes.ingredient'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'sku' => $p->sku ?? '—',
                'description' => $p->description,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
                'is_available' => (bool) ($p->is_available && $p->checkStockAtBranch($table->branch_id)),
                'is_out_of_stock' => (bool) (!($p->is_available && $p->checkStockAtBranch($table->branch_id))),
                'ingredients' => $p->recipes->map(fn ($r) => $r->ingredient?->name)->filter()->values()->toArray(),
            ]);

        // Lấy danh sách đơn hàng đang hoạt động của bàn này
        $activeOrders = Order::where('restaurant_id', $restaurantId)
            ->where('table_id', $table->id)
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->with(['items.product'])
            ->latest()
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float) $o->total_amount,
                'created_at' => $o->created_at->format('H:i'),
                'items' => $o->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'status' => $item->status,
                    'notes' => $item->notes,
                ])->toArray(),
            ])->toArray();

        // Lấy danh sách nhân viên đang làm việc trong ca trực hiện tại
        $now = now();
        $orderTimeStr = $now->toTimeString();
        $orderDate = $now->toDateString();

        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $activeShift = null;
        foreach ($shifts as $shift) {
            $inShift = false;
            if (! $shift->is_overnight) {
                $inShift = $orderTimeStr >= $shift->start_time && $orderTimeStr <= $shift->end_time;
            } else {
                if ($shift->start_time > $shift->end_time) {
                    $inShift = $orderTimeStr >= $shift->start_time || $orderTimeStr <= $shift->end_time;
                } else {
                    $inShift = $orderTimeStr >= $shift->start_time && $orderTimeStr <= $shift->end_time;
                }
            }

            if ($inShift) {
                $activeShift = $shift;
                break;
            }
        }

        $onDutyStaff = [];
        if ($activeShift) {
            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereDate('scheduled_date', $orderDate)
                ->where('shift_id', $activeShift->id)
                ->with(['employee.user'])
                ->get();

            foreach ($assignments as $asm) {
                if ($asm->employee && $asm->employee->user) {
                    $onDutyStaff[] = [
                        'employee_id' => $asm->employee->id,
                        'name' => $asm->employee->user->name,
                        'role' => $asm->employee->role?->name ?? 'Nhân viên',
                    ];
                }
            }
        }

        // Nếu không có ca trực nào phù hợp hoặc không có ai được xếp lịch ca trực đó, lấy tất cả ca trực của ngày hôm đó
        if (empty($onDutyStaff)) {
            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereDate('scheduled_date', $orderDate)
                ->with(['employee.user'])
                ->get();

            foreach ($assignments as $asm) {
                if ($asm->employee && $asm->employee->user) {
                    $onDutyStaff[] = [
                        'employee_id' => $asm->employee->id,
                        'name' => $asm->employee->user->name,
                        'role' => $asm->employee->role?->name ?? 'Nhân viên',
                    ];
                }
            }
        }

        return Inertia::render('orders/QrOrder', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
            ],
            'table' => [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => $table->capacity,
            ],
            'products' => $products,
            'categories' => $categories,
            'initialActiveOrders' => $activeOrders,
            'onDutyStaff' => $onDutyStaff,
        ]);
    }

    /**
     * Khách hàng gửi đơn hàng đệm lên hệ thống.
     */
    public function submitOrder(Request $request, int $restaurantId, string $tableToken): JsonResponse
    {
        // Thiết lập TenantContext
        app(TenantContext::class)->setRestaurantId($restaurantId);

        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('qr_token', $tableToken)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $order = DB::transaction(function () use ($restaurantId, $table, $data) {
            $orderNumber = 'ORD-QR-'.strtoupper(uniqid());

            $productIds = array_column($data['items'], 'product_id');
            $products = Product::where('restaurant_id', $restaurantId)
                ->whereIn('id', $productIds)
                ->with(['recipes.ingredient'])
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $itemsToCreate = [];

            // 1. Kiểm tra kho cho từng sản phẩm trước khi tạo đơn hàng
            foreach ($data['items'] as $itemData) {
                $product = $products->get($itemData['product_id'])
                    ?? abort(422, 'Sản phẩm không tồn tại hoặc không hoạt động.');

                if (!$product->is_available || !$product->checkStockAtBranch($table->branch_id)) {
                    abort(422, "Món ăn '{$product->name}' hiện đã hết hàng. Vui lòng chọn món khác.");
                }

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

            // 2. Tạo đơn hàng
            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $table->branch_id,
                'table_id' => $table->id,
                'order_number' => $orderNumber,
                'channel' => 'qr',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $subtotal,
                'note' => $data['note'] ?? null,
            ]);

            // 3. Tạo món ăn trong đơn & Đăng ký giữ kho đệm (InventoryReservation)
            foreach ($itemsToCreate as $item) {
                $item['order_id'] = $order->id;
                $orderItem = OrderItem::create($item);

                $product = $products->get($orderItem->product_id);
                if ($product && $product->track_inventory) {
                    foreach ($product->recipes as $recipe) {
                        $totalUsed = ((float) $recipe->quantity * (float) $orderItem->quantity) * (1 + ((float) $recipe->waste_rate / 100));

                        InventoryReservation::create([
                            'restaurant_id' => $restaurantId,
                            'order_id' => $order->id,
                            'ingredient_id' => $recipe->ingredient_id,
                            'reserved_quantity' => $totalUsed,
                            'status' => 'holding',
                            'expires_at' => now()->addHours(2),
                        ]);
                    }
                }
            }

            // Ghi nhật ký kiểm toán
            AuditLog::log('order_created', 'created', $order, null, [
                'total_amount' => (float) $order->total_amount,
                'items_count' => count($itemsToCreate),
                'channel' => 'qr',
            ]);

            return $order;
        });

        // Kích hoạt phát thông tin real-time cho máy POS/Tablet của nhân viên
        event(new QrOrderPlaced($order->load('table')));

        return response()->json([
            'success' => true,
            'message' => 'Đặt món thành công! Vui lòng chờ nhân viên di chuyển tới bàn xác nhận.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => (float) $order->total_amount,
                'created_at' => $order->created_at->format('H:i'),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'quantity' => (float) $item->quantity,
                    'status' => $item->status,
                    'notes' => $item->notes,
                ])->toArray(),
            ],
        ]);
    }

    /**
     * Khách hàng gửi yêu cầu gọi nhân viên.
     */
    public function callStaff(int $restaurantId, string $tableToken): JsonResponse
    {
        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('qr_token', $tableToken)
            ->whereNull('deleted_at')
            ->firstOrFail();

        event(new \App\Events\TableInteraction(
            $table,
            'call_staff',
            "Bàn {$table->name} yêu cầu gọi nhân viên."
        ));

        // Lưu nhật ký kiểm toán
        AuditLog::log('table_call_staff', 'created', $table, null, [
            'type' => 'call_staff',
            'message' => "Bàn {$table->name} yêu cầu gọi nhân viên.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu gọi nhân viên thành công!',
        ]);
    }

    /**
     * Khách hàng gửi yêu cầu thanh toán hóa đơn.
     */
    public function requestPayment(int $restaurantId, string $tableToken): JsonResponse
    {
        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('qr_token', $tableToken)
            ->whereNull('deleted_at')
            ->firstOrFail();

        event(new \App\Events\TableInteraction(
            $table,
            'request_payment',
            "Bàn {$table->name} yêu cầu thanh toán hóa đơn."
        ));

        // Lưu nhật ký kiểm toán
        AuditLog::log('table_request_payment', 'created', $table, null, [
            'type' => 'request_payment',
            'message' => "Bàn {$table->name} yêu cầu thanh toán hóa đơn.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu thanh toán thành công! Nhân viên sẽ mang hóa đơn tới bàn của bạn.',
        ]);
    }
}

