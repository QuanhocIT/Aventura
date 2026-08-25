<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\User;
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
        // Xác thực chữ ký webhook (cùng secret/nhà cung cấp Sepay với Billing\PaymentWebhookController)
        // — thiếu bước này cho phép giả mạo xác nhận thanh toán mà không cần chuyển tiền thật.
        $signature = (string) ($request->header('X-SePay-Signature') ?? $request->header('X-Signature') ?? '');
        $secret = (string) config('billing.webhook_secret');

        if (empty($secret)) {
            if (app()->environment('production')) {
                Log::critical('OrderPaymentWebhookController: BILLING_WEBHOOK_SECRET chưa được cấu hình trong production!');

                return response()->json(['success' => false, 'message' => 'Configuration error'], 500);
            }
        } elseif (! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            Log::warning('OrderPaymentWebhookController: chữ ký webhook không hợp lệ', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

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
            'description' => $description,
        ]);

        if (empty($description)) {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung chuyển khoản trống.',
            ], 400);
        }

        // Tìm mã đơn hàng từ nội dung chuyển khoản (Định dạng AVTORD{order_id})
        if (! preg_match('/AVTORD(\d+)/i', $description, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy mã đơn hàng AVTORD trong nội dung chuyển khoản.',
            ], 422);
        }

        $orderId = (int) $matches[1];
        $order = Order::find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy đơn hàng ID: {$orderId}",
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng này đã được thanh toán từ trước.',
            ]);
        }

        // Tìm hoặc giải quyết user chịu trách nhiệm cập nhật đơn (để ghi log, trừ kho)
        $user = User::find($order->created_by)
            ?? User::where('restaurant_id', $order->restaurant_id)->first()
            ?? User::first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy user thích hợp để xử lý đơn hàng.',
            ], 500);
        }

        // Lấy số tiền thực nhận từ payload webhook (hỗ trợ amount, transferAmount, transfer_amount, creditAmount)
        $paidAmount = (float) (
            $request->input('amount')
            ?? $request->input('transferAmount')
            ?? $request->input('transfer_amount')
            ?? $request->input('creditAmount')
            ?? 0
        );

        if ($paidAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền giao dịch trong callback không hợp lệ (amount <= 0).',
            ], 400);
        }

        if ($paidAmount < (float) $order->total_amount) {
            Log::warning('Webhook thanh toán không đủ số tiền đơn hàng:', [
                'order_id' => $order->id,
                'order_total' => $order->total_amount,
                'paid_amount' => $paidAmount,
            ]);

            return response()->json([
                'success' => false,
                'message' => "Số tiền chuyển khoản ({$paidAmount}) nhỏ hơn tổng tiền đơn hàng ({$order->total_amount}).",
                'order_id' => $order->id,
            ], 422);
        }

        try {
            $paymentData = [
                'payment_method' => 'bank_transfer',
                'cash_received' => $paidAmount,
                'change_amount' => max(0, $paidAmount - (float) $order->total_amount),
            ];

            // payOrder() tự khoá row + re-check trong transaction; trả false nghĩa là
            // webhook trùng lặp đến đồng thời — tuyệt đối không bắn lại event OrderPaid.
            $paid = $this->orderService->payOrder($order, $paymentData, $user, true);

            if (! $paid) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đơn hàng này đã được thanh toán từ trước (webhook trùng lặp).',
                    'order_id' => $order->id,
                    'duplicate' => true,
                ]);
            }

            // Phát sự kiện Realtime báo cho nhân viên và bàn ăn của khách
            if ($order->table_id) {
                event(new OrderPaid($order, (int) $order->restaurant_id, (int) $order->table_id));
            }

            return response()->json([
                'success' => true,
                'message' => "Thanh toán đơn hàng #{$order->order_number} thành công qua Webhook.",
                'order_id' => $order->id,
                'status' => 'completed',
                'payment_status' => 'paid',
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi khi xử lý webhook thanh toán QR:', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi xử lý thanh toán đơn hàng: '.$e->getMessage(),
            ], 500);
        }
    }
}
