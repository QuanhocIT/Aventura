<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherForecastService
{
    /**
     * Lấy dự báo thời tiết và lượng cầu món ăn dự đoán từ Python    public function getForecast(int $restaurantId): array
    {
        return \Illuminate\Support\Facades\Cache::remember("weather_forecast_{$restaurantId}", 3600, function () use ($restaurantId) {
            // 1. Sinh dự báo thời tiết 7 ngày tới (deterministic dựa trên ngày để tránh giật lag)
            $conditions = ['sunny', 'rainy', 'cloudy', 'windy'];
            $forecast = [];
            
            for ($i = 1; $i <= 7; $i++) {
                $date = now()->addDays($i);
                $hash = crc32($date->toDateString());
                $cond = $conditions[$hash % count($conditions)];
                
                $temp = 25.0;
                if ($cond === 'sunny') {
                    $temp = 31.0 + ($hash % 5); // 31°C - 35°C
                } elseif ($cond === 'rainy') {
                    $temp = 20.0 + ($hash % 4); // 20°C - 23°C
                } elseif ($cond === 'cloudy') {
                    $temp = 26.0 + ($hash % 4); // 26°C - 29°C
                } else {
                    $temp = 24.0 + ($hash % 5); // 24°C - 28°C
                }

                $forecast[] = [
                    'date' => $date->format('Y-m-d'),
                    'condition' => $cond,
                    'temperature' => (float)$temp
                ];
            }

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
            $isOffline = \Illuminate\Support\Facades\Cache::has('analytics_service_offline');

            if (!$isOffline) {
                try {
                    $response = Http::timeout(5)->post($url, [
                        'forecast_days' => $forecast,
                        'products' => $productsData
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $data['source'] = 'Python Service (FastAPI)';
                        return $data;
                    }
                } catch (\Exception $e) {
                    Log::warning('Analytics Service is unreachable for weather forecasting: ' . $e->getMessage());
                    // Cache the offline status for 5 minutes
                    \Illuminate\Support\Facades\Cache::put('analytics_service_offline', true, 300);
                }
            }

            // Fallback tự phân tích bằng PHP nếu service python ngoại tuyến
            return [
                'success' => true,
                'source' => 'Laravel Fallback (Rules Engine)',
                'forecast' => $this->runFallbackForecast($forecast, $productsData)
            ];
        });
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
