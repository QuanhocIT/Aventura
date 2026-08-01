<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Services\SuperAdmin\PlatformMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PlatformMetricsService::cohortAnalysis() giờ ưu tiên gọi analytics_service
 * (endpoint /api/analytics/cohort-retention, tính bằng pandas) qua
 * CircuitBreaker, tự rơi về tính PHP (cohortAnalysisFallback, giữ nguyên logic
 * cũ) khi service gián đoạn — test cả 2 nhánh để đảm bảo tách đúng và không
 * làm hỏng dữ liệu hiển thị trên dashboard SuperAdmin.
 */
class PlatformCohortAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private PlatformMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlatformMetricsService::class);
    }

    public function test_returns_empty_cohorts_immediately_without_calling_http_when_no_restaurants(): void
    {
        Http::fake();

        $result = $this->service->cohortAnalysis(Carbon::now());

        $this->assertCount(6, $result);
        foreach ($result as $cohort) {
            $this->assertSame(0, $cohort['total']);
        }
        Http::assertNothingSent();
    }

    public function test_uses_python_result_when_analytics_service_responds_successfully(): void
    {
        Restaurant::factory()->create(['status' => 'active', 'created_at' => Carbon::now()->subMonth()]);

        Http::fake([
            '*/api/analytics/cohort-retention' => Http::response([
                'cohorts' => [
                    ['cohort' => 'FAKE/2099', 'month' => '2099-01', 'total' => 999, 'm1' => 42.5, 'm3' => null, 'm6' => null],
                ],
            ], 200),
        ]);

        $result = $this->service->cohortAnalysis(Carbon::now());

        // Nếu Python thực sự được dùng, kết quả phải khớp y hệt payload giả lập
        // (không phải kết quả PHP tính song song) — chứng minh nhánh Python chạy.
        $this->assertSame([
            ['cohort' => 'FAKE/2099', 'month' => '2099-01', 'total' => 999, 'm1' => 42.5, 'm3' => null, 'm6' => null],
        ], $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/analytics/cohort-retention')
                && $request->hasHeader('X-Internal-API-Key');
        });
    }

    public function test_falls_back_to_php_computation_when_analytics_service_is_unreachable(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active', 'created_at' => Carbon::now()->subMonth()->startOfMonth()->addDays(3)]);
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'created_at' => Carbon::now()->startOfMonth()->addDays(2),
        ]);

        Http::fake([
            '*/api/analytics/cohort-retention' => Http::response(null, 500),
        ]);

        $result = $this->service->cohortAnalysis(Carbon::now());

        $this->assertCount(6, $result);
        $cohortWithRestaurant = collect($result)->firstWhere('total', 1);
        $this->assertNotNull($cohortWithRestaurant, 'Kết quả fallback PHP phải chứa cohort có nhà hàng vừa tạo.');
    }
}
