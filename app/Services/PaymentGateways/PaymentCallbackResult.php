<?php

namespace App\Services\PaymentGateways;

class PaymentCallbackResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $orderId,
        public readonly ?string $transactionCode,
        public readonly ?string $error = null,
    ) {}
}
