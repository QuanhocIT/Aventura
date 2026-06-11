<?php

namespace App\Http\Controllers;

use App\Events\QrOrderPlaced;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QrOrderController extends Controller
{
    /**
     * Renders giao diện thực đơn gọi món tại bàn cho khách hàng.
     */
    public function showMenu(int $restaurantId, string $tableToken): Response
    {
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
            ->where('status', 'active')
            ->where('is_active', true)
            ->where('is_available', true)
            ->with(['category'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'sku' => $p->sku ?? '—',
                'description' => $p->description,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
            ]);

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
        ]);
    }

    /**
     * Khách hàng gửi đơn hàng đệm lên hệ thống.
     */
    public function submitOrder(Request $request, int $restaurantId, string $tableToken): JsonResponse
    {
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
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = $products->get($itemData['product_id'])
                    ?? abort(422, 'Sản phẩm không tồn tại hoặc không hoạt động.');

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

            foreach ($itemsToCreate as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
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
            ],
        ]);
    }
}
