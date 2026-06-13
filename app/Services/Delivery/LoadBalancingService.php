<?php

namespace App\Services\Delivery;

use App\Models\Delivery\DeliveryDetail;
use App\Models\Delivery\Shipper;
use Illuminate\Support\Collection;

class LoadBalancingService
{
    /**
     * Score and rank active shippers for a given delivery.
     * Lower score = better candidate.
     *
     * Score = (distance_factor * 0.4) + (orders_load_ratio * 0.4) + (weight_load_ratio * 0.2)
     */
    public function scoreShippers(DeliveryDetail $delivery, Collection $shippers): Collection
    {
        return $shippers
            ->map(function (Shipper $shipper) use ($delivery): array {
                $load = $shipper->getCurrentLoad();
                $latestLoc = $shipper->latestLocation;

                // Distance from shipper's last known location to the delivery pickup (restaurant)
                // If no GPS available, we treat distance as max (penalize)
                $distanceFactor = 1.0;
                if ($latestLoc) {
                    $distanceKm = $this->haversineKm(
                        $latestLoc->latitude, $latestLoc->longitude,
                        $delivery->latitude, $delivery->longitude
                    );
                    // Normalize: 0km = 0.0, 10km+ = 1.0
                    $distanceFactor = min($distanceKm / 10.0, 1.0);
                }

                $ordersRatio = $shipper->max_orders_per_batch > 0
                    ? min($load['orders'] / $shipper->max_orders_per_batch, 1.0)
                    : 1.0;

                $weightRatio = $shipper->max_capacity_kg > 0
                    ? min($load['weight_kg'] / $shipper->max_capacity_kg, 1.0)
                    : 1.0;

                $score = ($distanceFactor * 0.4) + ($ordersRatio * 0.4) + ($weightRatio * 0.2);

                return [
                    'shipper' => $shipper,
                    'score' => round($score, 4),
                    'current_orders' => $load['orders'],
                    'current_weight_kg' => $load['weight_kg'],
                    'available_slots' => max(0, $shipper->max_orders_per_batch - $load['orders']),
                    'distance_factor' => round($distanceFactor, 3),
                    'has_gps' => (bool) $latestLoc,
                    'last_seen_at' => $latestLoc?->logged_at?->toIso8601String(),
                ];
            })
            ->filter(fn ($item) => $item['available_slots'] > 0)
            ->sortBy('score')
            ->values();
    }

    public function getShipperCurrentLoad(Shipper $shipper): array
    {
        return $shipper->getCurrentLoad();
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
