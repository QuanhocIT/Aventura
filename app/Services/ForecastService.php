<?php

namespace App\Services;

use App\Models\RestaurantRevenueSummary;
use Carbon\Carbon;

class ForecastService
{
    /**
     * Dự báo doanh thu cho ngày mai bằng Seasonal Moving Average.
     * Lấy 4 tuần gần nhất cùng ngày trong tuần, tính trung bình có trọng số.
     */
    private ?array $cachedForecast = null;

    private function getAiRevenueForecast(int $restaurantId): array
    {
        if ($this->cachedForecast !== null) {
            return $this->cachedForecast;
        }

        $cacheKey = "restaurant_{$restaurantId}_revenue_forecast_data";
        $this->cachedForecast = \Illuminate\Support\Facades\Cache::remember($cacheKey, 7200, function () use ($restaurantId) {
            $historicals = RestaurantRevenueSummary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('summary_type', 'daily')
                ->where('summary_date', '<', today())
                ->orderBy('summary_date')
                ->take(30)
                ->get();

            $historyPayload = [];
            foreach ($historicals as $r) {
                $historyPayload[] = [
                    'date' => $r->summary_date instanceof Carbon ? $r->summary_date->toDateString() : Carbon::parse($r->summary_date)->toDateString(),
                    'net_revenue' => (float)$r->net_revenue
                ];
            }

            $url = config('services.analytics.url') . '/api/analytics/revenue-forecast';
            $isOffline = \Illuminate\Support\Facades\Cache::has('analytics_service_offline');

            if (!$isOffline) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(3)
                        ->post($url, [
                            'history' => $historyPayload
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['tomorrow']) && isset($data['next_7_days'])) {
                            return $data;
                        }
                    }
                } catch (\Throwable $e) {
                    // Cache the offline status for 5 minutes
                    \Illuminate\Support\Facades\Cache::put('analytics_service_offline', true, 300);
                }
            }

            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $dayOfWeekField = $driver === 'sqlite'
                ? "CAST(strftime('%w', summary_date) AS INTEGER) + 1"
                : "DAYOFWEEK(summary_date)";

            $tomorrow = Carbon::tomorrow();
            $dayOfWeek = $tomorrow->dayOfWeek;

            $weeklyHist = RestaurantRevenueSummary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('summary_type', 'daily')
                ->where('summary_date', '<', today())
                ->whereRaw("{$dayOfWeekField} = ?", [$dayOfWeek + 1])
                ->orderByDesc('summary_date')
                ->take(8)
                ->get();

            if ($weeklyHist->isEmpty()) {
                return [
                    'tomorrow' => [
                        'amount' => 0,
                        'confidence' => 'no_data',
                        'confidence_label' => 'Chưa đủ dữ liệu (Laravel Fallback)',
                        'trend_factor' => 1.0
                    ],
                    'next_7_days' => []
                ];
            }

            $totalWeight = 0;
            $weightedSum = 0;
            foreach ($weeklyHist as $i => $record) {
                $weight = 1 / ($i + 1);
                $weightedSum += (float) $record->net_revenue * $weight;
                $totalWeight += $weight;
            }

            $forecast = $totalWeight > 0 ? round($weightedSum / $totalWeight) : 0;
            $trendFactor = $this->weeklyTrendFactor($restaurantId);
            $forecast = (int) round($forecast * $trendFactor);

            $count = $weeklyHist->count();
            $confidence = $count >= 4 ? 'high' : ($count >= 2 ? 'medium' : 'low');
            $confidenceLabel = ($confidence === 'high' ? 'Cao' : ($confidence === 'medium' ? 'Trung bình' : 'Thấp')) . ' (Laravel Fallback)';

            $next7Days = [];
            for ($i = 1; $i <= 7; $i++) {
                $target = Carbon::today()->addDays($i);
                $targetDayOfWeek = $target->dayOfWeek;

                $dayHist = RestaurantRevenueSummary::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('summary_type', 'daily')
                    ->where('summary_date', '<', today())
                    ->whereRaw("{$dayOfWeekField} = ?", [$targetDayOfWeek + 1])
                    ->orderByDesc('summary_date')
                    ->take(4)
                    ->get();

                $avg = $dayHist->isEmpty() ? 0 : $dayHist->avg(fn ($r) => (float) $r->net_revenue);
                $next7Days[] = [
                    'date' => $target->format('d/m'),
                    'revenue' => (int) round($avg * $trendFactor),
                    'is_forecast' => true
                ];
            }

            return [
                'tomorrow' => [
                    'amount' => $forecast,
                    'confidence' => $confidence,
                    'confidence_label' => $confidenceLabel,
                    'trend_factor' => round($trendFactor, 2)
                ],
                'next_7_days' => $next7Days
            ];
        });

        return $this->cachedForecast;
    }

    public function forecastTomorrow(int $restaurantId): array
    {
        $tomorrow = Carbon::tomorrow();
        $forecast = $this->getAiRevenueForecast($restaurantId);

        return [
            'amount'           => $forecast['tomorrow']['amount'],
            'confidence'       => $forecast['tomorrow']['confidence'],
            'confidence_label' => $forecast['tomorrow']['confidence_label'],
            'samples'          => 30,
            'day_label'        => $tomorrow->locale('vi')->isoFormat('dddd, D/M'),
            'trend_factor'     => $forecast['tomorrow']['trend_factor'],
        ];
    }

    public function forecast7Days(int $restaurantId): array
    {
        $forecast = $this->getAiRevenueForecast($restaurantId);
        return $forecast['next_7_days'];
    }

    /**
     * Tính hệ số xu hướng: tuần này / tuần trước.
     * Trả về giá trị trong [0.7, 1.3] để tránh outlier.
     */
    private function weeklyTrendFactor(int $restaurantId): float
    {
        $thisWeek = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [today()->startOfWeek(), today()])
            ->sum('net_revenue');

        $lastWeek = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()])
            ->sum('net_revenue');

        if ($lastWeek <= 0) return 1.0;

        $factor = (float) $thisWeek / (float) $lastWeek;
        return max(0.7, min(1.3, $factor));
    }
}
