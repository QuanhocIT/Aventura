<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\WeatherForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_estimated_forecast_when_openweather_not_configured(): void
    {
        config()->set('services.openweather.api_key', '');
        config()->set('services.analytics.url', 'http://analytics.test');
        Http::fake(['analytics.test/*' => Http::response('', 500)]);

        $restaurant = Restaurant::factory()->create(['latitude' => 10.77, 'longitude' => 106.70]);

        $result = app(WeatherForecastService::class)->getForecast($restaurant->id);

        $this->assertSame('Laravel Fallback (Rules Engine)', $result['source']);
        $this->assertCount(7, $result['forecast']);
    }

    public function test_fetches_real_forecast_from_openweathermap_and_aggregates_by_day(): void
    {
        config()->set('services.openweather.api_key', 'fake-key');
        config()->set('services.analytics.url', 'http://analytics.test');

        $tomorrow = now()->addDay()->toDateString();
        $dayAfter = now()->addDays(2)->toDateString();

        Http::fake([
            'analytics.test/*' => Http::response('', 500),
            'api.openweathermap.org/*' => Http::response([
                'list' => [
                    [
                        'dt_txt' => $tomorrow . ' 12:00:00',
                        'main' => ['temp' => 33.5],
                        'weather' => [['main' => 'Clear']],
                        'wind' => ['speed' => 2.1],
                    ],
                    [
                        'dt_txt' => $dayAfter . ' 12:00:00',
                        'main' => ['temp' => 24.0],
                        'weather' => [['main' => 'Rain']],
                        'wind' => ['speed' => 3.0],
                    ],
                ],
            ], 200),
        ]);

        $restaurant = Restaurant::factory()->create(['latitude' => 10.77, 'longitude' => 106.70]);

        $result = app(WeatherForecastService::class)->getForecast($restaurant->id);

        $this->assertSame('Laravel Fallback (Rules Engine)', $result['source']);
        $forecast = $result['forecast'];
        $this->assertCount(7, $forecast);

        // Ngày 1 và 2 phải khớp đúng dữ liệu OpenWeatherMap thật (không phải ước tính random).
        $this->assertSame($tomorrow, $forecast[0]['date']);
        $this->assertSame('sunny', $forecast[0]['condition']);
        $this->assertEquals(33.5, $forecast[0]['temperature']);

        $this->assertSame($dayAfter, $forecast[1]['date']);
        $this->assertSame('rainy', $forecast[1]['condition']);

        // Ngày 3-7 (persistence forecast) phải lặp lại điều kiện của ngày cuối cùng có dữ liệu thật.
        for ($i = 2; $i < 7; $i++) {
            $this->assertSame('rainy', $forecast[$i]['condition']);
        }
    }

    public function test_falls_back_to_estimate_when_openweather_api_errors(): void
    {
        config()->set('services.openweather.api_key', 'fake-key');
        config()->set('services.analytics.url', 'http://analytics.test');

        Http::fake([
            'analytics.test/*' => Http::response('', 500),
            'api.openweathermap.org/*' => Http::response(['message' => 'Invalid API key'], 401),
        ]);

        $restaurant = Restaurant::factory()->create(['latitude' => 10.77, 'longitude' => 106.70]);

        $result = app(WeatherForecastService::class)->getForecast($restaurant->id);

        $this->assertCount(7, $result['forecast']);
    }
}
