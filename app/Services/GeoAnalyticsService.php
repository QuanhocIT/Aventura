<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeoAnalyticsService
{
    public function getOrderHeatmap(int $restaurantId, int $days = 30): array
    {
        return Cache::remember("geo_heatmap:{$restaurantId}:{$days}", 300, function () use ($restaurantId, $days) {
            return DB::table('delivery_details')
                ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
                ->where('delivery_details.restaurant_id', $restaurantId)
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
        });
    }

    public function getDeliveryZoneStats(int $restaurantId, int $days = 30): array
    {
        $restaurant = \App\Models\Restaurant::find($restaurantId);
        $rLat = (float) ($restaurant->latitude ?? 0);
        $rLng = (float) ($restaurant->longitude ?? 0);

        if ($rLat === 0.0) {
            return ['zones' => [], 'avg_distance' => 0, 'max_distance' => 0];
        }

        $deliveries = DB::table('delivery_details')
            ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
            ->where('delivery_details.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->where('orders.completed_at', '>=', now()->subDays($days))
            ->whereNotNull('delivery_details.latitude')
            ->select('delivery_details.latitude', 'delivery_details.longitude', 'orders.total_amount', 'delivery_details.delivery_fee')
            ->get();

        $zones = ['0-2km' => 0, '2-5km' => 0, '5-8km' => 0, '8km+' => 0];
        $zoneRevenue = ['0-2km' => 0, '2-5km' => 0, '5-8km' => 0, '8km+' => 0];
        $distances = [];

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

    public function getTopAreas(int $restaurantId, int $days = 30): array
    {
        return DB::table('delivery_details')
            ->join('orders', 'delivery_details.order_id', '=', 'orders.id')
            ->where('delivery_details.restaurant_id', $restaurantId)
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
    }

    public function getBranchSuggestions(int $restaurantId): array
    {
        $heatmap = $this->getOrderHeatmap($restaurantId, 90);
        $restaurant = \App\Models\Restaurant::find($restaurantId);
        $rLat = (float) ($restaurant->latitude ?? 0);
        $rLng = (float) ($restaurant->longitude ?? 0);

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
                'reason' => "Khu vực cách {$dist}km có {$hotspot['count']} đơn hàng (doanh thu " . number_format($hotspot['revenue']) . "đ) trong 90 ngày.",
                'score' => $hotspot['count'] * 10 + ($hotspot['revenue'] / 100000),
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    public function getChannelBreakdown(int $restaurantId, int $days = 30): array
    {
        return DB::table('orders')
            ->where('restaurant_id', $restaurantId)
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
