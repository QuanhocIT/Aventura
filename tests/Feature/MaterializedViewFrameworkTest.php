<?php

namespace Tests\Feature;

use App\Jobs\RefreshMaterializedViewJob;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantRevenueSummary;
use App\Services\DailyReportService;
use App\Services\MaterializedViewRefresher;
use App\Support\MaterializedViews\MaterializedViewReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kiểm tra khung Materialized View chung (config/materialized_views.php +
 * MaterializedViewRefresher + MaterializedViewReader) qua view 'revenue_daily'
 * — view đầu tiên đấu nối vào khung, tận dụng DailyReportService sẵn có.
 */
class MaterializedViewFrameworkTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['status' => 'active']);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_amount' => 100000,
            'discount_amount' => 0,
        ]);
    }

    public function test_refresh_is_idempotent(): void
    {
        $refresher = app(MaterializedViewRefresher::class);

        $refresher->refresh('revenue_daily', $this->restaurant->id);
        $first = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurant->id)
            ->first();

        $this->assertNotNull($first);
        $this->assertEquals(100000, (float) $first->net_revenue);

        // Chạy lại lần 2 — phải vẫn đúng 1 dòng (unique key), calculated_at được cập nhật.
        usleep(1_100_000); // đảm bảo mốc giây khác nhau để phân biệt calculated_at
        $refresher->refresh('revenue_daily', $this->restaurant->id);

        $rows = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurant->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->calculated_at->gt($first->calculated_at));
    }

    public function test_reader_with_fresh_row_does_not_query_orders_table(): void
    {
        app(MaterializedViewRefresher::class)->refresh('revenue_daily', $this->restaurant->id);

        $queriedOrders = false;
        DB::listen(function ($query) use (&$queriedOrders) {
            if (str_contains($query->sql, '"orders"') || str_contains($query->sql, '`orders`')) {
                $queriedOrders = true;
            }
        });

        $data = app(MaterializedViewReader::class)->read('revenue_daily', $this->restaurant->id);

        $this->assertFalse($queriedOrders, 'Reader không được đụng bảng orders khi dòng tổng hợp còn tươi.');
        $this->assertEquals(100000, (float) $data['net_revenue']);
    }

    public function test_reader_falls_back_to_live_query_and_self_heals_when_row_missing(): void
    {
        Queue::fake();

        // Chưa từng refresh — không có dòng tổng hợp nào.
        $this->assertDatabaseCount('restaurant_revenue_summaries', 0);

        $data = app(MaterializedViewReader::class)->read('revenue_daily', $this->restaurant->id);

        // Vẫn trả đúng dữ liệu (qua live query trong builder), trang không bị hỏng.
        $this->assertEquals(100000, (float) $data['net_revenue']);

        // Và tự kích hoạt job nền để lần sau có dòng tổng hợp sẵn.
        Queue::assertPushed(RefreshMaterializedViewJob::class, function (RefreshMaterializedViewJob $job) {
            return $job->view === 'revenue_daily' && $job->restaurantId === $this->restaurant->id;
        });
    }

    /**
     * Kịch bản chính xác từng khiến UpdateIntradaySummaryJob (mỗi 15 phút) gọi
     * DailyReportService::generateForRestaurant() lặp lại nhiều lần/ngày — dead
     * code trong Kernel.php cũ nên chưa từng bị bắt lỗi. Xem ghi chú tại
     * DailyReportService::upsertSummary() về lỗi UniqueConstraintViolationException
     * chỉ lộ ra trên SQLite (bộ test mặc định), không lộ trên MySQL (production).
     */
    public function test_generate_for_restaurant_is_idempotent_when_called_repeatedly_same_day(): void
    {
        $service = app(DailyReportService::class);
        $today = now()->toDateString();

        $service->generateForRestaurant($this->restaurant->id, $today);
        $service->generateForRestaurant($this->restaurant->id, $today);
        $service->generateForRestaurant($this->restaurant->id, $today);

        $rows = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurant->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertEquals(100000, (float) $rows->first()->net_revenue);
    }
}
