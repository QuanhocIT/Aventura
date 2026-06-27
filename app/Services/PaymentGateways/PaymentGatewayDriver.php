<?php

namespace App\Services\PaymentGateways;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayDriver
{
    public function createPaymentUrl(Order $order, string $returnUrl): string;

    public function verifyCallback(Request $request): PaymentCallbackResult;

    public function getDisplayName(): string;

    public function isConfigured(): bool;
}
