<?php

namespace Tests\Unit;

use App\Services\Delivery\OsrmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OsrmClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_configured_when_osrm_url_is_empty(): void
    {
        config(['services.osrm.url' => '']);

        $client = new OsrmClient();

        $this->assertFalse($client->isConfigured());
    }

    public function test_segment_route_returns_null_without_http_call_when_not_configured(): void
    {
        config(['services.osrm.url' => '']);
        Http::fake(function () {
            $this->fail('OsrmClient should not make an HTTP request when not configured.');
        });

        $client = new OsrmClient();
        $result = $client->segmentRoute(10.77, 106.70, 10.78, 106.71);

        $this->assertNull($result);
    }

    public function test_segment_route_parses_real_osrm_response(): void
    {
        config(['services.osrm.url' => 'http://localhost:5000']);

        Http::fake([
            'localhost:5000/route/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 5000, 'duration' => 600], // 5km, 10 minutes
                ],
            ], 200),
        ]);

        $client = new OsrmClient();
        $result = $client->segmentRoute(10.77, 106.70, 10.78, 106.71);

        $this->assertNotNull($result);
        $this->assertEquals(5.0, $result['distance_km']);
        $this->assertEquals(10.0, $result['duration_minutes']);
    }

    public function test_segment_route_returns_null_on_unreachable_server(): void
    {
        config(['services.osrm.url' => 'http://localhost:5000']);

        Http::fake([
            'localhost:5000/*' => Http::response(null, 500),
        ]);

        $client = new OsrmClient();
        $result = $client->segmentRoute(10.77, 106.70, 10.78, 106.71);

        $this->assertNull($result);
    }

    public function test_distance_matrix_parses_real_osrm_response(): void
    {
        config(['services.osrm.url' => 'http://localhost:5000']);

        Http::fake([
            'localhost:5000/table/*' => Http::response([
                'code' => 'Ok',
                'distances' => [[0, 1000], [1000, 0]],
                'durations' => [[0, 120], [120, 0]],
            ], 200),
        ]);

        $client = new OsrmClient();
        $result = $client->distanceMatrix([
            ['lat' => 10.77, 'lng' => 106.70],
            ['lat' => 10.78, 'lng' => 106.71],
        ]);

        $this->assertNotNull($result);
        $this->assertEquals(1.0, $result['distances_km'][0][1]);
        $this->assertEquals(2.0, $result['durations_minutes'][0][1]);
    }
}
