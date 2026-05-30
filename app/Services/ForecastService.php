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
    public function forecastTomorrow(int $restaurantId): array
    {
        $tomorrow    = Carbon::tomorrow();
        $dayOfWeek   = $tomorrow->dayOfWeek; // 0=Sun … 6=Sat

        // Lấy tối đa 8 tuần gần nhất cùng ngày trong tuần
        $historicals = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->where('summary_date', '<', today())
            ->get()
            ->filter(fn ($r) => Carbon::parse($r->summary_date)->dayOfWeek === $dayOfWeek)
            ->sortByDesc('summary_date')
            ->take(8)
            ->values();

        if ($historicals->isEmpty()) {
            return [
                'amount'     => 0,
                'confidence' => 'no_data',
                'label'      => 'Chưa đủ dữ liệu',
                'samples'    => 0,
                'day_label'  => $tomorrow->locale('vi')->isoFormat('dddd, D/M'),
            ];
        }

        // Weighted avg: tuần gần nhất có trọng số cao hơn
        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($historicals as $i => $record) {
            $weight       = 1 / ($i + 1); // 1, 0.5, 0.33 …
            $weightedSum += (float) $record->net_revenue * $weight;
            $totalWeight += $weight;
        }

        $forecast = $totalWeight > 0 ? round($weightedSum / $totalWeight) : 0;

        // Hệ số xu hướng tuần này vs tuần trước
        $trendFactor = $this->weeklyTrendFactor($restaurantId);
        $forecast    = (int) round($forecast * $trendFactor);

        $count      = $historicals->count();
        $confidence = match (true) {
            $count >= 4 => 'high',
            $count >= 2 => 'medium',
            default     => 'low',
        };

        $confidenceLabel = match ($confidence) {
            'high'    => 'Cao',
            'medium'  => 'Trung bình',
            default   => 'Thấp',
        };

        return [
            'amount'           => $forecast,
            'confidence'       => $confidence,
            'confidence_label' => $confidenceLabel,
            'samples'          => $count,
            'day_label'        => $tomorrow->locale('vi')->isoFormat('dddd, D/M'),
            'trend_factor'     => round($trendFactor, 2),
        ];
    }

    /**
     * Dự báo 7 ngày tiếp theo (cho chart mở rộng).
     */
    public function forecast7Days(int $restaurantId): array
    {
        $forecasts = [];
        for ($i = 1; $i <= 7; $i++) {
            $target    = Carbon::today()->addDays($i);
            $dayOfWeek = $target->dayOfWeek;

            $historicals = RestaurantRevenueSummary::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('summary_type', 'daily')
                ->where('summary_date', '<', today())
                ->get()
                ->filter(fn ($r) => Carbon::parse($r->summary_date)->dayOfWeek === $dayOfWeek)
                ->sortByDesc('summary_date')
                ->take(4)
                ->values();

            if ($historicals->isEmpty()) {
                $forecasts[] = ['date' => $target->format('d/m'), 'revenue' => 0, 'is_forecast' => true];
                continue;
            }

            $avg = $historicals->avg(fn ($r) => (float) $r->net_revenue);
            $forecasts[] = [
                'date'        => $target->format('d/m'),
                'revenue'     => (int) round($avg * $this->weeklyTrendFactor($restaurantId)),
                'is_forecast' => true,
            ];
        }

        return $forecasts;
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
