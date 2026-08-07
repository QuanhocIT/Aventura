<?php

namespace App\Http\Controllers;

use App\Events\Customer\TemporaryOrderUpdated;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\TemporaryOrder;
use App\Services\LoyaltyService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffQROrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * Lấy danh sách các đơn đệm đang chờ duyệt hoặc bị chuyển cấp cứu xét.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $tempOrders = TemporaryOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['waiting_verification', 'escalated'])
            ->where('awaiting_customer_confirmation', false)
            ->with(['table.area'])
            ->latest()
            ->get()
            ->map(fn ($to) => [
                'id' => $to->id,
                'table_name' => $to->table?->name ?? 'Bàn trống',
                'area_name' => $to->table?->area?->name ?? 'Khu vực',
                'total_amount' => (float) $to->total_amount,
                'customer_name' => $to->customer_name,
                'customer_phone' => $to->customer_phone,
                'status' => $to->status,
                'awaiting_customer_confirmation' => (bool) $to->awaiting_customer_confirmation,
                'revision_note' => $to->revision_note,
                'items' => $to->cart_data,
                'minutes_elapsed' => $to->created_at->diffInMinutes(now()),
                'created_at' => $to->created_at->format('H:i'),
            ]);

        return response()->json([
            'success' => true,
            'temporary_orders' => $tempOrders,
        ]);
    }

    /**
     * Duyệt đơn đệm và chuyển thành đơn hàng chính thức.
     */
    public function confirm(Request $request, TemporaryOrder $temporaryOrder): JsonResponse
    {
        $user = $request->user();
        abort_if($temporaryOrder->restaurant_id !== $user->restaurant_id, 403);
        abort_unless(in_array($temporaryOrder->status, ['waiting_verification', 'escalated']), 422, 'Đơn hàng này đã được xử lý trước đó.');
        abort_unless(! $temporaryOrder->awaiting_customer_confirmation, 422, 'Đơn hàng đang chờ khách xác nhận thay đổi.');

        $order = DB::transaction(function () use ($temporaryOrder, $user) {
            // Lock temporaryOrder to prevent double confirm
            $temporaryOrder = TemporaryOrder::where('id', $temporaryOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($temporaryOrder->status, ['waiting_verification', 'escalated'])) {
                throw new \Exception('Đơn hàng này đã được xử lý trước đó.');
            }

            if ($temporaryOrder->awaiting_customer_confirmation) {
                throw new \Exception('Đơn hàng đang chờ khách xác nhận thay đổi.');
            }

            $customerId = null;
            $customer = null;
            if ($temporaryOrder->customer_phone) {
                $customer = Customer::firstOrCreate(
                    [
                        'restaurant_id' => $temporaryOrder->restaurant_id,
                        'phone' => $temporaryOrder->customer_phone,
                    ],
                    [
                        'full_name' => $temporaryOrder->customer_name ?: 'Khách gọi món QR',
                        'branch_id' => $temporaryOrder->branch_id,
                    ]
                );
                $customerId = $customer->id;
                // Lock customer
                $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();
            }

            // Kiểm tra điểm loyalty trước
            if ($temporaryOrder->redeem_points > 0) {
                if (! $customer || $customer->loyalty_points < $temporaryOrder->redeem_points) {
                    throw new \Exception('Khách hàng không đủ điểm tích lũy để thực hiện quy đổi.');
                }
            }

            // Chuẩn bị payload cho OrderService
            $orderData = [
                'table_id' => $temporaryOrder->table_id,
                'customer_id' => $customerId,
                'channel' => 'qr',
                'note' => "Đơn QR-Order [Xác nhận bởi: {$user->name}]",
                'items' => collect($temporaryOrder->cart_data)->map(fn ($item) => [
                    'product_id' => $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'notes' => $item['notes'] ?? null,
                ])->toArray(),
            ];

            // Gọi OrderService để khởi tạo đơn hàng chính thức (Kích hoạt DB Transaction & Kitchen Display Sync)
            // Mỗi yêu cầu gọi món QR là một đơn riêng. Luồng POS/offline mới
            // được phép gộp vào đơn đang phục vụ; nếu gộp ở đây, đơn QR sẽ
            // mang ngày tạo của đơn cũ và khách/bếp có thể không thấy món mới.
            $order = $this->orderService->createOrder($orderData, $user, false);

            // Chuyển kênh phân phối sang 'qr'
            $order->update([
                'channel' => 'qr',
            ]);

            // Trừ điểm loyalty chính thức nếu có
            if ($temporaryOrder->redeem_points > 0 && $customer) {
                $discountValue = app(LoyaltyService::class)->redeemPoints($customer, $temporaryOrder->redeem_points, $order);
                if ($discountValue > 0) {
                    $newDiscount = $order->discount_amount + $discountValue;
                    $newTotal = max(0.0, $order->subtotal - $newDiscount);
                    $order->update([
                        'discount_amount' => $newDiscount,
                        'total_amount' => $newTotal,
                        'note' => ($order->note ? $order->note.' ' : '')."[Quy đổi {$temporaryOrder->redeem_points} điểm: -".number_format($discountValue).'đ]',
                    ]);
                }
            }

            // Cập nhật trạng thái đơn đệm thành confirmed
            $temporaryOrder->update([
                'status' => 'confirmed',
                'order_id' => $order->id,
            ]);

            // Ghi nhật ký kiểm toán
            AuditLog::log('temporary_order_confirmed', 'updated', $temporaryOrder, [
                'status' => 'waiting_verification',
            ], [
                'status' => 'confirmed',
                'order_id' => $order->id,
            ]);

            return $order;
        });

        $temporaryOrder->refresh();

        // Phát tín hiệu Realtime cập nhật trạng thái đơn cho Khách hàng
        try {
            event(new TemporaryOrderUpdated($temporaryOrder));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast temporary order updated failed: '.$e->getMessage());
        }

        // Lấy gợi ý Upselling AI dựa trên các món ăn trong đơn hàng
        $promotionController = app(PromotionController::class);
        $itemNames = collect($temporaryOrder->cart_data)->pluck('name')->filter()->toArray();
        $upsellRequest = new Request(['items' => $itemNames]);
        $upsellRequest->setUserResolver(fn () => $user);
        $suggestionResult = $promotionController->getUpsellSuggestion($upsellRequest);
        $upsell = $suggestionResult->getData();

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận và khởi tạo đơn hàng chính thức thành công!',
            'order_id' => $order->id,
            'upsell' => $upsell,
        ]);
    }

    /**
     * Nhân viên chỉnh đơn QR trước khi duyệt; khách tại bàn phải xác nhận lại.
     */
    public function requestRevision(Request $request, TemporaryOrder $temporaryOrder): JsonResponse
    {
        $user = $request->user();
        abort_if($temporaryOrder->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updatedTemporaryOrder = DB::transaction(function () use ($temporaryOrder, $user, $data) {
                $lockedOrder = TemporaryOrder::where('id', $temporaryOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedOrder->status, ['waiting_verification', 'escalated'], true)
                    || $lockedOrder->awaiting_customer_confirmation) {
                    throw new \Exception('Đơn QR này hiện không thể chỉnh sửa.');
                }

                $productIds = collect($data['items'])
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $products = Product::withoutGlobalScopes()
                    ->where('restaurant_id', $lockedOrder->restaurant_id)
                    ->whereIn('id', $productIds)
                    ->where(function ($query) use ($lockedOrder): void {
                        $query->whereNull('branch_id')->orWhere('branch_id', $lockedOrder->branch_id);
                    })
                    ->where('is_active', true)
                    ->where('is_available', true)
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw new \Exception('Một hoặc nhiều món trong đơn không còn phục vụ.');
                }

                $cartData = collect($data['items'])->map(function (array $item) use ($products): array {
                    $product = $products->get((int) $item['product_id']);
                    $quantity = (float) $item['quantity'];
                    $lineTotal = (float) $product->price * $quantity;

                    return [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->code,
                        'quantity' => $quantity,
                        'unit_price' => (float) $product->price,
                        'notes' => $item['notes'] ?? null,
                        'line_total' => $lineTotal,
                    ];
                })->values()->all();

                $grossTotal = (float) collect($cartData)->sum('line_total');
                $pointsDiscount = (int) $lockedOrder->redeem_points * 100;

                $lockedOrder->update([
                    'cart_data' => $cartData,
                    'total_amount' => max(0, $grossTotal - $pointsDiscount),
                    'awaiting_customer_confirmation' => true,
                    'revision_note' => $data['message'],
                    'revised_by' => $user->id,
                    'revision_confirmed_at' => null,
                ]);

                AuditLog::log('temporary_order_revision_requested', 'updated', $lockedOrder, [
                    'cart_data' => $temporaryOrder->cart_data,
                    'total_amount' => $temporaryOrder->total_amount,
                ], [
                    'cart_data' => $cartData,
                    'total_amount' => $lockedOrder->total_amount,
                    'revision_note' => $data['message'],
                    'revised_by' => $user->name,
                ]);

                return $lockedOrder->fresh(['table']);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        try {
            event(new TemporaryOrderUpdated($updatedTemporaryOrder));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast temporary order revision failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi thay đổi tới khách. Đơn sẽ chờ khách xác nhận lại.',
            'temporary_order' => [
                'id' => $updatedTemporaryOrder->id,
                'total_amount' => (float) $updatedTemporaryOrder->total_amount,
                'awaiting_customer_confirmation' => true,
            ],
        ]);
    }

    /**
     * Hủy bỏ yêu cầu đặt món QR của khách hàng (ngăn chặn spam/hóa đơn ảo).
     */
    public function cancel(Request $request, TemporaryOrder $temporaryOrder): JsonResponse
    {
        $user = $request->user();
        abort_if($temporaryOrder->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $updatedTemporaryOrder = DB::transaction(function () use ($temporaryOrder, $user, $data) {
                // Lock the temporary order row
                $lockedOrder = TemporaryOrder::where('id', $temporaryOrder->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedOrder->status, ['waiting_verification', 'escalated'])) {
                    throw new \Exception('Đơn hàng này đã được xử lý trước đó.');
                }

                $lockedOrder->update([
                    'status' => 'cancelled',
                    'cancelled_by' => $user->id,
                    'cancellation_reason' => $data['reason'],
                ]);

                // Ghi nhật ký kiểm toán với JSON log chi tiết
                AuditLog::log('temporary_order_cancelled', 'updated', $lockedOrder, [
                    'status' => 'waiting_verification',
                ], [
                    'status' => 'cancelled',
                    'cancelled_by' => $user->name,
                    'cancellation_reason' => $data['reason'],
                    'cart_data' => $lockedOrder->cart_data,
                ]);

                return $lockedOrder->fresh(['table']);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        // Phát tín hiệu Realtime cập nhật trạng thái cho Khách hàng
        try {
            event(new TemporaryOrderUpdated($updatedTemporaryOrder));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast temporary order updated failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy yêu cầu gọi món thành công và ghi nhận nhật ký tra soát.',
        ]);
    }

    /**
     * Xem danh sách các yêu cầu gọi món QR bị nhân viên hủy trong ngày (Quản lý).
     */
    public function rejectedLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        $restaurantId = $user->restaurant_id;

        $logs = TemporaryOrder::where('restaurant_id', $restaurantId)
            ->where('status', 'cancelled')
            ->whereDate('updated_at', today())
            ->with(['table.area', 'cancelledBy'])
            ->latest()
            ->get()
            ->map(fn ($to) => [
                'id' => $to->id,
                'table_name' => $to->table?->name ?? 'Bàn trống',
                'area_name' => $to->table?->area?->name ?? 'Khu vực',
                'total_amount' => (float) $to->total_amount,
                'items' => $to->cart_data,
                'cancelled_by_name' => $to->cancelledBy?->name ?? 'Hệ thống',
                'cancellation_reason' => $to->cancellation_reason,
                'cancelled_at' => $to->updated_at->format('H:i d/m/Y'),
            ]);

        return response()->json([
            'success' => true,
            'rejected_logs' => $logs,
        ]);
    }
}
