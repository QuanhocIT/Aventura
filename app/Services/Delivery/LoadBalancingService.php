<?php

namespace App\Services\Delivery;

use App\Models\Delivery\Shipper;
use Illuminate\Support\Collection;

class LoadBalancingService
{
    private RouteOptimizationService $router;

    public function __construct(RouteOptimizationService $router)
    {
        $this->router = $router;
    }

    /**
     * Score and rank shippers for a set of order coordinates.
     * Lower score = better candidate.
     * $firstOrderLat/Lng: coordinates of the first stop (pickup point or centroid of orders).
     */
    public function rankShippers(Collection $shippers, float $firstOrderLat, float $firstOrderLng): Collection
    {
        return $shippers
            ->map(function (Shipper $shipper) use ($firstOrderLat, $firstOrderLng) {
                $load = $shipper->getCurrentLoad();

                $distanceFactor = 1.0;
                if ($shipper->current_lat !== null && $shipper->current_lng !== null) {
                    $km = $this->router->haversine(
                        (float) $shipper->current_lat, (float) $shipper->current_lng,
                        $firstOrderLat, $firstOrderLng
                    );
                    // Normalize: 0–20 km range → 0–1
                    $distanceFactor = min($km / 20, 1.0);
                }

                $max          = max(1, (int) $shipper->max_orders_per_batch);
                $orderRatio   = $load['orders'] / $max;

                // Score: distance×0.4 + orderLoad×0.6
                $score = ($distanceFactor * 0.4) + ($orderRatio * 0.6);

                return [
                    'id'             => $shipper->id,
                    'name'           => $shipper->employee?->full_name ?? "Shipper #{$shipper->id}",
                    'vehicle_type'   => $shipper->vehicle_type,
                    'vehicle_plate'  => $shipper->vehicle_plate,
                    'score'          => round($score, 4),
                    'current_orders' => $load['orders'],
                    'available_slots'=> max(0, $max - $load['orders']),
                    'max_orders'     => $max,
                    'has_gps'        => $shipper->hasGps(),
                    'last_seen_at'   => $shipper->last_seen_at?->toISOString(),
                    'distance_factor'=> round($distanceFactor, 4),
                ];
            })
            ->filter(fn ($s) => $s['available_slots'] > 0)
            ->sortBy('score')
            ->values();
    }
}
