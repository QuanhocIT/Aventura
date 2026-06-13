<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TSP Route Optimization — Nearest Neighbor + 2-opt + Or-opt.
 *
 * Algorithm pipeline:
 *   1. Build distance/duration matrix (Google API or Haversine fallback, cached 30 min)
 *   2. Nearest Neighbor greedy construction — O(n²)
 *   3. 2-opt improvement — reverses edge pairs to eliminate crossings
 *   4. Or-opt improvement — relocates 1- and 2-node segments to better positions
 *   5. Cumulative ETA per stop using vehicle-specific speed profile
 */
class RouteOptimizationService
{
    private string $apiKey;

    /** Realistic average city speeds by vehicle type (km/h) */
    private const VEHICLE_SPEEDS = [
        'bike'      => 13.0,
        'motorbike' => 32.0,
        'car'       => 38.0,
    ];

    private const DEFAULT_SPEED      = 28.0;
    private const GOOGLE_MAX_LOCS    = 25;
    private const CACHE_TTL          = 1800; // 30 minutes

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key', '');
    }

    /**
     * Optimize delivery route.
     *
     * @param  array   $stops       Each: {order_id, address, latitude, longitude, ...}
     * @param  array   $origin      {latitude, longitude, address?}
     * @param  string  $vehicleType bike | motorbike | car
     * @return array   Stops in optimized order, appended with {sequence, estimated_arrival_minutes, distance_from_prev_km}
     */
    public function optimizeRoute(array $stops, array $origin, string $vehicleType = 'motorbike'): array
    {
        if (count($stops) === 0) return [];

        if (count($stops) === 1) {
            $d = $this->haversineMeters(
                (float) $origin['latitude'], (float) $origin['longitude'],
                (float) $stops[0]['latitude'], (float) $stops[0]['longitude']
            );
            return [array_merge($stops[0], [
                'sequence'                  => 1,
                'estimated_arrival_minutes' => (int) ceil($d / ($this->speedMs($vehicleType) * 60)),
                'distance_from_prev_km'     => round($d / 1000, 2),
            ])];
        }

        $matrix = $this->buildDistanceMatrix($stops, $origin, $vehicleType);

        // Construction + 3-phase improvement
        $indices = $this->nearestNeighbor($matrix, count($stops));
        $indices = $this->twoOptImprove($indices, $matrix);
        $indices = $this->orOptImprove($indices, $matrix, 1);
        $indices = $this->orOptImprove($indices, $matrix, 2);

        // Build result with cumulative ETA + per-leg distance
        $result = [];
        $cumulativeSec = 0;

        foreach ($indices as $seq => $idx) {
            $leg = $seq === 0
                ? ($matrix['origin_to_stops'][$idx] ?? ['duration' => 0, 'distance' => 0])
                : ($matrix['stops'][$indices[$seq - 1]][$idx] ?? ['duration' => 0, 'distance' => 0]);

            $cumulativeSec += (int) ($leg['duration'] ?? 0);

            $result[] = array_merge($stops[$idx], [
                'sequence'                  => $seq + 1,
                'estimated_arrival_minutes' => (int) ceil($cumulativeSec / 60),
                'distance_from_prev_km'     => round(($leg['distance'] ?? 0) / 1000, 2),
            ]);
        }

        return $result;
    }

    /**
     * Build a Google Maps deep-link using address strings (avoids coordinate geocoding errors).
     */
    public function getGoogleMapsDirectionsUrl(array $origin, array $orderedStops): string
    {
        $resolve = fn (array $p): string => !empty($p['address'])
            ? $p['address']
            : "{$p['latitude']},{$p['longitude']}";

        $waypoints = collect($orderedStops)
            ->slice(0, -1)
            ->map(fn ($s) => urlencode($resolve($s)))
            ->join('|');

        $dest = urlencode($resolve(last($orderedStops)));
        $orig = urlencode($resolve($origin));
        $url  = "https://www.google.com/maps/dir/?api=1&origin={$orig}&destination={$dest}&travelmode=driving";
        if ($waypoints) $url .= "&waypoints={$waypoints}";

        return $url;
    }

    // ─── Distance Matrix ──────────────────────────────────────────────────────

    private function buildDistanceMatrix(array $stops, array $origin, string $vehicleType): array
    {
        // Deterministic cache key from sorted lat/lng list
        $locs = array_merge(
            [[(float) $origin['latitude'], (float) $origin['longitude']]],
            array_map(fn ($s) => [(float) $s['latitude'], (float) $s['longitude']], $stops)
        );
        $cacheKey = 'dlv_dm_' . md5(json_encode($locs));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stops, $origin, $vehicleType) {
            $n = count($stops);
            if (empty($this->apiKey) || ($n + 1) > self::GOOGLE_MAX_LOCS) {
                if (!empty($this->apiKey) && ($n + 1) > self::GOOGLE_MAX_LOCS) {
                    Log::warning('DeliveryRoute: >24 stops, falling back to Haversine.', ['n' => $n]);
                }
                return $this->buildHaversineMatrix($stops, $origin, $vehicleType);
            }
            return $this->buildGoogleMatrix($stops, $origin)
                ?? $this->buildHaversineMatrix($stops, $origin, $vehicleType);
        });
    }

    private function buildGoogleMatrix(array $stops, array $origin): ?array
    {
        $n         = count($stops);
        $originStr = "{$origin['latitude']},{$origin['longitude']}";
        $stopStrs  = array_map(fn ($s) => "{$s['latitude']},{$s['longitude']}", $stops);
        $allLocs   = array_merge([$originStr], $stopStrs);

        try {
            $data = Http::timeout(10)
                ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins'      => implode('|', $allLocs),
                    'destinations' => implode('|', $allLocs),
                    'mode'         => 'driving',
                    'language'     => 'vi',
                    'key'          => $this->apiKey,
                ])->json();

            if (($data['status'] ?? '') !== 'OK') {
                Log::error('Google DM error', ['status' => $data['status'] ?? '?', 'msg' => $data['error_message'] ?? '']);
                return null;
            }

            $originToStops = [];
            for ($i = 0; $i < $n; $i++) {
                $el = $data['rows'][0]['elements'][$i + 1] ?? [];
                $originToStops[$i] = ['distance' => $el['distance']['value'] ?? 0, 'duration' => $el['duration']['value'] ?? 0];
            }

            $stopMatrix = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    $el = $data['rows'][$i + 1]['elements'][$j + 1] ?? [];
                    $stopMatrix[$i][$j] = ['distance' => $el['distance']['value'] ?? 0, 'duration' => $el['duration']['value'] ?? 0];
                }
            }

            return ['origin_to_stops' => $originToStops, 'stops' => $stopMatrix, 'source' => 'google'];
        } catch (\Throwable $e) {
            Log::error('Google DM request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildHaversineMatrix(array $stops, array $origin, string $vehicleType): array
    {
        $n     = count($stops);
        $speed = $this->speedMs($vehicleType);

        $originToStops = [];
        for ($i = 0; $i < $n; $i++) {
            $d = $this->haversineMeters(
                (float) $origin['latitude'],  (float) $origin['longitude'],
                (float) $stops[$i]['latitude'], (float) $stops[$i]['longitude']
            );
            $originToStops[$i] = ['distance' => (int) $d, 'duration' => (int) ($d / $speed)];
        }

        $stopMatrix = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $d = $this->haversineMeters(
                    (float) $stops[$i]['latitude'], (float) $stops[$i]['longitude'],
                    (float) $stops[$j]['latitude'], (float) $stops[$j]['longitude']
                );
                $stopMatrix[$i][$j] = ['distance' => (int) $d, 'duration' => (int) ($d / $speed)];
            }
        }

        return ['origin_to_stops' => $originToStops, 'stops' => $stopMatrix, 'source' => 'haversine'];
    }

    // ─── TSP Algorithms ───────────────────────────────────────────────────────

    /** Greedy nearest-neighbor construction — O(n²). */
    private function nearestNeighbor(array $matrix, int $n): array
    {
        $visited = array_fill(0, $n, false);
        $route   = [];
        $current = -1;

        for ($step = 0; $step < $n; $step++) {
            $bestIdx = -1; $bestT = PHP_INT_MAX;
            for ($j = 0; $j < $n; $j++) {
                if ($visited[$j]) continue;
                $t = $current === -1
                    ? ($matrix['origin_to_stops'][$j]['duration'] ?? PHP_INT_MAX)
                    : ($matrix['stops'][$current][$j]['duration'] ?? PHP_INT_MAX);
                if ($t < $bestT) { $bestT = $t; $bestIdx = $j; }
            }
            $visited[$bestIdx] = true;
            $route[]  = $bestIdx;
            $current  = $bestIdx;
        }

        return $route;
    }

    /** 2-opt: reverse a sub-sequence if it reduces total duration. */
    private function twoOptImprove(array $route, array $matrix): array
    {
        $n = count($route);
        $improved = true;
        while ($improved) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 2; $j < $n; $j++) {
                    $before = $this->edge($matrix, $i === 0 ? -1 : $route[$i - 1], $route[$i])
                            + $this->edge($matrix, $route[$j], $j + 1 < $n ? $route[$j + 1] : -2);
                    $after  = $this->edge($matrix, $i === 0 ? -1 : $route[$i - 1], $route[$j])
                            + $this->edge($matrix, $route[$i], $j + 1 < $n ? $route[$j + 1] : -2);
                    if ($after < $before - 1) {
                        $route = array_merge(
                            array_slice($route, 0, $i),
                            array_reverse(array_slice($route, $i, $j - $i + 1)),
                            array_slice($route, $j + 1)
                        );
                        $improved = true;
                    }
                }
            }
        }
        return $route;
    }

    /**
     * Or-opt: relocate segments of $segLen consecutive nodes.
     * Typically finds 3-8% savings over 2-opt alone for small N.
     */
    private function orOptImprove(array $route, array $matrix, int $segLen): array
    {
        $n = count($route);
        if ($n <= $segLen + 1) return $route;

        $improved = true;
        while ($improved) {
            $improved = false;
            $baseCost = $this->routeCost($route, $matrix);

            for ($i = 0; $i <= $n - $segLen; $i++) {
                $segment = array_slice($route, $i, $segLen);
                $rest    = array_merge(array_slice($route, 0, $i), array_slice($route, $i + $segLen));
                $rLen    = count($rest);

                for ($j = 0; $j <= $rLen; $j++) {
                    if ($j === $i || $j === $i + 1) continue;
                    $candidate = array_merge(
                        array_slice($rest, 0, $j),
                        $segment,
                        array_slice($rest, $j)
                    );
                    $newCost = $this->routeCost($candidate, $matrix);
                    if ($newCost < $baseCost - 1) {
                        $route    = $candidate;
                        $baseCost = $newCost;
                        $improved = true;
                        break 2;
                    }
                }
            }
        }
        return $route;
    }

    private function routeCost(array $route, array $matrix): int
    {
        $cost = 0;
        foreach ($route as $seq => $idx) {
            $cost += $this->edge($matrix, $seq === 0 ? -1 : $route[$seq - 1], $idx);
        }
        return $cost;
    }

    private function edge(array $matrix, int $from, int $to): int
    {
        if ($to === -2) return 0;
        if ($from === -1) return $matrix['origin_to_stops'][$to]['duration'] ?? 0;
        return $matrix['stops'][$from][$to]['duration'] ?? 0;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function speedMs(string $vehicleType): float
    {
        return (self::VEHICLE_SPEEDS[$vehicleType] ?? self::DEFAULT_SPEED) / 3.6;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6_371_000;
        $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
        $dP = deg2rad($lat2 - $lat1); $dL = deg2rad($lng2 - $lng1);
        $a  = sin($dP / 2) ** 2 + cos($p1) * cos($p2) * sin($dL / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
