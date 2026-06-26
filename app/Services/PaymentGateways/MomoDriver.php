<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;

class MomoDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        // TODO: Implement khi có Momo API keys
        // Flow: POST to Momo API → nhận payUrl → redirect khách
        // Docs: https://developers.momo.vn/v3/docs/payment/api/wallet/onetime
        throw new \RuntimeException('Momo chưa được cấu hình. Vui lòng thêm MOMO_PARTNER_CODE vào .env.');
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        // TODO: Verify Momo IPN signature
        throw new \RuntimeException('Momo chưa được cấu hình.');
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
