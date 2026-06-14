<?php

namespace App\Jobs\Support;

use App\Models\SystemAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchSystemAlertWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alertId) {}

    public function handle(): void
    {
        $alert = SystemAlert::find($this->alertId);

        if (! $alert) {
            return;
        }

        $payload = [
            'text' => sprintf('[%s] %s | %s=%s | threshold=%s', strtoupper($alert->status), $alert->title, $alert->metric_key, $alert->metric_value, $alert->threshold),
            'alert' => [
                'id' => $alert->id,
                'title' => $alert->title,
                'message' => $alert->message,
                'metric_key' => $alert->metric_key,
                'metric_value' => $alert->metric_value,
                'threshold' => $alert->threshold,
                'triggered_at' => optional($alert->triggered_at)?->toIso8601String(),
            ],
        ];

        foreach (array_filter([
            config('services.support_alerts.telegram_webhook'),
            config('services.support_alerts.discord_webhook'),
        ]) as $url) {
            try {
                Http::timeout(5)->post($url, $payload)->throw();
            } catch (\Throwable $e) {
                Log::warning('support_alert_webhook_failed', ['alert_id' => $alert->id, 'url' => $url, 'error' => $e->getMessage()]);
            }
        }
    }
}