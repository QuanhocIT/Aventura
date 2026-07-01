<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherForecastService
{
    /**
     * Lấy dự báo thời tiết và lượng cầu món ăn dự đoán từ Python Microservice.
     */
    public function getForecast(int $restaurantId): array
    {
        $forecast = $this->fetchWeatherForecast($restaurantId);

        // 2. Lấy dữ liệu bán hàng thực tế 30 ngày qua
        $startDate = now()->subDays(30);
        $sales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', $startDate)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'product_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as total_qty')
            )
            ->groupBy('products.id', 'products.name', 'product_categories.name')
            ->get();

        $salesMap = $sales->keyBy('product_id');

        // 2b. Doanh số trung bình theo danh mục — dùng làm baseline cho sản phẩm mới
        // chưa có lịch sử bán hàng, thay vì con số giả lập theo hash(id).
        $categoryAverages = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', $startDate)
            ->select('products.category_id', DB::raw('SUM(order_items.quantity) / 30.0 as avg_qty'))
            ->groupBy('products.category_id')
            ->get()
            ->keyBy('category_id');

        $restaurantWideAverage = $categoryAverages->isNotEmpty()
            ? $categoryAverages->avg('avg_qty')
            : 1.0;

        // 3. Lấy tất cả sản phẩm đang kinh doanh để tính toán
        $activeProducts = \App\Models\Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with('category')
            ->get();

        $productsData = [];
        foreach ($activeProducts as $p) {
            $avgSales = 0.0;
            if ($salesMap->has($p->id)) {
                $avgSales = (float) $salesMap->get($p->id)->total_qty / 30.0;
            }

            // Sản phẩm mới chưa có doanh số: dùng trung bình của cùng danh mục (nếu có),
            // hoặc trung bình toàn nhà hàng — không còn là con số bịa theo hash(id).
            if ($avgSales < 0.1) {
                $categoryAvg = $categoryAverages->get($p->category_id);
                $avgSales = $categoryAvg ? (float) $categoryAvg->avg_qty : (float) $restaurantWideAverage;
            }

            $productsData[] = [
                'product_id' => $p->id,
                'product_name' => $p->name,
                'category_name' => $p->category?->name ?? 'Món ăn',
                'avg_daily_sales' => round($avgSales, 2)
            ];
        }

        // 4. Gửi sang Python Analytics service
        $url = config('services.analytics.url') . '/api/analytics/weather-menu-forecast';

        try {
            $response = Http::timeout(5)->post($url, [
                'forecast_days' => $forecast['days'],
                'products' => $productsData
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $data['source'] = 'Python Service (FastAPI)';
                $data['weather_data_source'] = $forecast['source'];
                return $data;
            }
        } catch (\Exception $e) {
            Log::warning('Analytics Service is unreachable for weather forecasting: ' . $e->getMessage());
        }

        // Fallback tự phân tích bằng PHP nếu service python ngoại tuyến
        return [
            'success' => true,
            'source' => 'Laravel Fallback (Rules Engine)',
            'weather_data_source' => $forecast['source'],
            'is_degraded' => $forecast['source'] !== 'openweathermap',
            'forecast' => $this->runFallbackForecast($forecast['days'], $productsData)
        ];
    }

    /**
     * Get a real 7-day-ish weather forecast from OpenWeather when the restaurant has GPS
     * coordinates and an API key is configured; otherwise fall back to the rules-based
     * heuristic and say so explicitly via the returned 'source' key, rather than
     * presenting a hash-based fake forecast as if it were real.
     *
     * @return array{days: array, source: string}
     */
    private function fetchWeatherForecast(int $restaurantId): array
    {
        $restaurant = Restaurant::find($restaurantId);
        $apiKey = (string) config('services.openweather.key');

        if (! $restaurant?->latitude || ! $restaurant?->longitude || empty($apiKey)) {
            return ['days' => $this->generateFallbackWeatherDays(), 'source' => 'no_coordinates_or_api_key'];
        }

        // Round to ~1.1km precision so nearby tenants share the same cached forecast and
        // we stay well under OpenWeather's free-tier daily call quota.
        $latKey = round((float) $restaurant->latitude, 2);
        $lngKey = round((float) $restaurant->longitude, 2);
        $cacheKey = "weather_forecast:{$latKey}:{$lngKey}";

        $days = Cache::remember($cacheKey, now()->addHours(3), function () use ($restaurant, $apiKey) {
            try {
                $response = Http::timeout(5)->get(config('services.openweather.url'), [
                    'lat' => $restaurant->latitude,
                    'lon' => $restaurant->longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'vi',
                ]);

                if ($response->successful()) {
                    return $this->parseOpenWeatherResponse($response->json());
                }

                Log::warning('OpenWeather API returned an error response', ['status' => $response->status()]);
            } catch (\Throwable $e) {
                Log::warning('OpenWeather API unreachable: ' . $e->getMessage());
            }

            return null;
        });

        if ($days !== null && count($days) > 0) {
            return ['days' => $this->padToSevenDays($days), 'source' => 'openweathermap'];
        }

        // Don't cache the failure itself — a transient OpenWeather outage should be
        // retried on the next request rather than sticking for the full cache TTL.
        Cache::forget($cacheKey);

        return ['days' => $this->generateFallbackWeatherDays(), 'source' => 'openweather_unavailable'];
    }

    /**
     * OpenWeather's free "5 day / 3 hour forecast" endpoint groups by 3h steps — bucket
     * those into daily entries (using the reading closest to midday as representative)
     * and map OpenWeather's condition vocabulary onto the internal sunny/rainy/cloudy/
     * windy labels the recommendation engine already understands.
     */
    private function parseOpenWeatherResponse(array $response): array
    {
        $byDate = [];

        foreach ($response['list'] ?? [] as $entry) {
            $date = substr($entry['dt_txt'] ?? '', 0, 10);
            if (! $date) {
                continue;
            }

            $hour = (int) substr($entry['dt_txt'], 11, 2);
            $distanceFromMidday = abs(12 - $hour);

            if (! isset($byDate[$date]) || $distanceFromMidday < $byDate[$date]['distance']) {
                $byDate[$date] = [
                    'distance' => $distanceFromMidday,
                    'temperature' => (float) ($entry['main']['temp'] ?? 25),
                    'condition' => $this->mapOpenWeatherCondition($entry['weather'][0]['main'] ?? '', (float) ($entry['wind']['speed'] ?? 0)),
                ];
            }
        }

        return collect($byDate)
            ->map(fn ($day, $date) => [
                'date' => $date,
                'condition' => $day['condition'],
                'temperature' => $day['temperature'],
            ])
            ->values()
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function mapOpenWeatherCondition(string $main, float $windSpeedMs): string
    {
        if ($windSpeedMs >= 8.0) {
            return 'windy';
        }

        return match ($main) {
            'Rain', 'Drizzle', 'Thunderstorm' => 'rainy',
            'Clear' => 'sunny',
            'Clouds', 'Mist', 'Fog', 'Haze' => 'cloudy',
            default => 'cloudy',
        };
    }

    /**
     * The free OpenWeather tier only covers ~5 days; fill any remaining days up to 7
     * using the same rules-based generator as the no-API-key fallback, so callers always
     * get 7 days back, with the tail end simply less precise than the real forecast.
     */
    private function padToSevenDays(array $days): array
    {
        if (count($days) >= 7) {
            return array_slice($days, 0, 7);
        }

        $missing = 7 - count($days);
        $lastDate = end($days)['date'] ?? now()->toDateString();
        $filler = $this->generateFallbackWeatherDays($missing, \Illuminate\Support\Carbon::parse($lastDate)->addDay());

        return array_merge($days, $filler);
    }

    /**
     * Deterministic-but-labeled-as-fallback weather generator, used only when we have no
     * restaurant coordinates, no API key, or OpenWeather is unreachable. Callers must
     * check the 'source' field returned by fetchWeatherForecast() rather than assume this
     * is real data.
     */
    private function generateFallbackWeatherDays(int $count = 7, ?\Illuminate\Support\Carbon $startFrom = null): array
    {
        $conditions = ['sunny', 'rainy', 'cloudy', 'windy'];
        $days = [];
        // Default series starts tomorrow (day+1); when padding after real API days,
        // $startFrom is already the correct next date, so start at offset 0.
        $start = $startFrom ?? now()->addDay();

        for ($i = 0; $i < $count; $i++) {
            $date = $start->copy()->addDays($i);
            $hash = crc32($date->toDateString());
            $cond = $conditions[$hash % count($conditions)];

            $temp = match ($cond) {
                'sunny' => 31.0 + ($hash % 5),
                'rainy' => 20.0 + ($hash % 4),
                'cloudy' => 26.0 + ($hash % 4),
                default => 24.0 + ($hash % 5),
            };

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'condition' => $cond,
                'temperature' => (float) $temp,
            ];
        }

        return $days;
    }

    /**
     * Fallback rules-engine bằng PHP đề phòng service Python offline.
     */
    private function runFallbackForecast(array $forecastDays, array $products): array
    {
        $results = [];

        foreach ($forecastDays as $day) {
            $cond = strtolower($day['condition']);
            $temp = $day['temperature'];
            $recommendations = [];

            foreach ($products as $prod) {
                $cat = strtolower($prod['category_name']);
                $name = strtolower($prod['product_name']);
                $multiplier = 1.0;
                $reason = "";

                $isHot = str_contains($cat, 'lẩu') || str_contains($cat, 'nướng') || str_contains($cat, 'súp') || str_contains($cat, 'soup') || str_contains($name, 'hotpot');
                $isCold = str_contains($cat, 'uống') || str_contains($cat, 'nước') || str_contains($cat, 'bia') || str_contains($cat, 'drink') || str_contains($cat, 'beer') || str_contains($cat, 'kem') || str_contains($name, 'sinh tố');

                if ($cond === 'rainy' || $temp < 22) {
                    if ($isHot) {
                        $multiplier = 1.35;
                        $reason = "Thời tiết mát/mưa ({$temp}°C) làm tăng nhu cầu các món ăn nóng như {$prod['product_name']} (+35%).";
                    } elseif ($isCold) {
                        $multiplier = 0.8;
                        $reason = "Thời tiết mưa lạnh làm giảm nhẹ lượng tiêu thụ đồ uống lạnh {$prod['product_name']} (-20%).";
                    }
                } elseif ($cond === 'sunny' || $temp > 30) {
                    if ($isCold) {
                        $multiplier = 1.45;
                        $reason = "Trời nắng nóng ({$temp}°C) làm tăng đột biến nhu cầu nước giải khát, bia như {$prod['product_name']} (+45%).";
                    } elseif ($isHot) {
                        $multiplier = 0.7;
                        $reason = "Nắng nóng làm giảm sức mua các món nóng như {$prod['product_name']} (-30%).";
                    }
                } elseif ($cond === 'windy') {
                    if ($isHot) {
                        $multiplier = 1.15;
                        $reason = "Trời lộng gió thích hợp dùng món nóng như {$prod['product_name']} (+15%).";
                    }
                }

                if (abs($multiplier - 1.0) > 0.01) {
                    $recommendations[] = [
                        'product_id' => $prod['product_id'],
                        'product_name' => $prod['product_name'],
                        'category_name' => $prod['category_name'],
                        'avg_daily_sales' => $prod['avg_daily_sales'],
                        'predicted_sales' => round($prod['avg_daily_sales'] * $multiplier, 2),
                        'change_pct' => round(($multiplier - 1.0) * 100.0, 1),
                        'suggested_multiplier' => round($multiplier, 2),
                        'reason' => $reason
                    ];
                }
            }

            $results[] = [
                'date' => $day['date'],
                'condition' => $day['condition'],
                'temperature' => $day['temperature'],
                'recommendations' => $recommendations
            ];
        }

        return $results;
    }
}
