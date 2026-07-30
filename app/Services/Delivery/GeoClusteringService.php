<?php

namespace App\Services\Delivery;

class GeoClusteringService
{
    private RouteOptimizationService $router;

    public function __construct(RouteOptimizationService $router)
    {
        $this->router = $router;
    }

    /**
     * K-means++ clustering on geo points.
     * $points: [['id' => orderOrDetailId, 'lat' => float, 'lng' => float], ...]
     * Returns array of clusters: [['centroid' => [...], 'order_ids' => [...], 'count' => n, 'radius_km' => float]]
     */
    public function cluster(array $points, int $k): array
    {
        if (empty($points)) {
            return [];
        }
        $k = min($k, count($points));

        $centroids = $this->initCentroidsKmeansPlusPlus($points, $k);
        $clusters = [];
        $maxIter = 50;

        for ($iter = 0; $iter < $maxIter; $iter++) {
            $clusters = array_fill(0, $k, ['points' => []]);

            foreach ($points as $point) {
                $nearest = 0;
                $minDist = PHP_FLOAT_MAX;

                foreach ($centroids as $ci => $c) {
                    $d = $this->router->haversine(
                        (float) $point['lat'], (float) $point['lng'],
                        (float) $c['lat'], (float) $c['lng']
                    );
                    if ($d < $minDist) {
                        $minDist = $d;
                        $nearest = $ci;
                    }
                }

                $clusters[$nearest]['points'][] = $point;
            }

            $newCentroids = [];
            $changed = false;

            foreach ($clusters as $ci => $cluster) {
                if (empty($cluster['points'])) {
                    $newCentroids[] = $centroids[$ci];

                    continue;
                }

                $avgLat = array_sum(array_column($cluster['points'], 'lat')) / count($cluster['points']);
                $avgLng = array_sum(array_column($cluster['points'], 'lng')) / count($cluster['points']);

                if (abs($avgLat - $centroids[$ci]['lat']) > 0.0001 || abs($avgLng - $centroids[$ci]['lng']) > 0.0001) {
                    $changed = true;
                }

                $newCentroids[] = ['lat' => $avgLat, 'lng' => $avgLng];
            }

            $centroids = $newCentroids;

            if (! $changed) {
                break;
            }
        }

        $result = [];
        foreach ($clusters as $ci => $cluster) {
            if (empty($cluster['points'])) {
                continue;
            }

            $centroid = $centroids[$ci];
            $maxR = 0;
            $ids = [];

            foreach ($cluster['points'] as $p) {
                $ids[] = $p['id'];
                $r = $this->router->haversine(
                    (float) $centroid['lat'], (float) $centroid['lng'],
                    (float) $p['lat'], (float) $p['lng']
                );
                if ($r > $maxR) {
                    $maxR = $r;
                }
            }

            $result[] = [
                'centroid' => $centroid,
                'order_ids' => $ids,
                'count' => count($ids),
                'radius_km' => round($maxR, 3),
            ];
        }

        usort($result, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * Suggest batch groupings using k-means++.
     * $maxPerBatch: max orders per batch (shipper capacity).
     */
    public function suggestBatches(array $orders, int $maxPerBatch = 5): array
    {
        $points = [];
        foreach ($orders as $order) {
            if (! isset($order['delivery_detail']['latitude'])) {
                continue;
            }
            $points[] = [
                'id' => $order['id'],
                'lat' => (float) $order['delivery_detail']['latitude'],
                'lng' => (float) $order['delivery_detail']['longitude'],
            ];
        }

        if (empty($points)) {
            return [];
        }

        $k = (int) ceil(count($points) / $maxPerBatch);

        return $this->cluster($points, max(1, $k));
    }

    private function initCentroidsKmeansPlusPlus(array $points, int $k): array
    {
        $centroids = [];
        $n = count($points);

        // Pick first centroid randomly
        $centroids[] = $points[array_rand($points)];

        for ($c = 1; $c < $k; $c++) {
            $distances = [];
            $total = 0;

            foreach ($points as $p) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($centroids as $cent) {
                    $d = $this->router->haversine(
                        (float) $p['lat'], (float) $p['lng'],
                        (float) $cent['lat'], (float) $cent['lng']
                    );
                    if ($d < $minDist) {
                        $minDist = $d;
                    }
                }
                // D² probability weighting
                $distances[] = $minDist ** 2;
                $total += $minDist ** 2;
            }

            // Weighted random selection
            $rand = lcg_value() * $total;
            $accum = 0;
            foreach ($points as $i => $p) {
                $accum += $distances[$i];
                if ($accum >= $rand) {
                    $centroids[] = $p;
                    break;
                }
            }
        }

        return $centroids;
    }
}
