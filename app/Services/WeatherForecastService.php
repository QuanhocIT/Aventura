<?php

namespace App\Services;

use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherForecastService
{
    /** Tọa độ mặc định (TP.HCM) khi nhà hàng chưa cấu hình GPS. */
    private const DEFAULT_LAT = 10.7626;
    private const DEFAULT_LNG = 106.6602;

    /**
     * Lấy dự báo thời tiết và lượng cầu món ăn dự đoán từ Python Microservice.
     */
    public function getForecast(int $restaurantId): array
    {
        $restaurant = Restaurant::find($restaurantId);

        // 1. Dự báo thời tiết 7 ngày tới — ưu tiên OpenWeatherMap thật, fallback ước tính nếu
        // chưa cấu hình OPENWEATHER_API_KEY hoặc API lỗi.
        $forecast = $this->buildForecastDays((float) ($restaurant?->latitude ?? self::DEFAULT_LAT), (float) ($restaurant?->longitude ?? self::DEFAULT_LNG));

        // 2. Lấy dữ liệu bán hàng thực tế 30 ngày qua
        $startDate = now()->subDays(30);
        $sales = DB::table('order_items_unified')
            ->join('orders_unified', 'order_items_unified.order_id', '=', 'orders_unified.id')
            ->join('products', 'order_items_unified.product_id', '=', 'products.id')
            ->join('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->where('orders_unified.restaurant_id', $restaurantId)
            ->where('orders_unified.status', 'completed')
            ->where('orders_unified.created_at', '>=', $startDate)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'product_categories.name as category_name',
                DB::raw('SUM(order_items_unified.quantity) as total_qty')
            )
            ->groupBy('products.id', 'products.name', 'product_categories.name')
            ->get();

        $salesMap = $sales->keyBy('product_id');

        // 3. Lấy tất cả sản phẩm đang kinh doanh để tính toán
        $activeProducts = \App\Models\Product::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->with('category')
            ->get();

        $productsData = [];
        foreach ($activeProducts as $p) {
            $avgSales = 0.0;
            if ($salesMap->has($p->id)) {
                $avgSales = (float)$salesMap->get($p->id)->total_qty / 30.0;
            }

            // Seed baseline nếu sản phẩm mới tinh chưa có doanh số để hiển thị trực quan
            if ($avgSales < 0.1) {
                $hash = crc32($p->id . $p->name);
                $avgSales = 2.0 + ($hash % 7); // 2 - 8 đơn mỗi ngày
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

        return app(CircuitBreaker::class)->for('analytics_service')->attempt(
            function () use ($url, $forecast, $productsData) {
                $response = Http::timeout(5)->post($url, [
                    'forecast_days' => $forecast,
                    'products' => $productsData
                ]);

                if (!$response->successful()) {
                    throw new \RuntimeException("Weather forecast service trả lỗi HTTP {$response->status()}");
                }

                $data = $response->json();
                $data['source'] = 'Python Service (FastAPI)';

                return $data;
            },
            function () use ($forecast, $productsData) {
                // Fallback tự phân tích bằng PHP nếu service python ngoại tuyến
                return [
                    'success' => true,
                    'source' => 'Laravel Fallback (Rules Engine)',
                    'forecast' => $this->runFallbackForecast($forecast, $productsData)
                ];
            }
        );
    }

    /**
     * Dựng dự báo thời tiết 7 ngày: gọi OpenWeatherMap thật nếu đã cấu hình API key,
     * nối thêm persistence forecast (lặp lại điều kiện ngày cuối) nếu API chỉ trả về
     * ít hơn 7 ngày (gói free thường chỉ có 5 ngày), hoặc dùng ước tính nếu API lỗi/chưa cấu hình.
     */
    private function buildForecastDays(float $lat, float $lng): array
    {
        $realDays = $this->fetchRealForecast($lat, $lng);

        if (empty($realDays)) {
            return $this->buildEstimatedForecast();
        }

        $forecast = array_slice($realDays, 0, 7);
        $last = end($forecast);

        while (count($forecast) < 7) {
            $nextDate = Carbon::parse(end($forecast)['date'])->addDay();
            $forecast[] = [
                'date' => $nextDate->toDateString(),
                'condition' => $last['condition'],
                'temperature' => $last['temperature'],
            ];
        }

        return $forecast;
    }

    /**
     * Gọi OpenWeatherMap /forecast (free tier, 3 giờ/lần trong 5 ngày), gộp theo ngày
     * (lấy mốc 12:00 trưa làm đại diện). Trả về null nếu chưa cấu hình hoặc lỗi —
     * để buildForecastDays() rơi về ước tính thay vì làm hỏng luồng chính.
     */
    private function fetchRealForecast(float $lat, float $lng): ?array
    {
        $apiKey = config('services.openweather.api_key');

        if (empty($apiKey)) {
            return null;
        }

        $cacheKey = 'weather_forecast:' . round($lat, 2) . ':' . round($lng, 2);

        return Cache::remember($cacheKey, now()->addHours(3), function () use ($lat, $lng, $apiKey) {
            try {
                $response = Http::timeout(5)->get(config('services.openweather.url'), [
                    'lat' => $lat,
                    'lon' => $lng,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'vi',
                ]);

                if (!$response->successful()) {
                    Log::warning('WeatherForecastService: OpenWeatherMap trả lỗi', ['status' => $response->status()]);

                    return null;
                }

                $list = $response->json('list', []);

                if (empty($list)) {
                    return null;
                }

                $today = now()->toDateString();
                $byDate = [];

                foreach ($list as $entry) {
                    $date = substr((string) ($entry['dt_txt'] ?? ''), 0, 10);

                    if ($date === '' || $date <= $today) {
                        continue;
                    }

                    $byDate[$date][] = $entry;
                }

                $days = [];
                foreach ($byDate as $date => $entries) {
                    $noon = collect($entries)->first(fn ($e) => str_ends_with((string) ($e['dt_txt'] ?? ''), '12:00:00')) ?? $entries[0];

                    $days[] = [
                        'date' => $date,
                        'condition' => $this->classifyCondition(
                            (string) ($noon['weather'][0]['main'] ?? 'Clouds'),
                            (float) ($noon['wind']['speed'] ?? 0)
                        ),
                        'temperature' => round((float) ($noon['main']['temp'] ?? 27), 1),
                    ];
                }

                return $days;
            } catch (\Throwable $e) {
                Log::warning('WeatherForecastService: lỗi gọi OpenWeatherMap', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Ánh xạ mã điều kiện thời tiết của OpenWeatherMap sang 4 nhóm mà rules-engine
     * gợi ý thực đơn đang dùng (sunny/rainy/cloudy/windy).
     */
    private function classifyCondition(string $main, float $windSpeedMs): string
    {
        // >= 8 m/s (~29 km/h, cấp gió mạnh) được ưu tiên xếp là "windy" bất kể mây/nắng.
        if ($windSpeedMs >= 8.0) {
            return 'windy';
        }

        return match ($main) {
            'Clear' => 'sunny',
            'Rain', 'Drizzle', 'Thunderstorm', 'Snow' => 'rainy',
            'Squall', 'Tornado' => 'windy',
            default => 'cloudy',
        };
    }

    /**
     * Ước tính thời tiết deterministic theo ngày khi chưa cấu hình OPENWEATHER_API_KEY
     * hoặc API lỗi — chỉ dùng làm phương án cuối để tính năng gợi ý thực đơn vẫn chạy được.
     */
    private function buildEstimatedForecast(): array
    {
        $conditions = ['sunny', 'rainy', 'cloudy', 'windy'];
        $forecast = [];

        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i);
            $hash = crc32($date->toDateString());
            $cond = $conditions[$hash % count($conditions)];

            $temp = match (true) {
                $cond === 'sunny' => 31.0 + ($hash % 5),
                $cond === 'rainy' => 20.0 + ($hash % 4),
                $cond === 'cloudy' => 26.0 + ($hash % 4),
                default => 24.0 + ($hash % 5),
            };

            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'condition' => $cond,
                'temperature' => (float) $temp,
            ];
        }

        return $forecast;
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

