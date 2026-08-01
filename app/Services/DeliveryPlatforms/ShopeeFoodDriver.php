<?php

namespace App\Services\DeliveryPlatforms;

use App\Models\RestaurantIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver ShopeeFood: webhook ký HMAC-SHA256 hex trên raw body qua header
 * X-Shopee-Signature. Khi chưa có endpoint đẩy trạng thái thật, ghi log demo.
 */
class ShopeeFoodDriver implements DeliveryPlatformDriver
{
    public function getDisplayName(): string
    {
        return 'ShopeeFood';
    }

    public function verifyWebhook(Request $request, RestaurantIntegration $integration): bool
    {
        $secret = $integration->credential('webhook_secret');

        if (! $secret) {
            return false;
        }

        $signature = $request->header('X-Shopee-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseOrder(array $payload): array
    {
        $items = [];

        foreach ($payload['order']['item_list'] ?? [] as $item) {
            $items[] = [
                'name' => (string) ($item['item_name'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => (float) ($item['item_price'] ?? 0),
                'note' => $item['remark'] ?? null,
            ];
        }

        return [
            'platform_order_id' => (string) ($payload['order']['order_sn'] ?? ''),
            'customer_name' => (string) ($payload['order']['buyer']['name'] ?? 'Khách ShopeeFood'),
            'customer_phone' => $payload['order']['buyer']['phone'] ?? null,
            'note' => $payload['order']['remark'] ?? null,
            'items' => $items,
            'total' => (float) ($payload['order']['total_amount'] ?? 0),
        ];
    }

    public function pushOrderStatus(RestaurantIntegration $integration, string $platformOrderId, string $status): bool
    {
        $endpoint = config('services.shopeefood.status_url', '');

        if ($endpoint === '') {
            Log::channel('daily')->info('[SHOPEEFOOD:LOG] Push order status', [
                'platform_order_id' => $platformOrderId,
                'status' => $status,
            ]);

            return true;
        }

        try {
            Http::timeout(10)
                ->withHeaders(['X-Store-Id' => $integration->credential('store_id', '')])
                ->post($endpoint, [
                    'order_sn' => $platformOrderId,
                    'status' => $status,
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[SHOPEEFOOD] Push status failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
