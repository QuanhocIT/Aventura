<?php

namespace App\Jobs\Integrations;

use App\Models\WebhookDelivery;
use App\Services\Integrations\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var array<int> backoff giữa các lần retry (giây) */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || ! $delivery->endpoint || $delivery->status === 'success') {
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE);
        $timestamp = now()->timestamp;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Aventura-Event' => $delivery->event,
                    'X-Aventura-Delivery' => (string) $delivery->id,
                    'X-Aventura-Signature' => WebhookDispatchService::sign($endpoint->secret, $body, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'attempts' => $delivery->attempts + 1,
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 500),
                'status' => $response->successful() ? 'success' : 'failed',
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if (! $response->successful()) {
                // Ném exception để queue retry theo backoff
                throw new \RuntimeException("Webhook endpoint trả về HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            if ($delivery->wasChanged() === false) {
                $delivery->update([
                    'attempts' => $delivery->attempts + 1,
                    'status' => 'failed',
                    'response_body' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 600);
            }
        }
    }
}
