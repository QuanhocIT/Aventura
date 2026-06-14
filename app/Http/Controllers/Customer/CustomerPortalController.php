<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\TemporaryOrder;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function showDashboard($restaurantId, $phone): Response
    {
        $customer = Customer::where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->first();

        if (!$customer) {
            $customer = Customer::create([
                'restaurant_id' => $restaurantId,
                'phone' => $phone,
                'full_name' => 'Hội viên mới',
                'membership_level' => 'silver',
                'loyalty_points' => 0,
            ]);
        }

        // Get past orders for this customer phone
        $orders = Order::where('restaurant_id', $restaurantId)
            ->where('customer_phone', $phone)
            ->with('items.product')
            ->latest()
            ->take(15)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float)$o->total_amount,
                'created_at' => $o->created_at->toIso8601String(),
                'items' => $o->items->map(fn($i) => [
                    'name' => $i->product?->name ?? 'Món ăn',
                    'quantity' => (float)$i->quantity,
                    'price' => (float)$i->unit_price,
                ]),
            ]);

        // Get redeemable items list
        $rewards = Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float)$p->price,
                'image_url' => $p->image_url,
                'points_required' => (int)max(10, round($p->price / 1000)), // 1,000đ = 1 point
            ]);

        return Inertia::render('customers/Dashboard', [
            'restaurant' => [
                'id' => (int)$restaurantId,
                'name' => \App\Models\Restaurant::find($restaurantId)?->name ?? 'Nhà hàng',
            ],
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'membership_level' => $customer->membership_level,
                'loyalty_points' => (int)$customer->loyalty_points,
            ],
            'orders' => $orders,
            'rewards' => $rewards,
        ]);
    }

    public function redeemReward(Request $request, $restaurantId, $phone): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'points' => ['required', 'integer', 'min:1'],
        ]);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->firstOrFail();

        if ($customer->loyalty_points < $data['points']) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không đủ điểm tích lũy để thực hiện đổi phần thưởng này.',
            ], 422);
        }

        // Subtract points
        $customer->decrement('loyalty_points', $data['points']);

        return response()->json([
            'success' => true,
            'message' => 'Đổi thưởng thành công! Ưu đãi đã được lưu lại.',
            'new_points' => $customer->loyalty_points,
        ]);
    }

    public function createReservation(Request $request, $restaurantId): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'reservation_time' => ['required', 'string'],
            'guests_count' => ['required', 'integer', 'min:1'],
            'items' => ['nullable', 'array'],
        ]);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            [
                'restaurant_id' => $restaurantId,
                'phone' => $data['customer_phone']
            ],
            [
                'full_name' => $data['customer_name'],
            ]
        );

        $table = RestaurantTable::where('restaurant_id', $restaurantId)
            ->where('capacity', '>=', $data['guests_count'])
            ->first();

        $cartData = [];
        $totalAmount = 0.0;

        if (!empty($data['items'])) {
            $productIds = collect($data['items'])->pluck('product_id')->toArray();
            $products = Product::where('restaurant_id', $restaurantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if ($product) {
                    $lineTotal = (float) $product->price * (float) $item['quantity'];
                    $totalAmount += $lineTotal;

                    $cartData[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => (float) $item['quantity'],
                        'unit_price' => (float) $product->price,
                        'notes' => 'Đặt trước lúc ' . $data['reservation_time'],
                        'line_total' => $lineTotal,
                    ];
                }
            }
        }

        TemporaryOrder::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $table ? $table->branch_id : null,
            'table_id' => $table ? $table->id : null,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'status' => 'waiting_verification',
            'cart_data' => $cartData,
            'total_amount' => $totalAmount,
            'notes' => 'Đặt bàn trước lúc: ' . $data['reservation_time'] . ' | Số khách: ' . $data['guests_count'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đặt bàn và gọi món trước thành công! Nhân viên sẽ liên hệ lại xác nhận sớm nhất.',
        ]);
    }
}
