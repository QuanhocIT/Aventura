<?php

namespace App\Services\Delivery;

/**
 * K-means++ geographic clustering.
 *
 * Distributes delivery orders into K clusters so each cluster can be
 * assigned to one shipper. K-means++ initialization gives 2-4× better
 * cluster quality than random initialization.
 */
class GeoClusteringService
{
    private const MAX_ITERATIONS = 50;
    private const CONVERGENCE_THRESHOLD_M = 10; // centroid moved < 10 m → converged

    /**
     * Cluster orders into up to $k groups.
     *
     * @param  array  $orders  Each: {id, latitude, longitude, ...}
     * @param  int    $k       Number of clusters (= number of available shippers)
     * @return array  Array of clusters: [{centroid: [lat,lng], order_ids: int[], orders: array[]}]
     */
    public function cluster(array $orders, int $k): array
    {
        $n = count($orders);
        if ($n === 0) return [];
        if ($k <= 1 || $n <= $k) {
            return [['centroid' => $this->centroidOf($orders), 'order_ids' => array_column($orders, 'id'), 'orders' => $orders]];
        }

        $k = min($k, $n);
        $centroids = $this->initKMeansPlusPlus($orders, $k);

        for ($iter = 0; $iter < self::MAX_ITERATIONS; $iter++) {
            // Assignment step
            $clusters = array_fill(0, $k, []);
            foreach ($orders as $order) {
                $nearest = $this->nearestCentroid($order, $centroids);
                $clusters[$nearest][] = $order;
            }

            // Update step — recompute centroids
            $newCentroids = [];
            foreach ($clusters as $i => $clusterOrders) {
                if (empty($clusterOrders)) {
                    // Empty cluster: reinitialize to a random order (prevents degenerate state)
                    $newCentroids[] = [
                        'lat' => $orders[array_rand($orders)]['latitude'],
                        'lng' => $orders[array_rand($orders)]['longitude'],
                    ];
                } else {
                    $newCentroids[] = $this->centroidOf($clusterOrders);
                }
            }

            // Check convergence
            $maxShift = 0;
            foreach ($newCentroids as $i => $c) {
                $shift = $this->haversineMeters(
                    $centroids[$i]['lat'], $centroids[$i]['lng'],
                    $c['lat'], $c['lng']
                );
                $maxShift = max($maxShift, $shift);
            }

            $centroids = $newCentroids;
            if ($maxShift < self::CONVERGENCE_THRESHOLD_M) break;
        }

        // Final assignment
        $finalClusters = array_fill(0, $k, []);
        foreach ($orders as $order) {
            $finalClusters[$this->nearestCentroid($order, $centroids)][] = $order;
        }

        return collect($finalClusters)
            ->filter(fn ($c) => count($c) > 0)
            ->map(fn ($clusterOrders) => [
                'centroid'  => $this->centroidOf($clusterOrders),
                'order_ids' => array_column($clusterOrders, 'id'),
                'orders'    => $clusterOrders,
                'radius_km' => $this->clusterRadiusKm($clusterOrders),
                'count'     => count($clusterOrders),
            ])
            ->sortByDesc('count')
            ->values()
            ->toArray();
    }

    /**
     * K-means++ seeding: first centroid random, each subsequent centroid
     * chosen with probability proportional to D² from nearest existing centroid.
     * This gives much better spread than purely random initialization.
     */
    private function initKMeansPlusPlus(array $orders, int $k): array
    {
        $centroids = [];

        // First centroid: random
        $first = $orders[array_rand($orders)];
        $centroids[] = ['lat' => (float) $first['latitude'], 'lng' => (float) $first['longitude']];

        for ($c = 1; $c < $k; $c++) {
            // Compute D² distances from each point to its nearest centroid
            $distances = [];
            $total = 0.0;
            foreach ($orders as $order) {
                $minD = PHP_FLOAT_MAX;
                foreach ($centroids as $centroid) {
                    $d = $this->haversineMeters((float) $order['latitude'], (float) $order['longitude'], $centroid['lat'], $centroid['lng']);
                    $minD = min($minD, $d);
                }
                $d2 = $minD ** 2;
                $distances[] = $d2;
                $total += $d2;
            }

            if ($total == 0) {
                // All points already at a centroid — just pick any remaining
                $centroids[] = ['lat' => (float) $orders[array_rand($orders)]['latitude'], 'lng' => (float) $orders[array_rand($orders)]['longitude']];
                continue;
            }

            // Sample proportional to D²
            $r = (float) random_int(0, PHP_INT_MAX) / PHP_INT_MAX * $total;
            $cumulative = 0.0;
            foreach ($orders as $i => $order) {
                $cumulative += $distances[$i];
                if ($cumulative >= $r) {
                    $centroids[] = ['lat' => (float) $order['latitude'], 'lng' => (float) $order['longitude']];
                    break;
                }
            }
        }

        return $centroids;
    }

    private function nearestCentroid(array $order, array $centroids): int
    {
        $best = 0;
        $bestD = PHP_FLOAT_MAX;
        foreach ($centroids as $i => $c) {
            $d = $this->haversineMeters((float) $order['latitude'], (float) $order['longitude'], $c['lat'], $c['lng']);
            if ($d < $bestD) {
                $bestD = $d;
                $best = $i;
            }
        }
        return $best;
    }

    private function centroidOf(array $orders): array
    {
        $n = count($orders);
        $latSum = array_sum(array_column($orders, 'latitude'));
        $lngSum = array_sum(array_column($orders, 'longitude'));
        return ['lat' => $latSum / $n, 'lng' => $lngSum / $n];
    }

    /** Approximate cluster radius = mean distance from centroid */
    private function clusterRadiusKm(array $orders): float
    {
        if (count($orders) <= 1) return 0.0;
        $c = $this->centroidOf($orders);
        $total = 0.0;
        foreach ($orders as $o) {
            $total += $this->haversineMeters((float) $o['latitude'], (float) $o['longitude'], $c['lat'], $c['lng']);
        }
        return round($total / count($orders) / 1000, 2);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6_371_000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dL   = deg2rad($lng2 - $lng1);
        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dL / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
