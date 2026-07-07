<?php

namespace App\Services\Integrations;

use App\Jobs\Integrations\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Phát sự kiện domain (order.created, order.paid...) tới các webhook endpoint
 * mà developer của nhà hàng đã đăng ký. Việc gửi HTTP thực hiện qua queue
 * (DeliverWebhookJob) với retry + backoff, không chặn request chính.
 */
class WebhookDispatchService
{
    public function dispatch(int $restaurantId, string $event, array $data): void
    {
        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $envelope = [
            'id' => 'evt_'.Str::random(20),
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $envelope,
                'status' => 'pending',
            ]);

            DeliverWebhookJob::dispatch($delivery->id)->onQueue('default');
        }
    }

    /** Gửi sự kiện test tới một endpoint cụ thể — dùng cho nút "Gửi thử" trên UI. */
    public function sendTest(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'ping',
            'payload' => [
                'id' => 'evt_'.Str::random(20),
                'event' => 'ping',
                'created_at' => now()->toIso8601String(),
                'data' => ['message' => 'Aventura webhook test — kết nối thành công!'],
            ],
            'status' => 'pending',
        ]);

        DeliverWebhookJob::dispatchSync($delivery->id);

        return $delivery->fresh();
    }

    /**
     * Chữ ký HMAC-SHA256 theo chuẩn "t=<timestamp>,v1=<hmac>" — receiver ghép
     * "{timestamp}.{body}" và verify bằng secret để chống replay + giả mạo.
     */
    public static function sign(string $secret, string $body, int $timestamp): string
    {
        $hmac = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return "t={$timestamp},v1={$hmac}";
    }
}
