<?php

namespace App\Jobs;

use App\Models\Restaurant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeRestaurantAddress implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public Restaurant $restaurant) {}

    public function handle(): void
    {
        $restaurant = $this->restaurant;

        if (empty($restaurant->address)) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Aventura/1.0 (contact@aventura.vn)'
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q'      => $restaurant->address,
                'format' => 'json',
                'limit'  => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data[0])) {
                    $restaurant->latitude = (float) $data[0]['lat'];
                    $restaurant->longitude = (float) $data[0]['lon'];
                    $restaurant->saveQuietly();
                    Log::info('GeocodeRestaurantAddress: Geocoding completed successfully.', [
                        'restaurant_id' => $restaurant->id,
                        'address'       => $restaurant->address,
                        'lat'           => $restaurant->latitude,
                        'lon'           => $restaurant->longitude,
                    ]);
                } else {
                    Log::warning('GeocodeRestaurantAddress: Address not found by Nominatim.', [
                        'restaurant_id' => $restaurant->id,
                        'address'       => $restaurant->address
                    ]);
                }
            } else {
                Log::warning('GeocodeRestaurantAddress: Nominatim returned error status.', [
                    'restaurant_id' => $restaurant->id,
                    'status'        => $response->status()
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('GeocodeRestaurantAddress: Failed to fetch coordinates.', [
                'restaurant_id' => $restaurant->id,
                'address'       => $restaurant->address,
                'error'         => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
