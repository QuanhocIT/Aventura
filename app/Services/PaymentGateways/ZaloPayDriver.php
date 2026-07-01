<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ZaloPay order-creation + callback integration.
 * Docs: https://docs.zalopay.vn/v2/general/overview.html
 */
class ZaloPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $appId = (string) config('services.zalopay.app_id');
        $key1 = (string) config('services.zalopay.key1');
        $endpoint = (string) config('services.zalopay.endpoint');

        $appTransId = now()->format('ymd') . '_' . $order->id . '_' . now()->timestamp;
        $appTime = (string) now()->getTimestampMs();
        $amount = (int) round((float) $order->total_amount);
        $appUser = 'aventura_customer';

        $embedData = json_encode([
            'order_id' => $order->id,
            'redirecturl' => $returnUrl,
        ]);

        $item = json_encode([
            ['order_number' => $order->order_number, 'amount' => $amount],
        ]);

        // ZaloPay requires this exact field order/pipe-delimited format when signing.
        $macData = "{$appId}|{$appTransId}|{$appUser}|{$amount}|{$appTime}|{$embedData}|{$item}";
        $mac = hash_hmac('sha256', $macData, $key1);

        $response = Http::asForm()->timeout(10)->post($endpoint, [
            'app_id' => $appId,
            'app_trans_id' => $appTransId,
            'app_user' => $appUser,
            'app_time' => $appTime,
            'amount' => $amount,
            'item' => $item,
            'embed_data' => $embedData,
            'description' => "Thanh toan don hang #{$order->order_number}",
            'bank_code' => '',
            'callback_url' => route('webhooks.zalopay'),
            'mac' => $mac,
        ]);

        $data = $response->json();

        if (! $response->successful() || ($data['return_code'] ?? 0) !== 1 || empty($data['order_url'])) {
            Log::error('ZaloPayDriver: failed to create payment URL', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Không thể tạo giao dịch ZaloPay: ' . ($data['return_message'] ?? 'Lỗi không xác định'));
        }

        return $data['order_url'];
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $key2 = (string) config('services.zalopay.key2');

        $data = (string) $request->input('data');
        $reqMac = (string) $request->input('mac');

        $expectedMac = hash_hmac('sha256', $data, $key2);

        if (! hash_equals($expectedMac, $reqMac)) {
            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'Invalid ZaloPay mac');
        }

        $decoded = json_decode($data, true) ?? [];
        $embedData = json_decode($decoded['embed_data'] ?? '{}', true) ?? [];
        $orderId = isset($embedData['order_id']) ? (int) $embedData['order_id'] : null;
        $transactionCode = (string) ($decoded['zp_trans_id'] ?? $decoded['app_trans_id'] ?? '');

        if (! $orderId) {
            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: $transactionCode, error: 'Missing order_id in ZaloPay embed_data');
        }

        return new PaymentCallbackResult(success: true, orderId: $orderId, transactionCode: $transactionCode);
    }

    public function getDisplayName(): string
    {
        return 'ZaloPay';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.zalopay.app_id')) && ! empty(config('services.zalopay.key1'));
    }
}
