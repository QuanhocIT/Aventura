<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * VNPay "vnpay_return"/IPN integration.
 * Docs: https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html
 */
class VNPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $tmnCode = (string) config('services.vnpay.tmncode');
        $hashSecret = (string) config('services.vnpay.hashsecret');
        $vnpUrl = (string) config('services.vnpay.url');

        $txnRef = $order->id . '-' . now()->timestamp;
        $amount = (int) round((float) $order->total_amount * 100);

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $amount,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => "Thanh toan don hang {$order->order_number}",
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => request()->ip() ?? '127.0.0.1',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes(15)->format('YmdHis'),
        ];

        ksort($params);

        $secureHash = $this->buildSecureHash($params, $hashSecret);
        $params['vnp_SecureHash'] = $secureHash;

        return $vnpUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $hashSecret = (string) config('services.vnpay.hashsecret');
        $params = $request->query->all() ?: $request->all();

        $receivedHash = (string) ($params['vnp_SecureHash'] ?? '');
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        ksort($params);
        $expectedHash = $this->buildSecureHash($params, $hashSecret);

        if (! hash_equals($expectedHash, $receivedHash)) {
            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'Invalid VNPay signature');
        }

        $responseCode = (string) ($params['vnp_ResponseCode'] ?? '');
        $txnRef = (string) ($params['vnp_TxnRef'] ?? '');
        $transactionNo = (string) ($params['vnp_TransactionNo'] ?? $txnRef);
        $orderId = (int) strtok($txnRef, '-');

        if ($responseCode !== '00') {
            return new PaymentCallbackResult(success: false, orderId: $orderId, transactionCode: $transactionNo, error: "VNPay response code: {$responseCode}");
        }

        return new PaymentCallbackResult(success: true, orderId: $orderId, transactionCode: $transactionNo);
    }

    public function getDisplayName(): string
    {
        return 'VNPay';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.vnpay.tmncode')) && ! empty(config('services.vnpay.hashsecret'));
    }

    /**
     * VNPay requires the hash data string to use the same urlencoded values as the final
     * query string (RFC1738/PHP_QUERY_RFC1738-style '+' for spaces), built from params
     * already sorted by key.
     */
    private function buildSecureHash(array $sortedParams, string $hashSecret): string
    {
        $hashData = http_build_query($sortedParams, '', '&', PHP_QUERY_RFC3986);

        return hash_hmac('sha512', $hashData, $hashSecret);
    }
}
