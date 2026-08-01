<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class VietQrDriver implements PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string
    {
        $restaurant = Restaurant::find($order->restaurant_id);
        $bank = $restaurant->qr_bank_id ?? config('services.sepay.bank');
        $account = $restaurant->qr_account_number ?? config('services.sepay.account_number');
        $template = config('services.sepay.qr_template', 'compact');

        $amount = (int) $order->total_amount;
        $info = 'AVTORD'.$order->id;

        return "https://img.vietqr.io/image/{$bank}-{$account}-{$template}.png?amount={$amount}&addInfo={$info}";
    }

    public function verifyCallback(Request $request): PaymentCallbackResult
    {
        $content = $request->input('content', '').$request->input('description', '');

        if (preg_match('/AVTORD(\d+)/i', $content, $matches)) {
            return new PaymentCallbackResult(
                success: true,
                orderId: (int) $matches[1],
                transactionCode: $request->input('transaction_code') ?? $request->input('referenceNumber'),
            );
        }

        return new PaymentCallbackResult(success: false, orderId: null, transactionCode: null, error: 'Invalid reference');
    }

    public function getDisplayName(): string
    {
        return 'Chuyển khoản ngân hàng (VietQR)';
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.sepay.bank')) && ! empty(config('services.sepay.account_number'));
    }
}
