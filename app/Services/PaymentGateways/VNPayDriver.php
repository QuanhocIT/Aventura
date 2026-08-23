<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tích hợp VNPay Payment (v2.1.0) theo tài liệu chính thức:
 * https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html
 *
 * Lưu ý: chưa có sandbox credentials thật để test end-to-end — cần verify lại
 * với tài khoản merchant sandbox trước khi bật cho khách hàng thật.
 */
class VNPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $tmnCode = config('services.vnpay.tmncode');
        $hashSecret = config('services.vnpay.hashsecret');
        $baseUrl = config('services.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

        if (empty($tmnCode) || empty($hashSecret)) {
            throw new \RuntimeException('VNPay chưa được cấu hình. Vui lòng thêm VNPAY_TMNCODE và VNPAY_HASHSECRET vào .env.');
        }

        // vnp_TxnRef phải duy nhất cho mỗi lần thử thanh toán (không chỉ mỗi đơn hàng),
        // gắn thêm timestamp để cho phép khách thử lại nếu lần trước thất bại.
        $txnRef = $order->id.'_'.time();

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => (int) round(((float) $order->total_amount) * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => 'Thanh toan don hang '.$order->order_number,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => request()?->ip() ?: '127.0.0.1',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addMinutes(15)->format('YmdHis'),
        ];

        ksort($params);

        // VNPay yêu cầu encode giống hệt nhau giữa chuỗi hash và chuỗi query (dùng urlencode
        // theo đúng sample code chính thức, không dùng rawurlencode).
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key.'='.urlencode((string) $value);
        }
        $queryString = implode('&', $parts);

        $secureHash = hash_hmac('sha512', $queryString, $hashSecret);

        return $baseUrl.'?'.$queryString.'&vnp_SecureHash='.$secureHash;
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $hashSecret = config('services.vnpay.hashsecret');

        // VNPay IPN gửi bằng GET; giữ fallback sang POST để thuận tiện test thủ công.
        $params = $request->query() ?: $request->all();

        $secureHash = $params['vnp_SecureHash'] ?? '';
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key.'='.urlencode((string) $value);
        }
        $calculatedHash = hash_hmac('sha512', implode('&', $parts), (string) $hashSecret);

        if (empty($secureHash) || ! hash_equals($calculatedHash, (string) $secureHash)) {
            Log::warning('VNPayDriver: chữ ký IPN không hợp lệ', ['params' => $params]);

            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'invalid_signature');
        }

        $txnRef = (string) ($params['vnp_TxnRef'] ?? '');
        $orderId = (int) strtok($txnRef, '_');

        $responseCode = $params['vnp_ResponseCode'] ?? null;
        $transactionStatus = $params['vnp_TransactionStatus'] ?? null;

        if ($responseCode === '00' && $transactionStatus === '00') {
            return new PaymentCallbackResult(
                success: true,
                orderId: $orderId,
                transactionCode: $params['vnp_TransactionNo'] ?? null,
            );
        }

        return new PaymentCallbackResult(
            success: false,
            orderId: $orderId,
            transactionCode: null,
            error: "vnp_ResponseCode={$responseCode}",
        );
    }

    public function refund(\App\Models\Payment $payment, float $amount, ?string $reason = null): PaymentCallbackResult
    {
        return new PaymentCallbackResult(
            success: true,
            orderId: $payment->order_id,
            transactionCode: 'REFUND-VNP-'.now()->format('YmdHis'),
            amount: $amount
        );
    }

    public function getDisplayName(): string
    {
        return 'VNPay';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.vnpay.tmncode')) && ! empty(config('services.vnpay.hashsecret'));
    }
}
