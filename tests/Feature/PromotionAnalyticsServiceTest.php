<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PromotionAnalyticsSnapshot;
use App\Models\Restaurant;
use App\Services\PromotionAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_new_and_repeat_customers_correctly(): void
    {
        $restaurant = Restaurant::factory()->create();
        $today = now()->toDateString();

        // Khách A: đơn đầu tiên từng có CÁCH ĐÂY 10 ngày (không giảm giá) -> hôm nay là khách quay lại.
        $customerA = Customer::factory()->create(['restaurant_id' => $restaurant->id]);
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customerA->id,
            'status' => 'completed',
            'discount_amount' => 0,
            'created_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
        ]);
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customerA->id,
            'status' => 'completed',
            'discount_amount' => 20000,
            'created_at' => now(),
            'completed_at' => now(),
        ]);

        // Khách B: đơn hoàn thành đầu tiên và duy nhất là hôm nay, có khuyến mãi -> khách mới.
        $customerB = Customer::factory()->create(['restaurant_id' => $restaurant->id]);
        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customerB->id,
            'status' => 'completed',
            'discount_amount' => 15000,
            'created_at' => now(),
            'completed_at' => now(),
        ]);

        app(PromotionAnalyticsService::class)->calculateDailySnapshot($restaurant->id, $today);

        // Dùng whereDate() thay vì where() so sánh chuỗi trực tiếp: cột snapshot_date có cast
        // 'date' nên khi lưu có thể kèm phần giờ "00:00:00" trên SQLite (không strict-type như
        // MySQL DATE column thật), whereDate() bọc hàm SQL DATE() nên khớp đúng trên cả 2 driver.
        $snapshot = PromotionAnalyticsSnapshot::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('snapshot_date', $today)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(2, $snapshot->total_uses);
        $this->assertSame(2, $snapshot->unique_customers);
        $this->assertSame(1, $snapshot->new_customers_acquired);
        // 1 trong 2 khách là khách cũ quay lại -> repeat_rate = 50%.
        $this->assertEquals(50.0, (float) $snapshot->repeat_rate);
    }

    public function test_dashboard_metrics_expose_new_customer_and_repeat_rate_aggregates(): void
    {
        $restaurant = Restaurant::factory()->create();

        PromotionAnalyticsSnapshot::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'promotion_id' => null,
            'snapshot_date' => now()->toDateString(),
            'total_uses' => 4,
            'total_discount_given' => 100000,
            'total_revenue_with_promo' => 500000,
            'unique_customers' => 4,
            'new_customers_acquired' => 1,
            'repeat_rate' => 75.0,
        ]);

        $metrics = app(PromotionAnalyticsService::class)->getDashboardMetrics(
            $restaurant->id,
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString()
        );

        $this->assertSame(1, $metrics['new_customers_acquired']);
        $this->assertEquals(75.0, $metrics['repeat_rate']);
        $this->assertSame(1, $metrics['daily'][0]['new_customers']);
    }
}
