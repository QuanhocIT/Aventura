<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentGateways\PaymentCallbackResult;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnlinePaymentWebhookController extends Controller
{
    public function __construct(private PaymentGatewayService $gateway) {}

    public function handleVnpay(Request $request): JsonResponse
    {
        $result = $this->processWebhook('vnpay', $request);

        // VNPay yêu cầu đúng định dạng ack {"RspCode":...,"Message":...}, khác với
        // response JSON tự do — nếu không đúng, VNPay sẽ coi là lỗi và gửi lại IPN nhiều lần.
        if (! $result) {
            return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
        }

        if ($result->error === 'invalid_signature') {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }

        if (! $result->orderId) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
        }

        return response()->json([
            'RspCode' => $result->success ? '00' : '99',
            'Message' => $result->success ? 'Confirm Success' : ($result->error ?? 'Unknown error'),
        ]);
    }

    public function handleMomo(Request $request): JsonResponse
    {
        $this->processWebhook('momo', $request);

        // Momo chỉ cần HTTP 2xx để coi là đã nhận IPN, không yêu cầu body cụ thể.
        return response()->json(['message' => 'ok']);
    }

    public function handleZalopay(Request $request): JsonResponse
    {
        $result = $this->processWebhook('zalopay', $request);

        // ZaloPay bắt buộc đúng định dạng {"return_code":1|0,"return_message":...},
        // nếu không sẽ retry callback nhiều lần trong ~15 phút.
        return response()->json([
            'return_code' => $result && $result->success ? 1 : 0,
            'return_message' => $result && $result->success ? 'success' : ($result?->error ?? 'failed'),
        ]);
    }

    private function processWebhook(string $gateway, Request $request): ?PaymentCallbackResult
    {
        try {
            $result = $this->gateway->handleCallback($gateway, $request);

            if ($result->success && $result->orderId) {
                $order = Order::find($result->orderId);

                if ($order && $order->payment_status !== 'paid') {
                    // Check idempotency by transaction_code if provided
                    if (! empty($result->transactionCode)) {
                        $existingPayment = \App\Models\Payment::where('order_id', $order->id)
                            ->where(function ($q) use ($result) {
                                $q->where('transaction_code', $result->transactionCode)
                                    ->orWhere('meta->transaction_code', $result->transactionCode);
                            })
                            ->exists();

                        if ($existingPayment) {
                            Log::info("OnlinePaymentWebhook: {$gateway} transaction {$result->transactionCode} already processed.");
                            return $result;
                        }
                    }

                    $paidAmount = $result->amount ?? (float) $order->total_amount;

                    if ($paidAmount < (float) $order->total_amount) {
                        Log::warning("OnlinePaymentWebhook: {$gateway} callback amount ({$paidAmount}) less than order total ({$order->total_amount})", [
                            'order_id' => $order->id,
                        ]);

                        return new PaymentCallbackResult(
                            success: false,
                            orderId: $result->orderId,
                            transactionCode: $result->transactionCode,
                            error: 'insufficient_amount',
                            amount: $paidAmount
                        );
                    }

                    $systemUser = $order->cashier
                        ?? Restaurant::find($order->restaurant_id)?->owner
                        ?? User::where('restaurant_id', $order->restaurant_id)->first();

                    if (! $systemUser) {
                        Log::error("OnlinePaymentWebhook: no user found for order {$order->id}");

                        return new PaymentCallbackResult(success: false, orderId: $result->orderId, transactionCode: null, error: 'no_system_user');
                    }

                    app(OrderService::class)->payOrder(
                        $order,
                        [
                            'payment_method' => $gateway,
                            'transaction_code' => $result->transactionCode,
                            'cash_received' => $paidAmount,
                            'change_amount' => max(0, $paidAmount - (float) $order->total_amount),
                        ],
                        $systemUser,
                        true
                    );

                    Log::info("OnlinePaymentWebhook: {$gateway} payment confirmed", [
                        'order_id' => $order->id,
                        'transaction_code' => $result->transactionCode,
                    ]);
                }

                return $result;
            }

            Log::warning("OnlinePaymentWebhook: {$gateway} callback failed", [
                'error' => $result->error,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("OnlinePaymentWebhook: {$gateway} exception", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
