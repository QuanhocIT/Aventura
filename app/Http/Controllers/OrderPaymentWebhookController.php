<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderPaymentWebhookController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Nhận nội dung chuyển khoản từ webhook mô phỏng
        // Hỗ trợ các trường thông tin phổ biến như description, content, memo, addInfo, transaction_content
        $description = $request->input('description') 
            ?? $request->input('content') 
            ?? $request->input('memo') 
            ?? $request->input('addInfo') 
            ?? $request->input('transaction_content') 
            ?? '';

        Log::info('Simulated VietQR Webhook received:', [
            'payload' => $request->all(),
            'description' => $description
        ]);

        if (empty($description)) {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung chuyển khoản trống.'
            ], 400);
        }

        // Tìm mã đơn hàng từ nội dung chuyển khoản (Định dạng AVTORD{order_id})
        if (!preg_match('/AVTORD(\d+)/i', $description, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã đơn hàng AVTORD trong nội dung chuyển khoản.'
            ], 422);
        }

        $orderId = (int)$matches[1];
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy đơn hàng ID: {$orderId}"
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng này đã được thanh toán từ trước.'
            ]);
        }

        // Tìm hoặc giải quyết user chịu trách nhiệm cập nhật đơn (để ghi log, trừ kho)
        $user = \App\Models\User::find($order->created_by)
            ?? \App\Models\User::where('restaurant_id', $order->restaurant_id)->first()
            ?? \App\Models\User::first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy user thích hợp để xử lý đơn hàng.'
            ], 500);
        }

        try {
            $paymentData = [
                'payment_method' => 'bank_transfer',
                'cash_received' => $order->total_amount,
                'change_amount' => 0,
            ];

            // Gọi OrderService::payOrder()
            $this->orderService->payOrder($order, $paymentData, $user);

            // Phát sự kiện Realtime báo cho nhân viên và bàn ăn của khách
            if ($order->table_id) {
                event(new \App\Events\OrderPaid($order, (int)$order->restaurant_id, (int)$order->table_id));
            }

            return response()->json([
                'success' => true,
                'message' => "Thanh toán đơn hàng #{$order->order_number} thành công qua Webhook.",
                'order_id' => $order->id,
                'status' => 'completed',
                'payment_status' => 'paid'
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi xử lý webhook thanh toán QR:', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi xử lý thanh toán đơn hàng: ' . $e->getMessage()
            ], 500);
        }
    }
}
