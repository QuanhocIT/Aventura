<?php

namespace App\Http\Controllers;

use App\Models\TemporaryOrder;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\OrderService;
use App\Http\Controllers\PromotionController;
use App\Events\Customer\TemporaryOrderUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            ->with(['table.area'])
            ->latest()
            ->get()
            ->map(fn($to) => [
                'id'             => $to->id,
                'table_name'     => $to->table?->name ?? 'Bàn trống',
                'area_name'      => $to->table?->area?->name ?? 'Khu vực',
                'total_amount'   => (float) $to->total_amount,
                'customer_name'  => $to->customer_name,
                'customer_phone' => $to->customer_phone,
                'status'         => $to->status,
                'items'          => $to->cart_data,
                'minutes_elapsed'=> $to->created_at->diffInMinutes(now()),
                'created_at'     => $to->created_at->format('H:i'),
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

        $order = DB::transaction(function () use ($temporaryOrder, $user) {
            $customerId = null;
            if ($temporaryOrder->customer_phone) {
                $customer = \App\Models\Customer::firstOrCreate(
                    [
                        'restaurant_id' => $temporaryOrder->restaurant_id,
                        'phone' => $temporaryOrder->customer_phone
                    ],
                    [
                        'full_name' => $temporaryOrder->customer_name ?: 'Khách gọi món QR',
                        'branch_id' => $temporaryOrder->branch_id,
                    ]
                );
                $customerId = $customer->id;
            }

            // Chuẩn bị payload cho OrderService
            $orderData = [
                'table_id' => $temporaryOrder->table_id,
                'customer_id' => $customerId,
                'note'     => "Đơn QR-Order [Xác nhận bởi: {$user->name}]",
                'items'    => collect($temporaryOrder->cart_data)->map(fn($item) => [
                    'product_id' => $item['product_id'],
                    'quantity'   => (float) $item['quantity'],
                    'notes'      => $item['notes'] ?? null,
                ])->toArray(),
            ];

            // Gọi OrderService để khởi tạo đơn hàng chính thức (Kích hoạt DB Transaction & Kitchen Display Sync)
            $order = $this->orderService->createOrder($orderData, $user);

            // Chuyển kênh phân phối sang 'qr'
            $order->update([
                'channel' => 'qr',
            ]);

            // Cập nhật trạng thái đơn đệm thành confirmed
            $temporaryOrder->update([
                'status'   => 'confirmed',
                'order_id' => $order->id,
            ]);

            // Ghi nhật ký kiểm toán
            AuditLog::log('temporary_order_confirmed', 'updated', $temporaryOrder, [
                'status' => 'waiting_verification',
            ], [
                'status'   => 'confirmed',
                'order_id' => $order->id,
            ]);

            return $order;
        });

        // Phát tín hiệu Realtime cập nhật trạng thái đơn cho Khách hàng
        event(new TemporaryOrderUpdated($temporaryOrder));

        // Lấy gợi ý Upselling AI dựa trên các món ăn trong đơn hàng
        $promotionController = new PromotionController();
        $itemNames = collect($temporaryOrder->cart_data)->pluck('name')->filter()->toArray();
        $upsellRequest = new Request(['items' => $itemNames]);
        $upsellRequest->setUserResolver(fn() => $user);
        $suggestionResult = $promotionController->getUpsellSuggestion($upsellRequest);
        $upsell = $suggestionResult->getData();

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận và khởi tạo đơn hàng chính thức thành công!',
            'order_id' => $order->id,
            'upsell'  => $upsell,
        ]);
    }

    /**
     * Hủy bỏ yêu cầu đặt món QR của khách hàng (ngăn chặn spam/hóa đơn ảo).
     */
    public function cancel(Request $request, TemporaryOrder $temporaryOrder): JsonResponse
    {
        $user = $request->user();
        abort_if($temporaryOrder->restaurant_id !== $user->restaurant_id, 403);
        abort_unless(in_array($temporaryOrder->status, ['waiting_verification', 'escalated']), 422, 'Đơn hàng này đã được xử lý trước đó.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $temporaryOrder->update([
            'status'               => 'cancelled',
            'cancelled_by'         => $user->id,
            'cancellation_reason'  => $data['reason'],
        ]);

        // Ghi nhật ký kiểm toán với JSON log chi tiết
        AuditLog::log('temporary_order_cancelled', 'updated', $temporaryOrder, [
            'status' => 'waiting_verification',
        ], [
            'status'              => 'cancelled',
            'cancelled_by'        => $user->name,
            'cancellation_reason' => $data['reason'],
            'cart_data'           => $temporaryOrder->cart_data,
        ]);

        // Phát tín hiệu Realtime cập nhật trạng thái cho Khách hàng
        event(new TemporaryOrderUpdated($temporaryOrder));

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
            ->map(fn($to) => [
                'id'                  => $to->id,
                'table_name'          => $to->table?->name ?? 'Bàn trống',
                'area_name'           => $to->table?->area?->name ?? 'Khu vực',
                'total_amount'        => (float) $to->total_amount,
                'items'               => $to->cart_data,
                'cancelled_by_name'   => $to->cancelledBy?->name ?? 'Hệ thống',
                'cancellation_reason' => $to->cancellation_reason,
                'cancelled_at'        => $to->updated_at->format('H:i d/m/Y'),
            ]);

        return response()->json([
            'success' => true,
            'rejected_logs' => $logs,
        ]);
    }
}
