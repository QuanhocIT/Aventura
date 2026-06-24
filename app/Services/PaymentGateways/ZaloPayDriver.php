<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;

class ZaloPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        // TODO: Implement khi có ZaloPay API keys
        // Flow: POST to ZaloPay API → nhận order_url → redirect khách
        // Docs: https://docs.zalopay.vn/v2/general/overview.html
        throw new \RuntimeException('ZaloPay chưa được cấu hình. Vui lòng thêm ZALOPAY_APP_ID vào .env.');
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        // TODO: Verify ZaloPay callback MAC
        throw new \RuntimeException('ZaloPay chưa được cấu hình.');
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
