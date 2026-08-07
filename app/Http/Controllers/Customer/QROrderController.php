<?php

namespace App\Http\Controllers\Customer;

use App\Events\Customer\FeedbackSubmitted;
use App\Events\Customer\PaymentRequested;
use App\Events\Customer\StaffCalled;
use App\Events\Customer\TemporaryOrderCreated;
use App\Http\Controllers\Controller;
use App\Jobs\VerifyTemporaryOrderDelayJob;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\TemporaryOrder;
use App\Models\WorkShift;
use App\Services\CdpService;
use App\Services\InventoryAvailabilityService;
use App\Support\TenantRule;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QROrderController extends Controller
{
    /**
     * Hiển thị giao diện khách hàng gọi món tại bàn qua QR code.
     */
    public function showMenu($restaurantId, $qrToken): Response
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('qr_token', $qrToken)
            ->with(['area', 'branch'])
            ->firstOrFail();

        $restaurant = Restaurant::findOrFail($restaurantId);

        // 1. Lấy danh mục sản phẩm hoạt động
        $categories = ProductCategory::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->orderBy('display_order')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->toArray();

        // 2. Lấy danh sách sản phẩm hoạt động kèm công thức định lượng
        $productsRaw = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->sellableMenu()
            ->with(['category', 'recipes.ingredient'])
            ->get();

        // 3. Tính số suất có thể phục vụ từ các lô còn hạn, sau khi trừ phần đã giữ.
        $availabilityService = app(InventoryAvailabilityService::class);
        $availabilityService->refreshBranch((int) $restaurantId, (int) $table->branch_id, false);
        $availability = $availabilityService->forProducts($productsRaw, (int) $restaurantId, (int) $table->branch_id);

        $products = $productsRaw->map(function ($p) use ($availability) {
            $stock = $availability->get($p->id, []);
            $isInventorySoldOut = (bool) ($stock['is_sold_out'] ?? false);
            $isKitchenPaused = $p->paused_until && $p->paused_until->isFuture();
            $isKitchenOutOfStock = $p->out_of_stock_until && $p->out_of_stock_until->isFuture();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => (float) $p->price,
                'earn_points' => (int) ($p->earn_points ?? 0),
                'redeem_points' => (int) ($p->redeem_points ?? 0),
                'image_url' => $p->image_url,
                'sku' => $p->sku,
                'category_id' => $p->category_id,
                'in_stock' => ! $isInventorySoldOut && $p->is_available && ! $isKitchenPaused && ! $isKitchenOutOfStock,
                'available_portions' => $stock['available_portions'] ?? null,
                'is_inventory_sold_out' => $isInventorySoldOut,
                'inventory_bottleneck_ingredient' => $stock['bottleneck_ingredient_name'] ?? null,
                'paused_until' => $p->paused_until ? $p->paused_until->toIso8601String() : null,
                'out_of_stock_until' => $p->out_of_stock_until ? $p->out_of_stock_until->toIso8601String() : null,
                'is_kitchen_paused' => $isKitchenPaused,
                'is_kitchen_out_of_stock' => $isKitchenOutOfStock,
            ];
        });

        // 3.5. CDP Personalization: Get recommended items based on customer phone order history, fallback to top-selling items
        $customerPhone = request()->get('phone');
        $recommendedProducts = collect();
        if ($customerPhone) {
            // orders không có cột customer_phone — tra Customer theo phone rồi lọc theo customer_id.
            $customerId = Customer::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('phone', $customerPhone)
                ->value('id');

            $frequentProductIds = $customerId
                ? DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.restaurant_id', $restaurantId)
                    ->where('orders.customer_id', $customerId)
                    ->select('order_items.product_id', DB::raw('COUNT(*) as order_count'))
                    ->groupBy('order_items.product_id')
                    ->orderByDesc('order_count')
                    ->limit(4)
                    ->pluck('product_id')
                    ->toArray()
                : [];

            if (! empty($frequentProductIds)) {
                $recommendedProducts = $products->whereIn('id', $frequentProductIds)->values();
            }
        }

        if ($recommendedProducts->count() < 2) {
            $topProductIds = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.restaurant_id', $restaurantId)
                ->select('order_items.product_id', DB::raw('COUNT(*) as total_count'))
                ->groupBy('order_items.product_id')
                ->orderByDesc('total_count')
                ->limit(4)
                ->pluck('product_id')
                ->toArray();

            $additionalProducts = $products->whereIn('id', $topProductIds)
                ->whereNotIn('id', $recommendedProducts->pluck('id')->toArray())
                ->values();

            $recommendedProducts = $recommendedProducts->concat($additionalProducts)->take(4);
        }

        // Duplicate recommended products with category_id = 999999 for rendering under recommendation tab
        $recProductsDuplicated = $recommendedProducts->map(function ($p) {
            $duplicated = $p;
            $duplicated['category_id'] = 999999;

            return $duplicated;
        });

        // Prepend Recommendation Category
        if ($recommendedProducts->isNotEmpty()) {
            array_unshift($categories, [
                'id' => 999999,
                'name' => '⭐ Gợi ý cho bạn',
                'slug' => 'goi-y-cho-ban',
            ]);
            $products = $products->concat($recProductsDuplicated);
        }

        // 4. Lấy các đơn hàng tạm thời hoặc đơn hàng chính thức đang active tại bàn này
        $activeTempOrders = TemporaryOrder::withoutGlobalScopes()
            ->where('table_id', $table->id)
            ->whereIn('status', ['waiting_verification', 'escalated', 'confirmed'])
            ->with(['order.items.product'])
            ->latest()
            ->get()
            ->map(function ($to) {
                // Nếu đã confirm, load thêm trạng thái nấu nướng chi tiết của các món ăn
                $itemsStatus = [];
                if ($to->status === 'confirmed' && $to->order) {
                    // Trạng thái đơn: pending/confirmed/preparing -> Bếp đang chế biến, completed/served -> Đã lên món
                    foreach ($to->order->items as $item) {
                        $itemsStatus[] = [
                            'name' => $item->product?->name ?? 'Món ăn',
                            'quantity' => (float) $item->quantity,
                            'status' => $item->status, // pending, sent, preparing, served, cancelled
                        ];
                    }
                }

                return [
                    'id' => $to->id,
                    'status' => $to->status,
                    'total_amount' => (float) $to->total_amount,
                    'cart_data' => $to->cart_data,
                    'order_id' => $to->order_id,
                    'order_number' => $to->order?->order_number,
                    'order_status' => $to->order?->status,
                    'payment_status' => $to->order?->payment_status,
                    'items_status' => $itemsStatus,
                    'created_at' => $to->created_at->toIso8601String(),
                ];
            });

        // 5. Lấy danh sách nhân viên phục vụ trong ca trực hiện tại để khách hàng đánh giá
        $staffList = $this->resolveCurrentShiftStaff($restaurantId);

        $currentCustomer = null;
        $authUser = Auth::user();
        if ($authUser && $authUser->phone) {
            $customerObj = Customer::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('phone', $authUser->phone)
                ->first();
            if ($customerObj) {
                $currentCustomer = [
                    'id' => $customerObj->id,
                    'full_name' => $customerObj->full_name,
                    'phone' => $customerObj->phone,
                    'loyalty_points' => (int) $customerObj->loyalty_points,
                ];
            }
        }

        return Inertia::render('customers/QROrder', [
            'customer' => $currentCustomer,
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'logo_url' => $restaurant->logo_url,
            ],
            'table' => [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'area_id' => $table->area_id,
                'area_name' => $table->area?->name,
                'qr_token' => $table->qr_token,
            ],
            'categories' => $categories,
            'products' => $products,
            'activeTempOrders' => $activeTempOrders,
            'staffList' => $staffList,
        ]);
    }

    /**
     * Khách hàng gửi đơn hàng đệm (Self-ordering request).
     */
    public function submitOrder(Request $request, $restaurantId, $qrToken): JsonResponse
    {
        $table = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', TenantRule::exists('products', restaurantId: (int) $restaurantId)],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Tính tổng tiền và xác thực stock
        $totalAmount = 0.0;
        $cartData = [];

        // Load sản phẩm để đối chiếu giá và tên
        $productIds = collect($data['items'])->pluck('product_id')->toArray();
        $products = Product::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $productIds)
            ->sellableMenu()
            ->with('recipes.ingredient.unit')
            ->get()
            ->keyBy('id');

        try {
            app(InventoryAvailabilityService::class)->assertItemsAvailable(
                (int) $restaurantId,
                (int) $table->branch_id,
                $data['items'],
            );
        } catch (\RuntimeException $exception) {
            $firstProduct = $products->get((int) ($data['items'][0]['product_id'] ?? 0));
            return response()->json([
                'message' => $firstProduct
                    ? "Món '{$firstProduct->name}' đã hết nguyên liệu chế biến."
                    : $exception->getMessage(),
            ], 422);
        }

        foreach ($data['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! $product->is_active || ! $product->is_available) {
                return response()->json(['message' => "Món ăn {$product?->name} không còn phục vụ."], 422);
            }

            $isKitchenPaused = $product->paused_until && $product->paused_until->isFuture();
            $isKitchenOutOfStock = $product->out_of_stock_until && $product->out_of_stock_until->isFuture();

            if ($isKitchenPaused || $isKitchenOutOfStock) {
                return response()->json(['message' => "Món ăn {$product->name} tạm thời ngừng phục vụ."], 422);
            }

            $lineTotal = (float) $product->price * (float) $item['quantity'];
            $totalAmount += $lineTotal;

            $cartData[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $product->price,
                'notes' => $item['notes'] ?? null,
                'line_total' => $lineTotal,
            ];
        }

        $customerId = null;
        $customer = null;
        if (! empty($data['customer_phone'])) {
            try {
                $customer = Customer::firstOrCreate(
                    [
                        'restaurant_id' => $restaurantId,
                        'phone' => $data['customer_phone'],
                    ],
                    [
                        'full_name' => $data['customer_name'] ?: 'Khách gọi món QR',
                        'branch_id' => $table->branch_id,
                    ]
                );
            } catch (QueryException $e) {
                // In case of concurrent creation, retrieve the existing record
                $customer = Customer::where('restaurant_id', $restaurantId)
                    ->where('phone', $data['customer_phone'])
                    ->first();
                if (! $customer) {
                    throw $e;
                }
            }
            $customerId = $customer->id;

            if ($data['customer_name'] && ($customer->full_name === 'Khách gọi món QR' || empty($customer->full_name))) {
                $customer->update(['full_name' => $data['customer_name']]);
            }
        }

        // Apply loyalty points discount (1 point = 100đ)
        $redeemPoints = (int) ($data['redeem_points'] ?? 0);
        $pointsDiscount = 0.0;
        $actualRedeemPoints = 0;
        if ($customer && $redeemPoints > 0) {
            // Chỉ cho phép quy đổi tối đa số điểm khách đang có (pre-check)
            $actualRedeemPoints = min($redeemPoints, $customer->loyalty_points);
            $pointsDiscount = $actualRedeemPoints * 100.0;
        }

        $finalAmount = max(0.0, $totalAmount - $pointsDiscount);

        // Tạo bản ghi đơn hàng đệm
        $tempOrder = TemporaryOrder::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $table->branch_id,
            'table_id' => $table->id,
            'customer_name' => $data['customer_name'] ?? 'Khách tại bàn',
            'customer_phone' => $data['customer_phone'] ?? null,
            'status' => 'waiting_verification',
            'cart_data' => $cartData,
            'total_amount' => $finalAmount,
            // temporary_orders không có cột notes; thông tin điểm đã nằm trong redeem_points.
            'redeem_points' => $actualRedeemPoints,
        ]);

        // Ghi nhận hành vi gửi đơn hàng và liên kết lịch sử
        $sessionId = $data['session_id'] ?? 'unknown_session';
        CdpService::logBehavior(
            $restaurantId,
            $sessionId,
            'submit_order',
            null,
            null,
            ['temporary_order_id' => $tempOrder->id, 'amount' => $totalAmount],
            $customerId
        );

        // Kích hoạt Event thông báo Realtime (< 500ms) trên màn hình máy POS/Tablet của Staff
        event(new TemporaryOrderCreated($tempOrder));

        // Khởi tạo Delay Job trong queue với TTL là 2 phút (120 giây) để theo dõi lùi bước
        VerifyTemporaryOrderDelayJob::dispatch($tempOrder->id)->delay(now()->addSeconds(120));

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu gọi món thành công, đang chờ nhân viên xác thực tại bàn!',
            'temporary_order' => [
                'id' => $tempOrder->id,
                'status' => $tempOrder->status,
                'total_amount' => $tempOrder->total_amount,
                'cart_data' => $tempOrder->cart_data,
                'created_at' => $tempOrder->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Khách hàng bấm nút "Gọi nhân viên".
     */
    public function callStaff(Request $request, $restaurantId): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', TenantRule::exists('restaurant_tables', restaurantId: (int) $restaurantId)],
            'message' => ['nullable', 'string', 'max:100'],
        ]);

        $table = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('id', $data['table_id'])
            ->with(['area'])
            ->firstOrFail();

        $msg = $data['message'] ?: 'Khách hàng yêu cầu phục vụ tại bàn';

        event(new StaffCalled($restaurantId, $table->name, $table->area?->name ?? 'Khu vực', $msg));

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu hỗ trợ, nhân viên sẽ tới bàn của bạn ngay!',
        ]);
    }

    /**
     * Khách hàng bấm nút "Yêu cầu thanh toán".
     */
    public function paymentRequest(Request $request, $restaurantId): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', TenantRule::exists('restaurant_tables', restaurantId: (int) $restaurantId)],
        ]);

        $table = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('id', $data['table_id'])
            ->with(['area'])
            ->firstOrFail();

        // Kiểm tra xem bàn có hóa đơn chưa thanh toán nào không
        $hasUnpaidOrder = Order::withoutGlobalScopes()
            ->where('table_id', $table->id)
            ->where('payment_status', 'unpaid')
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->exists();

        if (! $hasUnpaidOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Bàn này hiện tại chưa có đơn hàng nào cần thanh toán.',
            ], 422);
        }

        event(new PaymentRequested($restaurantId, $table->name, $table->area?->name ?? 'Khu vực'));

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu thanh toán thành công!',
        ]);
    }

    /**
     * Khách hàng gửi đánh giá món ăn và nhân viên.
     */
    public function submitFeedback(Request $request, $restaurantId): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', TenantRule::exists('restaurant_tables', restaurantId: (int) $restaurantId)],
            'order_id' => ['nullable', TenantRule::exists('orders', restaurantId: (int) $restaurantId)],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['nullable', 'string', 'max:1000'],
            'is_anonymous' => ['required', 'boolean'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'submitted_by_phone' => ['nullable', 'string', 'max:20'],
            'items_rating' => ['nullable', 'array'], // [ ['product_id' => X, 'rating' => Y, 'comment' => Z], ... ]
            'staff_rating' => ['nullable', 'array'], // [ ['employee_id' => X, 'rating' => Y, 'comment' => Z], ... ]
        ]);

        $table = RestaurantTable::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('id', $data['table_id'])
            ->firstOrFail();

        $feedback = CustomerFeedback::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $table->branch_id,
            'order_id' => $data['order_id'] ?? null,
            'rating' => $data['rating'],
            'content' => $data['content'] ?? null,
            'is_anonymous' => $data['is_anonymous'],
            'submitted_by_name' => $data['is_anonymous'] ? null : ($data['submitted_by_name'] ?: 'Khách hàng'),
            'submitted_by_phone' => $data['is_anonymous'] ? null : ($data['submitted_by_phone'] ?? null),
            'items_rating' => $data['items_rating'] ?? null,
            'staff_rating' => $data['staff_rating'] ?? null,
            'status' => 'new',
        ]);

        event(new FeedbackSubmitted($restaurantId, $feedback));

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn ý kiến đóng góp quý báu của bạn!',
        ]);
    }

    /**
     * Giải quyết danh sách nhân viên trong ca hiện tại.
     */
    private function resolveCurrentShiftStaff(int $restaurantId): array
    {
        $now = now();
        $currentTimeStr = $now->toTimeString();
        $currentDateStr = $now->toDateString();

        // 1. Tìm ca trực hiện tại
        $shifts = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        $matchedShiftId = null;

        foreach ($shifts as $shift) {
            $inShift = false;
            if (! $shift->is_overnight) {
                $inShift = $currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time;
            } else {
                // Ca qua đêm (Ví dụ từ 22:00:00 đến 06:00:00 sáng hôm sau)
                if ($shift->start_time > $shift->end_time) {
                    $inShift = $currentTimeStr >= $shift->start_time || $currentTimeStr <= $shift->end_time;
                } else {
                    $inShift = $currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time;
                }
            }

            if ($inShift) {
                $matchedShiftId = $shift->id;
                break;
            }
        }

        if (! $matchedShiftId) {
            return [];
        }

        // 2. Tìm danh sách phân công cho ca trực và ngày hôm nay
        return ScheduleAssignment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('scheduled_date', $currentDateStr)
            ->where('shift_id', $matchedShiftId)
            ->with(['employee.user'])
            ->get()
            ->map(function ($asm) {
                if ($asm->employee && $asm->employee->user) {
                    return [
                        'employee_id' => $asm->employee->id,
                        'name' => $asm->employee->user->name,
                        'role' => $asm->employee->role_title ?? 'Nhân viên',
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
