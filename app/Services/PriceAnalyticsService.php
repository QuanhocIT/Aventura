<?php

namespace App\Services;

use App\Models\SupplierPriceHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceAnalyticsService
{
    /**
     * Lấy phân tích biến động giá nguyên liệu từ Python Microservice.
     */
    public function analyzePriceHistory(int $supplierId, int $ingredientId): array
    {
        // 1. Fetch histories from database
        $histories = SupplierPriceHistory::where('supplier_id', $supplierId)
            ->where('ingredient_id', $ingredientId)
            ->orderBy('effective_date')
            ->get();

        if ($histories->isEmpty()) {
            return [
                'trend' => 'stable',
                'percentage_change' => 0.0,
                'monthly_averages' => [],
                'recommendation' => 'Không có đủ dữ liệu lịch sử giá để phân tích.',
            ];
        }

        $historyPayload = [];
        foreach ($histories as $history) {
            $historyPayload[] = [
                'date' => $history->effective_date->toDateString(),
                'price' => (float) $history->price,
            ];
        }

        // 2. Call FastAPI
        $baseUrl = config('services.analytics.url');
        $url = "{$baseUrl}/api/analytics/price-analytics";

        return app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $historyPayload) {
                $response = Http::timeout(5)
                    ->withHeaders(app(AnalyticsServiceClient::class)->authHeaders())
                    ->post($url, [
                        'history' => $historyPayload,
                    ]);

                if (! $response->successful()) {
                    Log::warning('PriceAnalyticsService: Python service returned error code '.$response->status());
                    throw new \RuntimeException("Price analytics service trả lỗi HTTP {$response->status()}");
                }

                return $response->json();
            },
            function () use ($historyPayload) {
                return $this->fallbackAnalysis($historyPayload);
            }
        );
    }

    /**
     * Fallback PHP analysis if Python service is offline.
     */
    private function fallbackAnalysis(array $history): array
    {
        if (empty($history)) {
            return [
                'trend' => 'stable',
                'percentage_change' => 0,
                'monthly_averages' => [],
                'recommendation' => 'Không có đủ dữ liệu lịch sử giá để phân tích.',
            ];
        }

        $prices = array_column($history, 'price');
        $firstPrice = $prices[0];
        $lastPrice = end($prices);
        $change = $firstPrice > 0 ? (($lastPrice - $firstPrice) / $firstPrice * 100) : 0;

        // Group by month
        $monthlyGroups = [];
        foreach ($history as $h) {
            $month = substr($h['date'], 0, 7); // YYYY-MM
            $monthlyGroups[$month][] = $h['price'];
        }

        $monthlyAverages = [];
        foreach ($monthlyGroups as $month => $vals) {
            $monthlyAverages[] = [
                'period' => $month,
                'price' => round(array_sum($vals) / count($vals), 2),
            ];
        }

        if ($change > 5) {
            $trend = 'upward';
            $recommendation = 'Giá vật tư tăng mạnh ('.round($change, 1).'%). Đề xuất tăng giá vốn cost_price và xem xét điều chỉnh giá bán [Nguồn: Fallback PHP].';
        } elseif ($change < -5) {
            $trend = 'downward';
            $recommendation = 'Giá vật tư giảm ('.round(abs($change), 1).'%). Có thể giảm giá vốn cost_price để tăng biên lợi nhuận [Nguồn: Fallback PHP].';
        } else {
            $trend = 'stable';
            $recommendation = 'Giá vật tư bình ổn. Không cần điều chỉnh giá vốn [Nguồn: Fallback PHP].';
        }

        return [
            'trend' => $trend,
            'percentage_change' => round($change, 2),
            'monthly_averages' => $monthlyAverages,
            'recommendation' => $recommendation,
        ];
    }
}
