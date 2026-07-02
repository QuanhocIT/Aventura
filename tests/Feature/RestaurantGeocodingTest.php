<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RestaurantGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.test_enable_geocoding' => true]);
    }

    public function test_restaurant_address_triggers_geocoding_on_creation(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '21.0285',
                    'lon' => '105.8542',
                ]
            ], 200)
        ]);

        $restaurant = Restaurant::factory()->create([
            'address' => 'Hanoi, Vietnam',
            'latitude' => null,
            'longitude' => null,
        ]);

        $restaurant->refresh();

        $this->assertEquals(21.0285, $restaurant->latitude);
        $this->assertEquals(105.8542, $restaurant->longitude);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'nominatim.openstreetmap.org/search') &&
                   $request['q'] === 'Hanoi, Vietnam';
        });
    }

    public function test_restaurant_address_triggers_geocoding_on_update(): void
    {
        $restaurant = Restaurant::factory()->create([
            'address' => 'Old Address',
            'latitude' => 10.0,
            'longitude' => 10.0,
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '10.7769',
                    'lon' => '106.7009',
                ]
            ], 200)
        ]);

        $restaurant->update([
            'address' => 'New Address',
        ]);

        $restaurant->refresh();

        $this->assertEquals(10.7769, $restaurant->latitude);
        $this->assertEquals(106.7009, $restaurant->longitude);
    }

    public function test_geocoding_handles_empty_response_gracefully(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200)
        ]);

        $restaurant = Restaurant::factory()->create([
            'address' => 'Nonexistent Place',
            'latitude' => null,
            'longitude' => null,
        ]);

        $restaurant->refresh();

        $this->assertNull($restaurant->latitude);
        $this->assertNull($restaurant->longitude);
    }
}
