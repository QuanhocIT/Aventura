<?php

namespace App\Services;

use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Services\PaymentGateways\MomoDriver;
use App\Services\PaymentGateways\PaymentCallbackResult;
use App\Services\PaymentGateways\PaymentGatewayDriver;
use App\Services\PaymentGateways\VietQrDriver;
use App\Services\PaymentGateways\VNPayDriver;
use App\Services\PaymentGateways\ZaloPayDriver;
use Illuminate\Http\Request;

class PaymentGatewayService
{
    private array $drivers = [
        'bank_transfer' => VietQrDriver::class,
        'vnpay' => VNPayDriver::class,
        'momo' => MomoDriver::class,
        'zalopay' => ZaloPayDriver::class,
    ];

    public function createPayment(Order $order, string $gateway, string $returnUrl): string
    {
        return $this->resolveDriver($gateway)->createPaymentUrl($order, $returnUrl);
    }

    public function handleCallback(string $gateway, Request $request): PaymentCallbackResult
    {
        return $this->resolveDriver($gateway)->verifyCallback($request);
    }

    public function getAvailableGateways(?int $restaurantId = null): array
    {
        $configured = $this->getConfiguredGateways();

        if ($restaurantId) {
            $config = OnlineStoreConfig::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->first();

            if ($config && $config->accepted_payments) {
                $accepted = $config->accepted_payments;
                $configured = array_filter($configured, fn ($g) => in_array($g['key'], $accepted));
                $configured = array_values($configured);
            }
        }

        return $configured;
    }

    public function getConfiguredGateways(): array
    {
        $configured = [];

        foreach ($this->drivers as $key => $driverClass) {
            $driver = app($driverClass);
            if ($driver->isConfigured()) {
                $configured[] = [
                    'key' => $key,
                    'name' => $driver->getDisplayName(),
                ];
            }
        }

        return $configured;
    }

    private function resolveDriver(string $gateway): PaymentGatewayDriver
    {
        $class = $this->drivers[$gateway] ?? null;

        if (! $class) {
            throw new \InvalidArgumentException("Payment gateway '{$gateway}' không được hỗ trợ.");
        }

        return app($class);
    }
}
