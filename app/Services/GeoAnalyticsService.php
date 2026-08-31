<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeoAnalyticsService
{
    public function getOrderHeatmap(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $scopeKey = $branchId === null ? 'all' : 'branch:'.$branchId;

        return Cache::remember("geo_heatmap:{$restaurantId}:{$days}:{$scopeKey}", 300, function () use ($restaurantId, $days, $branchId) {
            $data = DB::table('delivery_details')
                ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
                ->where('delivery_details.restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
                ->where('orders.status', 'completed')
                ->where('orders.completed_at', '>=', now()->subDays($days))
                ->whereNotNull('delivery_details.latitude')
                ->whereNotNull('delivery_details.longitude')
                ->select(
                    DB::raw('ROUND(delivery_details.latitude, 3) as lat'),
                    DB::raw('ROUND(delivery_details.longitude, 3) as lng'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(orders.total_amount) as revenue')
                )
                ->groupBy(DB::raw('ROUND(delivery_details.latitude, 3)'), DB::raw('ROUND(delivery_details.longitude, 3)'))
                ->orderByDesc('count')
                ->take(200)
                ->get()
                ->map(fn ($r) => [
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                    'count' => (int) $r->count,
                    'revenue' => (float) $r->revenue,
                ])
                ->all();

            if (empty($data) && (bool) config('services.geo_analytics.demo_fallback', false)) {
                $restaurant = Restaurant::find($restaurantId);
                $rLat = (float) ($restaurant->latitude ?? 10.776889);
                $rLng = (float) ($restaurant->longitude ?? 106.700806);
                if ($rLat === 0.0 || $rLng === 0.0) {
                    $rLat = 10.776889;
                    $rLng = 106.700806;
                }

                $hotspots = [
                    ['lat_offset' => 0.012, 'lng_offset' => 0.015, 'count' => 38, 'revenue' => 5700000],
                    ['lat_offset' => -0.018, 'lng_offset' => -0.012, 'count' => 29, 'revenue' => 4350000],
                    ['lat_offset' => 0.025, 'lng_offset' => -0.022, 'count' => 22, 'revenue' => 3100000],
                    ['lat_offset' => -0.008, 'lng_offset' => 0.028, 'count' => 19, 'revenue' => 2850000],
                    ['lat_offset' => 0.035, 'lng_offset' => 0.042, 'count' => 15, 'revenue' => 2250000],
                    ['lat_offset' => -0.032, 'lng_offset' => 0.018, 'count' => 12, 'revenue' => 1800000],
                    ['lat_offset' => 0.005, 'lng_offset' => -0.007, 'count' => 45, 'revenue' => 6750000],
                    ['lat_offset' => -0.045, 'lng_offset' => -0.038, 'count' => 8, 'revenue' => 1200000],
                    ['lat_offset' => 0.052, 'lng_offset' => -0.015, 'count' => 6, 'revenue' => 900000],
                    ['lat_offset' => -0.022, 'lng_offset' => -0.048, 'count' => 5, 'revenue' => 750000],
                ];

                $factor = max(0.1, min(3.0, $days / 30));
                foreach ($hotspots as $h) {
                    $data[] = [
                        'lat' => $rLat + $h['lat_offset'],
                        'lng' => $rLng + $h['lng_offset'],
                        'count' => (int) round($h['count'] * $factor),
                        'revenue' => (float) round($h['revenue'] * $factor),
                    ];
                }
            }

            return $data;
        });
    }

    public function getDeliveryZoneStats(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $restaurant = Restaurant::find($restaurantId);
        $rLat = (float) ($restaurant->latitude ?? 10.776889);
        $rLng = (float) ($restaurant->longitude ?? 106.700806);
        if ($rLat === 0.0 || $rLng === 0.0) {
            $rLat = 10.776889;
            $rLng = 106.700806;
        }

        $deliveries = DB::table('delivery_details')
            ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
            ->where('delivery_details.restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->where('orders.status', 'completed')
            ->where('orders.completed_at', '>=', now()->subDays($days))
            ->whereNotNull('delivery_details.latitude')
            ->select('delivery_details.latitude', 'delivery_details.longitude', 'orders.total_amount', 'delivery_details.delivery_fee')
            ->get();

        $zones = ['0-2km' => 0, '2-5km' => 0, '5-8km' => 0, '8km+' => 0];
        $zoneRevenue = ['0-2km' => 0, '2-5km' => 0, '5-8km' => 0, '8km+' => 0];
        $distances = [];

        if ($deliveries->isEmpty()) {
            $heatmap = $this->getOrderHeatmap($restaurantId, $days, $branchId);
            $mockDeliveries = [];
            foreach ($heatmap as $h) {
                for ($i = 0; $i < $h['count']; $i++) {
                    $perturbationLat = (rand(-50, 50) / 100000);
                    $perturbationLng = (rand(-50, 50) / 100000);
                    $mockDeliveries[] = (object) [
                        'latitude' => $h['lat'] + $perturbationLat,
                        'longitude' => $h['lng'] + $perturbationLng,
                        'total_amount' => $h['revenue'] / $h['count'],
                        'delivery_fee' => 15000 + (rand(0, 4) * 5000),
                    ];
                }
            }
            $deliveries = collect($mockDeliveries);
        }

        foreach ($deliveries as $d) {
            $dist = $this->haversine($rLat, $rLng, (float) $d->latitude, (float) $d->longitude);
            $distances[] = $dist;

            $zone = match (true) {
                $dist <= 2 => '0-2km',
                $dist <= 5 => '2-5km',
                $dist <= 8 => '5-8km',
                default => '8km+',
            };

            $zones[$zone]++;
            $zoneRevenue[$zone] += (float) $d->total_amount;
        }

        $zoneData = [];
        foreach ($zones as $name => $count) {
            $zoneData[] = [
                'zone' => $name,
                'orders' => $count,
                'revenue' => $zoneRevenue[$name],
                'avg_order' => $count > 0 ? round($zoneRevenue[$name] / $count) : 0,
            ];
        }

        return [
            'zones' => $zoneData,
            'avg_distance' => count($distances) > 0 ? round(array_sum($distances) / count($distances), 1) : 0,
            'max_distance' => count($distances) > 0 ? round(max($distances), 1) : 0,
            'total_deliveries' => count($distances),
        ];
    }

    public function getTopAreas(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $data = DB::table('delivery_details')
            ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
            ->where('delivery_details.restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->where('orders.status', 'completed')
            ->where('orders.completed_at', '>=', now()->subDays($days))
            ->whereNotNull('delivery_details.address')
            ->where('delivery_details.address', '!=', '')
            ->select(
                DB::raw("SUBSTRING_INDEX(delivery_details.address, ',', -1) as area"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(orders.total_amount) as revenue')
            )
            ->groupBy(DB::raw("SUBSTRING_INDEX(delivery_details.address, ',', -1)"))
            ->orderByDesc('orders')
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'area' => trim($r->area),
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ])
            ->all();

        if (empty($data) && (bool) config('services.geo_analytics.demo_fallback', false)) {
            $data = [
                ['area' => 'Quận 1', 'orders' => 45, 'revenue' => 6750000],
                ['area' => 'Quận 3', 'orders' => 38, 'revenue' => 5700000],
                ['area' => 'Bình Thạnh', 'orders' => 29, 'revenue' => 4350000],
                ['area' => 'Quận 2', 'orders' => 22, 'revenue' => 3100000],
                ['area' => 'Phú Nhuận', 'orders' => 19, 'revenue' => 2850000],
                ['area' => 'Quận 7', 'orders' => 15, 'revenue' => 2250000],
                ['area' => 'Tân Bình', 'orders' => 12, 'revenue' => 1800000],
            ];
            $factor = max(0.1, min(3.0, $days / 30));
            foreach ($data as &$d) {
                $d['orders'] = (int) round($d['orders'] * $factor);
                $d['revenue'] = (float) round($d['revenue'] * $factor);
            }
        }

        return $data;
    }

    public function getBranchSuggestions(int $restaurantId, ?int $branchId = null): array
    {
        $heatmap = $this->getOrderHeatmap($restaurantId, 90, $branchId);
        $restaurant = Restaurant::find($restaurantId);
        $rLat = (float) ($restaurant->latitude ?? 10.776889);
        $rLng = (float) ($restaurant->longitude ?? 106.700806);
        if ($rLat === 0.0 || $rLng === 0.0) {
            $rLat = 10.776889;
            $rLng = 106.700806;
        }

        $suggestions = [];

        $farHotspots = collect($heatmap)
            ->filter(fn ($p) => $rLat > 0 && $this->haversine($rLat, $rLng, $p['lat'], $p['lng']) > 5)
            ->sortByDesc('count')
            ->take(3);

        foreach ($farHotspots as $hotspot) {
            $dist = round($this->haversine($rLat, $rLng, $hotspot['lat'], $hotspot['lng']), 1);
            $suggestions[] = [
                'lat' => $hotspot['lat'],
                'lng' => $hotspot['lng'],
                'reason' => "Khu vực cách {$dist}km có {$hotspot['count']} đơn hàng (doanh thu ".number_format($hotspot['revenue']).'đ) trong 90 ngày.',
                'score' => $hotspot['count'] * 10 + ($hotspot['revenue'] / 100000),
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    public function getChannelBreakdown(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $data = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays($days))
            ->select(
                'channel',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('channel')
            ->get()
            ->map(fn ($r) => [
                'channel' => $r->channel,
                'label' => match ($r->channel) {
                    'dine_in' => 'Tại chỗ', 'takeaway' => 'Mang về',
                    'delivery' => 'Giao hàng', 'qr' => 'QR Order',
                    default => $r->channel,
                },
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
            ])
            ->all();

        if (empty($data) && (bool) config('services.geo_analytics.demo_fallback', false)) {
            $data = [
                ['channel' => 'dine_in', 'label' => 'Tại chỗ', 'orders' => 124, 'revenue' => 18600000],
                ['channel' => 'takeaway', 'label' => 'Mang về', 'orders' => 86, 'revenue' => 12900000],
                ['channel' => 'delivery', 'label' => 'Giao hàng', 'orders' => 218, 'revenue' => 32700000],
                ['channel' => 'qr', 'label' => 'QR Order', 'orders' => 48, 'revenue' => 7200000],
            ];
            $factor = max(0.1, min(3.0, $days / 30));
            foreach ($data as &$d) {
                $d['orders'] = (int) round($d['orders'] * $factor);
                $d['revenue'] = (float) round($d['revenue'] * $factor);
            }
        }

        return $data;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
