<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TemporaryOrder;
use App\Services\LoyaltyService;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPortalController extends Controller
{
    public function showDashboard(Request $request, $restaurantId, $phone): Response
    {
        $token = $request->query('token');
        $expectedToken = hash('sha256', $restaurantId.$phone.config('app.key'));
        if (! $token || ! hash_equals($expectedToken, $token)) {
            abort(403, 'Link truy cập không hợp lệ hoặc đã hết hạn.');
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->first();

        // Không auto-create hội viên từ URL công khai (chống spam/bịa số điện thoại).
        // Link portal chỉ được phát cho khách đã có hồ sơ (sau mua hàng / tích điểm).
        if (! $customer) {
            abort(404, 'Số điện thoại này chưa có hồ sơ hội viên tại nhà hàng.');
        }

        // Lịch sử đơn liên kết qua customer_id (bảng orders không có cột customer_phone).
        $orders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('customer_id', $customer->id)
            ->with('items.product')
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float) $o->total_amount,
                'created_at' => $o->created_at->toIso8601String(),
                'items' => $o->items->map(fn ($i) => [
                    'name' => $i->product?->name ?? 'Món ăn',
                    'quantity' => (float) $i->quantity,
                    'price' => (float) $i->unit_price,
                ]),
            ]);

        // Get loyalty rewards from catalog
        $rewards = LoyaltyReward::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->available()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'type' => $r->type,
                'value' => (float) $r->value,
                'points_cost' => (int) $r->points_cost,
                'image_url' => $r->image_url,
            ]);

        // Loyalty tier info
        $tier = $customer->loyaltyTier;
        $allTiers = LoyaltyTier::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->ordered()
            ->get(['name', 'slug', 'min_spent', 'discount_percent', 'color', 'benefits_json']);

        $nextTier = $allTiers->first(fn ($t) => (float) $t->min_spent > (float) ($customer->total_spent ?? 0));

        // Recent loyalty transactions
        $loyaltyHistory = $customer->loyaltyTransactions()
            ->latest()
            ->take(10)
            ->get(['type', 'points', 'balance_after', 'description', 'created_at']);

        // Loyalty QR card
        $loyaltyQrSvg = null;
        $program = LoyaltyProgram::withoutGlobalScopes()->where('restaurant_id', $restaurantId)->first();
        if ($program && $program->enable_qr_card) {
            try {
                $loyaltyQrSvg = app(LoyaltyService::class)->generateLoyaltyQr($customer);
            } catch (\Throwable) {
            }
        }

        return Inertia::render('customers/Dashboard', [
            'restaurant' => [
                'id' => (int) $restaurantId,
                'name' => Restaurant::find($restaurantId)?->name ?? 'Nhà hàng',
            ],
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'membership_level' => $customer->membership_level,
                'loyalty_points' => (int) $customer->loyalty_points,
                'total_spent' => (float) ($customer->total_spent ?? 0),
                'tier' => $tier ? ['name' => $tier->name, 'color' => $tier->color, 'discount_percent' => (float) $tier->discount_percent] : null,
                'next_tier' => $nextTier ? ['name' => $nextTier->name, 'min_spent' => (float) $nextTier->min_spent] : null,
            ],
            'orders' => $orders,
            'rewards' => $rewards,
            'loyaltyHistory' => $loyaltyHistory,
            'loyaltyQrSvg' => $loyaltyQrSvg,
            'tiers' => $allTiers,
        ]);
    }

    /**
     * Gửi link truy cập cổng hội viên (đã ký) tới SĐT của khách qua SMS/Zalo.
     * Thay cho endpoint phát token công khai cũ (lỗ hổng: ai cũng lấy được token
     * để xem lịch sử đơn + điểm của người khác). Chỉ chủ SĐT nhận được link.
     * Luôn trả về thông báo chung, không tiết lộ SĐT có phải hội viên hay không.
     */
    public function requestAccessLink(Request $request, $restaurantId): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $customer = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('phone', $data['phone'])
            ->first();

        if ($customer) {
            $token = hash('sha256', $restaurantId.$customer->phone.config('app.key'));
            $url = url("/customer/portal/dashboard/{$restaurantId}/{$customer->phone}?token={$token}");
            app(SmsService::class)
                ->send($customer->phone, "Aventura: Link cổng hội viên của bạn: {$url}");
        }

        return response()->json([
            'message' => 'Nếu số điện thoại đã là hội viên, link truy cập đã được gửi qua SMS/Zalo của bạn.',
        ]);
    }

    public function redeemReward(Request $request, $restaurantId, $phone): JsonResponse
    {
        $token = $request->query('token') ?? $request->input('token');
        $expectedToken = hash('sha256', $restaurantId.$phone.config('app.key'));
        if (! $token || ! hash_equals($expectedToken, $token)) {
            abort(403, 'Link truy cập không hợp lệ hoặc đã hết hạn.');
        }

        $data = $request->validate([
            'reward_id' => ['required', 'integer'],
        ]);

        $customer = Customer::where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->firstOrFail();

        $reward = LoyaltyReward::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['reward_id']);

        try {
            $result = app(LoyaltyService::class)->redeemReward($customer, $reward);

            return response()->json([
                'success' => true,
                'message' => "Đổi thưởng thành công: {$result['reward']}!",
                'new_points' => $customer->fresh()->loyalty_points,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }
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
                'phone' => $data['customer_phone'],
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

        if (! empty($data['items'])) {
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
                        'notes' => 'Đặt trước lúc '.$data['reservation_time'],
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
            'notes' => 'Đặt bàn trước lúc: '.$data['reservation_time'].' | Số khách: '.$data['guests_count'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đặt bàn và gọi món trước thành công! Nhân viên sẽ liên hệ lại xác nhận sớm nhất.',
        ]);
    }
}
