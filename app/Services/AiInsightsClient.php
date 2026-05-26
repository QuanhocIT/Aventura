<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiInsightsClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.email_microservice.url', ''), '/');
    }

    public function getInsights(array $restaurants, array $tenantGrowth): array
    {
        $empty = ['churn_risks' => [], 'health_scores' => [], 'segments' => [], 'mrr_forecast' => []];

        if (empty($this->baseUrl)) {
            return $empty;
        }

        try {
            $response = Http::timeout(3)->post($this->baseUrl . '/ai/insights', [
                'restaurants'   => $restaurants,
                'tenant_growth' => $tenantGrowth,
            ]);

            if ($response->successful()) {
                return $response->json() ?? $empty;
            }

            Log::warning('AiInsightsClient: Python service trả lỗi', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('AiInsightsClient: không kết nối được Python service', ['error' => $e->getMessage()]);
        }

        return $empty;
    }
}
