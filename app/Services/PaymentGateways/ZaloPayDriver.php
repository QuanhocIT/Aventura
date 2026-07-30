<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tích hợp ZaloPay "create order" theo tài liệu chính thức:
 * https://docs.zalopay.vn/v2/general/overview.html
 *
 * Lưu ý: chưa có sandbox credentials thật để test end-to-end — cần verify lại
 * với tài khoản merchant sandbox ZaloPay trước khi bật cho khách hàng thật.
 */
class ZaloPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $appId = config('services.zalopay.app_id');
        $key1 = config('services.zalopay.key1');
        $endpoint = config('services.zalopay.endpoint', 'https://sb-openapi.zalopay.vn/v2/create');

        if (empty($appId) || empty($key1)) {
            throw new \RuntimeException('ZaloPay chưa được cấu hình. Vui lòng thêm ZALOPAY_APP_ID, ZALOPAY_KEY1, ZALOPAY_KEY2 vào .env.');
        }

        // app_trans_id bắt buộc theo định dạng yymmdd_<mã duy nhất trong ngày>.
        $appTransId = now()->format('ymd').'_'.$order->id.'_'.time();
        $appTime = (int) round(microtime(true) * 1000);
        $amount = (int) round((float) $order->total_amount);
        $appUser = 'user_'.$order->id;
        $embedData = json_encode(['redirecturl' => $returnUrl], JSON_UNESCAPED_SLASHES);
        $item = json_encode([], JSON_UNESCAPED_SLASHES);

        $data = $appId.'|'.$appTransId.'|'.$appUser.'|'.$amount.'|'.$appTime.'|'.$embedData.'|'.$item;
        $mac = hash_hmac('sha256', $data, $key1);

        $response = Http::timeout(10)->asForm()->post($endpoint, [
            'app_id' => (int) $appId,
            'app_trans_id' => $appTransId,
            'app_user' => $appUser,
            'app_time' => $appTime,
            'amount' => $amount,
            'item' => $item,
            'embed_data' => $embedData,
            'description' => 'Thanh toan don hang '.$order->order_number,
            'bank_code' => '',
            'mac' => $mac,
            'callback_url' => route('webhooks.zalopay'),
        ]);

        $responseData = $response->json() ?? [];

        if (! $response->successful() || (int) ($responseData['return_code'] ?? 0) !== 1 || empty($responseData['order_url'])) {
            Log::error('ZaloPayDriver: tạo order_url thất bại', ['response' => $responseData]);

            throw new \RuntimeException('Không tạo được liên kết thanh toán ZaloPay: '.($responseData['return_message'] ?? 'lỗi không xác định'));
        }

        return $responseData['order_url'];
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $key2 = config('services.zalopay.key2');

        $dataStr = (string) $request->input('data', '');
        $reqMac = (string) $request->input('mac', '');

        $calculatedMac = hash_hmac('sha256', $dataStr, (string) $key2);

        if (empty($reqMac) || ! hash_equals($calculatedMac, $reqMac)) {
            Log::warning('ZaloPayDriver: chữ ký callback (mac) không hợp lệ');

            return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'invalid_mac');
        }

        $decoded = json_decode($dataStr, true) ?? [];
        $appTransId = (string) ($decoded['app_trans_id'] ?? '');
        $segments = explode('_', $appTransId);
        $orderId = (int) ($segments[1] ?? 0);

        return new PaymentCallbackResult(
            success: true,
            orderId: $orderId,
            transactionCode: (string) ($decoded['zp_trans_id'] ?? ''),
        );
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
