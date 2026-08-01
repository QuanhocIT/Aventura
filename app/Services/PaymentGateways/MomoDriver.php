<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tích hợp Momo "payWithMethod" (v2) theo tài liệu chính thức:
 * https://developers.momo.vn/v3/docs/payment/api/wallet/onetime
 *
 * Lưu ý: chưa có sandbox credentials thật để test end-to-end — cần verify lại
 * với tài khoản merchant test.momo.vn trước khi bật cho khách hàng thật.
 */
class MomoDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $partnerCode = config('services.momo.partner_code');
        $accessKey = config('services.momo.access_key');
        $secretKey = config('services.momo.secret_key');
        $endpoint = config('services.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');

        if (empty($partnerCode) || empty($accessKey) || empty($secretKey)) {
            throw new \RuntimeException('Momo chưa được cấu hình. Vui lòng thêm MOMO_PARTNER_CODE, MOMO_ACCESS_KEY, MOMO_SECRET_KEY vào .env.');
        }

        $orderId = (string) $order->id;
        $requestId = $orderId.'-'.time();
        $amount = (string) (int) round((float) $order->total_amount);
        $orderInfo = 'Thanh toan don hang '.$order->order_number;
        $ipnUrl = route('webhooks.momo');
        $extraData = '';
        $requestType = 'payWithMethod';

        // Thứ tự field trong rawSignature phải khớp chính xác theo tài liệu Momo (không sắp xếp lại).
        $rawSignature = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$ipnUrl}"
            ."&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$partnerCode}&redirectUrl={$returnUrl}"
            ."&requestId={$requestId}&requestType={$requestType}";

        $signature = hash_hmac('sha256', $rawSignature, $secretKey);

        $response = Http::timeout(10)->post($endpoint, [
            'partnerCode' => $partnerCode,
            'partnerName' => 'Aventura',
            'storeId' => $partnerCode,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $returnUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
        ]);

        $data = $response->json() ?? [];

        if (! $response->successful() || empty($data['payUrl'])) {
            Log::error('MomoDriver: tạo payUrl thất bại', ['response' => $data]);

            throw new \RuntimeException('Không tạo được liên kết thanh toán Momo: '.($data['message'] ?? 'lỗi không xác định'));
        }

        return $data['payUrl'];
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $secretKey = config('services.momo.secret_key');

        $accessKey = $request->input('accessKey', config('services.momo.access_key'));
        $amount = $request->input('amount');
        $extraData = $request->input('extraData', '');
        $message = $request->input('message');
        $orderId = $request->input('orderId');
        $orderInfo = $request->input('orderInfo');
        $orderType = $request->input('orderType');
        $partnerCode = $request->input('partnerCode');
        $payType = $request->input('payType');
        $requestId = $request->input('requestId');
        $responseTime = $request->input('responseTime');
        $resultCode = $request->input('resultCode');
        $transId = $request->input('transId');
        $signature = (string) $request->input('signature');

        // Thứ tự field IPN của Momo khác với thứ tự lúc tạo giao dịch — theo đúng tài liệu chính thức.
        $rawSignature = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&message={$message}"
            ."&orderId={$orderId}&orderInfo={$orderInfo}&orderType={$orderType}&partnerCode={$partnerCode}"
            ."&payType={$payType}&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}"
            ."&transId={$transId}";

        $calculatedSignature = hash_hmac('sha256', $rawSignature, (string) $secretKey);

        if (empty($signature) || ! hash_equals($calculatedSignature, $signature)) {
            Log::warning('MomoDriver: chữ ký IPN không hợp lệ', ['orderId' => $orderId]);

            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'invalid_signature');
        }

        if ((int) $resultCode === 0) {
            return new PaymentCallbackResult(
                success: true,
                orderId: (int) $orderId,
                transactionCode: (string) $transId,
            );
        }

        return new PaymentCallbackResult(
            success: false,
            orderId: (int) $orderId,
            transactionCode: null,
            error: "resultCode={$resultCode}",
        );
    }

    public function getDisplayName(): string
    {
        return 'Ví MoMo';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.momo.partner_code')) && ! empty(config('services.momo.secret_key'));
    }
}
