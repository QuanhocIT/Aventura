<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MoMo "payWithMethod" one-time payment integration.
 * Docs: https://developers.momo.vn/v3/docs/payment/api/wallet/onetime
 */
class MomoDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $partnerCode = (string) config('services.momo.partner_code');
        $accessKey = (string) config('services.momo.access_key');
        $secretKey = (string) config('services.momo.secret_key');
        $endpoint = (string) config('services.momo.endpoint');

        $requestId = (string) Str::uuid();
        $orderId = $order->order_number . '-' . now()->timestamp;
        $amount = (string) (int) round((float) $order->total_amount);
        $orderInfo = "Thanh toan don hang #{$order->order_number}";
        $ipnUrl = route('webhooks.momo');
        $requestType = 'payWithMethod';
        $extraData = base64_encode((string) $order->id);

        // MoMo requires this exact field order when building the string to sign.
        $rawSignature = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}"
            . "&ipnUrl={$ipnUrl}&orderId={$orderId}&orderInfo={$orderInfo}"
            . "&partnerCode={$partnerCode}&redirectUrl={$returnUrl}"
            . "&requestId={$requestId}&requestType={$requestType}";

        $signature = hash_hmac('sha256', $rawSignature, $secretKey);

        $response = Http::timeout(10)->post($endpoint, [
            'partnerCode' => $partnerCode,
            'partnerName' => config('app.name'),
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

        $data = $response->json();

        if (! $response->successful() || ($data['resultCode'] ?? -1) !== 0 || empty($data['payUrl'])) {
            Log::error('MomoDriver: failed to create payment URL', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw new \RuntimeException('Không thể tạo giao dịch MoMo: ' . ($data['message'] ?? 'Lỗi không xác định'));
        }

        return $data['payUrl'];
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $secretKey = (string) config('services.momo.secret_key');
        $accessKey = (string) config('services.momo.access_key');

        $partnerCode = (string) $request->input('partnerCode');
        $orderId = (string) $request->input('orderId');
        $requestId = (string) $request->input('requestId');
        $amount = (string) $request->input('amount');
        $orderInfo = (string) $request->input('orderInfo');
        $orderType = (string) $request->input('orderType');
        $transId = (string) $request->input('transId');
        $resultCode = (string) $request->input('resultCode');
        $message = (string) $request->input('message');
        $payType = (string) $request->input('payType');
        $responseTime = (string) $request->input('responseTime');
        $extraData = (string) $request->input('extraData');
        $signature = (string) $request->input('signature');

        $rawSignature = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}"
            . "&message={$message}&orderId={$orderId}&orderInfo={$orderInfo}"
            . "&orderType={$orderType}&partnerCode={$partnerCode}&payType={$payType}"
            . "&requestId={$requestId}&responseTime={$responseTime}&resultCode={$resultCode}"
            . "&transId={$transId}";

        $expectedSignature = hash_hmac('sha256', $rawSignature, $secretKey);

        if (! hash_equals($expectedSignature, $signature)) {
            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'Invalid MoMo signature');
        }

        if ((int) $resultCode !== 0) {
            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: $transId, error: $message ?: 'MoMo payment failed');
        }

        $orderIdDecoded = (int) base64_decode($extraData);

        return new PaymentCallbackResult(success: true, orderId: $orderIdDecoded, transactionCode: $transId);
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
