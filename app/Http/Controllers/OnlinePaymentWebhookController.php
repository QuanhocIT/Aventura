<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnlinePaymentWebhookController extends Controller
{
    public function __construct(private PaymentGatewayService $gateway) {}

    public function handleVnpay(Request $request): JsonResponse
    {
        return $this->processWebhook('vnpay', $request);
    }

    public function handleMomo(Request $request): JsonResponse
    {
        return $this->processWebhook('momo', $request);
    }

    public function handleZalopay(Request $request): JsonResponse
    {
        return $this->processWebhook('zalopay', $request);
    }

    private function processWebhook(string $gateway, Request $request): JsonResponse
    {
        try {
            $result = $this->gateway->handleCallback($gateway, $request);

            if ($result->success && $result->orderId) {
                $order = \App\Models\Order::find($result->orderId);

                if ($order && $order->payment_status !== 'paid') {
                    $systemUser = $order->cashier
                        ?? \App\Models\Restaurant::find($order->restaurant_id)?->owner
                        ?? \App\Models\User::where('restaurant_id', $order->restaurant_id)->first();

                    if (! $systemUser) {
                        Log::error("OnlinePaymentWebhook: no user found for order {$order->id}");
                        return response()->json(['success' => false], 500);
                    }

                    app(\App\Services\OrderService::class)->payOrder(
                        $order,
                        ['payment_method' => $gateway, 'cash_received' => $order->total_amount, 'change_amount' => 0],
                        $systemUser,
                        true
                    );

                    Log::info("OnlinePaymentWebhook: {$gateway} payment confirmed", [
                        'order_id' => $order->id,
                        'transaction_code' => $result->transactionCode,
                    ]);
                }

                return response()->json(['success' => true]);
            }

            Log::warning("OnlinePaymentWebhook: {$gateway} callback failed", [
                'error' => $result->error,
            ]);

            return response()->json(['success' => false], 400);
        } catch (\Throwable $e) {
            Log::error("OnlinePaymentWebhook: {$gateway} exception", ['error' => $e->getMessage()]);

            return response()->json(['success' => false], 500);
        }
    }
}
