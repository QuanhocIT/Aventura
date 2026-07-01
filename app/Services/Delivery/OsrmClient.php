<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for a self-hosted OSRM (Open Source Routing Machine) server, giving real
 * road-network distances/durations instead of RouteOptimizationService's haversine +
 * fixed-speed-per-vehicle-type heuristic. Every method returns null on any failure
 * (not configured, unreachable, malformed response) so callers can fall back to the
 * haversine heuristic without special-casing exceptions — OSRM is an enhancement, not a
 * hard dependency of the dispatch flow.
 *
 * Requires OSRM_URL to point at a running OSRM instance serving a "driving" profile —
 * see docs/setup/osrm-setup.md for how to provision one (self-hosted, not a SaaS API).
 */
class OsrmClient
{
    private ?string $baseUrl;

    public function __construct()
    {
        $url = (string) config('services.osrm.url', '');
        $this->baseUrl = $url !== '' ? rtrim($url, '/') : null;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null;
    }

    /**
     * Real driving distance/duration for a single A→B segment.
     *
     * @return array{distance_km: float, duration_minutes: float}|null
     */
    public function segmentRoute(float $lat1, float $lng1, float $lat2, float $lng2): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $coords = "{$lng1},{$lat1};{$lng2},{$lat2}";

        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/route/v1/driving/{$coords}", [
                'overview' => 'false',
            ]);

            if (! $response->successful()) {
                Log::warning('OsrmClient: route request failed', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            $route = $data['routes'][0] ?? null;

            if (! $route) {
                return null;
            }

            return [
                'distance_km' => ((float) $route['distance']) / 1000,
                'duration_minutes' => ((float) $route['duration']) / 60,
            ];
        } catch (\Throwable $e) {
            Log::warning('OsrmClient: route request exception — falling back to haversine', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Full distance/duration matrix for a set of points in one call, for route
     * sequencing algorithms (nearestNeighbor/2-opt) that would otherwise need O(n^2)
     * individual segment requests.
     *
     * @param array<int, array{lat: float, lng: float}> $points
     * @return array{distances_km: float[][], durations_minutes: float[][]}|null
     */
    public function distanceMatrix(array $points): ?array
    {
        if (! $this->isConfigured() || count($points) < 2) {
            return null;
        }

        $coords = implode(';', array_map(fn ($p) => "{$p['lng']},{$p['lat']}", $points));

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/table/v1/driving/{$coords}", [
                'annotations' => 'distance,duration',
            ]);

            if (! $response->successful()) {
                Log::warning('OsrmClient: table request failed', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['distances'], $data['durations'])) {
                return null;
            }

            return [
                'distances_km' => array_map(fn ($row) => array_map(fn ($m) => $m / 1000, $row), $data['distances']),
                'durations_minutes' => array_map(fn ($row) => array_map(fn ($s) => $s / 60, $row), $data['durations']),
            ];
        } catch (\Throwable $e) {
            Log::warning('OsrmClient: table request exception — falling back to haversine', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
