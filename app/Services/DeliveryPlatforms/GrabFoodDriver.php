<?php

namespace App\Services\DeliveryPlatforms;

use App\Models\RestaurantIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver GrabFood theo mô hình GrabFood Partner API: webhook đơn mới ký
 * HMAC-SHA256 trên raw body, đẩy trạng thái qua REST endpoint của partner.
 * Khi chưa có endpoint thật (sandbox), pushOrderStatus ghi log để demo.
 */
class GrabFoodDriver implements DeliveryPlatformDriver
{
    public function getDisplayName(): string
    {
        return 'Grab Food';
    }

    public function verifyWebhook(Request $request, RestaurantIntegration $integration): bool
    {
        $secret = $integration->credential('webhook_secret');

        if (! $secret) {
            return false;
        }

        $signature = $request->header('X-Grab-Signature', '');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $signature);
    }

    public function parseOrder(array $payload): array
    {
        $items = [];

        foreach ($payload['items'] ?? [] as $item) {
            $items[] = [
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => (float) ($item['price'] ?? 0),
                'note' => $item['specialInstructions'] ?? null,
            ];
        }

        return [
            'platform_order_id' => (string) ($payload['orderID'] ?? ''),
            'customer_name' => (string) ($payload['eater']['name'] ?? 'Khách GrabFood'),
            'customer_phone' => $payload['eater']['mobileNumber'] ?? null,
            'note' => $payload['specialInstructions'] ?? null,
            'items' => $items,
            'total' => (float) ($payload['orderTotal'] ?? 0),
        ];
    }

    public function pushOrderStatus(RestaurantIntegration $integration, string $platformOrderId, string $status): bool
    {
        $endpoint = config('services.grabfood.status_url', '');

        if ($endpoint === '') {
            Log::channel('daily')->info('[GRABFOOD:LOG] Push order status', [
                'platform_order_id' => $platformOrderId,
                'status' => $status,
            ]);

            return true;
        }

        try {
            Http::timeout(10)
                ->withToken($integration->credential('webhook_secret', ''))
                ->post($endpoint, [
                    'orderID' => $platformOrderId,
                    'state' => $status,
                    'partnerID' => $integration->credential('partner_id'),
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[GRABFOOD] Push status failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
