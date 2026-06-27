<?php

namespace App\Services\Delivery;

class RouteOptimizationService
{
    /**
     * Haversine great-circle distance in km.
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Nearest-Neighbor TSP heuristic.
     * $points: array of ['id', 'lat', 'lng']
     * Returns ordered array of point IDs.
     */
    public function nearestNeighbor(array $points, ?array $origin = null): array
    {
        if (empty($points)) return [];

        $unvisited = $points;
        $route     = [];

        $current = $origin ?? array_shift($unvisited);

        while (!empty($unvisited)) {
            $best      = null;
            $bestDist  = PHP_FLOAT_MAX;

            foreach ($unvisited as $key => $p) {
                $d = $this->haversine((float) $current['lat'], (float) $current['lng'], (float) $p['lat'], (float) $p['lng']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best     = $key;
                }
            }

            $route[]   = $unvisited[$best];
            $current   = $unvisited[$best];
            unset($unvisited[$best]);
        }

        return $route;
    }

    /**
     * 2-opt improvement — swap edges to eliminate crossings.
     */
    public function twoOpt(array $route): array
    {
        $n       = count($route);
        $improved = true;

        while ($improved) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 2; $j < $n; $j++) {
                    $before = $this->haversine(
                        (float) $route[$i]['lat'],   (float) $route[$i]['lng'],
                        (float) $route[$i + 1]['lat'], (float) $route[$i + 1]['lng']
                    ) + $this->haversine(
                        (float) $route[$j]['lat'], (float) $route[$j]['lng'],
                        (float) ($route[$j + 1]['lat'] ?? $route[0]['lat']),
                        (float) ($route[$j + 1]['lng'] ?? $route[0]['lng'])
                    );

                    $after = $this->haversine(
                        (float) $route[$i]['lat'],   (float) $route[$i]['lng'],
                        (float) $route[$j]['lat'],   (float) $route[$j]['lng']
                    ) + $this->haversine(
                        (float) $route[$i + 1]['lat'], (float) $route[$i + 1]['lng'],
                        (float) ($route[$j + 1]['lat'] ?? $route[0]['lat']),
                        (float) ($route[$j + 1]['lng'] ?? $route[0]['lng'])
                    );

                    if ($after < $before - 0.001) {
                        $route    = array_merge(
                            array_slice($route, 0, $i + 1),
                            array_reverse(array_slice($route, $i + 1, $j - $i)),
                            array_slice($route, $j)
                        );
                        $improved = true;
                    }
                }
            }
        }

        return $route;
    }

    /**
     * Or-opt: relocate single nodes to better positions.
     */
    public function orOpt(array $route): array
    {
        $n       = count($route);
        $improved = true;

        while ($improved) {
            $improved = false;
            for ($i = 0; $i < $n; $i++) {
                $node = $route[$i];
                $prev = $route[($i - 1 + $n) % $n];
                $next = $route[($i + 1) % $n];

                $removeCost = $this->haversine(
                    (float) $prev['lat'], (float) $prev['lng'],
                    (float) $node['lat'], (float) $node['lng']
                ) + $this->haversine(
                    (float) $node['lat'], (float) $node['lng'],
                    (float) $next['lat'], (float) $next['lng']
                ) - $this->haversine(
                    (float) $prev['lat'], (float) $prev['lng'],
                    (float) $next['lat'], (float) $next['lng']
                );

                for ($j = 0; $j < $n; $j++) {
                    if ($j === $i || $j === ($i - 1 + $n) % $n) continue;
                    $a = $route[$j];
                    $b = $route[($j + 1) % $n];

                    $insertCost = $this->haversine(
                        (float) $a['lat'],    (float) $a['lng'],
                        (float) $node['lat'], (float) $node['lng']
                    ) + $this->haversine(
                        (float) $node['lat'], (float) $node['lng'],
                        (float) $b['lat'],    (float) $b['lng']
                    ) - $this->haversine(
                        (float) $a['lat'], (float) $a['lng'],
                        (float) $b['lat'], (float) $b['lng']
                    );

                    if ($insertCost < $removeCost - 0.001) {
                        array_splice($route, $i, 1);
                        $insertAt = $j > $i ? $j : $j + 1;
                        array_splice($route, $insertAt, 0, [$node]);
                        $improved = true;
                        break 2;
                    }
                }
            }
        }

        return $route;
    }

    /**
     * Full pipeline: NN → 2-opt → or-opt.
     * Returns optimized route with cumulative distance and ETA per stop.
     */
    public function optimize(array $points, ?array $origin = null, int $speedKmh = 32): array
    {
        if (count($points) <= 1) {
            return $this->attachEta($points, $origin, $speedKmh);
        }

        $route = $this->nearestNeighbor($points, $origin);

        if (count($route) >= 3) {
            $route = $this->twoOpt($route);
            $route = $this->orOpt($route);
        }

        return $this->attachEta($route, $origin, $speedKmh);
    }

    private function attachEta(array $route, ?array $origin, int $speedKmh): array
    {
        $now             = now();
        $cumulativeKm    = 0;
        $current         = $origin;
        $result          = [];

        foreach ($route as $idx => $point) {
            if ($current !== null) {
                $cumulativeKm += $this->haversine(
                    (float) $current['lat'], (float) $current['lng'],
                    (float) $point['lat'],   (float) $point['lng']
                );
            }

            $minutes = $speedKmh > 0 ? ($cumulativeKm / $speedKmh) * 60 : 0;

            $result[] = array_merge($point, [
                'sequence'         => $idx + 1,
                'cumulative_km'    => round($cumulativeKm, 2),
                'eta'              => $now->copy()->addMinutes((int) $minutes)->toISOString(),
                'eta_minutes'      => (int) $minutes,
            ]);

            $current = $point;
        }

        return $result;
    }

    public function totalDistanceKm(array $optimizedRoute): float
    {
        return (float) ($optimizedRoute[array_key_last($optimizedRoute)]['cumulative_km'] ?? 0);
    }
}
