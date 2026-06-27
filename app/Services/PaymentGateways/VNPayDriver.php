<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;

class VNPayDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        // TODO: Implement khi có VNPay API keys
        // Flow: tạo URL redirect đến VNPay payment page
        // Docs: https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html
        throw new \RuntimeException('VNPay chưa được cấu hình. Vui lòng thêm VNPAY_TMNCODE và VNPAY_HASHSECRET vào .env.');
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        // TODO: Verify VNPay IPN signature
        throw new \RuntimeException('VNPay chưa được cấu hình.');
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
