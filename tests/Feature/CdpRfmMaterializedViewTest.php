<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\MaterializedViewRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kiểm tra materialized view 'cdp_rfm' (CdpRfmBuilder) — trang CDP trước đây
 * TỰ ĐỒNG BỘ RFM đồng bộ (chặn request) cho mọi khách hàng chưa tính, ngay cả
 * khi không có khách nào mới, vẫn phải chạy lại toàn bộ aggregation mỗi lần tải trang.
 */
class CdpRfmMaterializedViewTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole($ownerRole);

        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_amount' => 300000,
            'subtotal' => 300000,
            'discount_amount' => 0,
        ]);
    }

    public function test_cdp_page_falls_back_to_live_metrics_when_rollup_missing(): void
    {
        $this->actingAs($this->owner);

        $response = $this->get(route('customers.cdp'));
        $response->assertOk();

        $metrics = $response->original->getData()['page']['props']['metrics'];
        $this->assertEquals(1, $metrics['total_customers']);
        $this->assertEquals(300000.0, $metrics['total_revenue']);
    }

    public function test_cdp_page_reads_materialized_rollup_when_fresh(): void
    {
        app(MaterializedViewRefresher::class)->refresh('cdp_rfm', $this->restaurant->id);

        // Thêm 1 khách + đơn mới SAU khi đã refresh — không refresh lại MV, nên
        // trang phải vẫn trả về số liệu CŨ (1 khách) nếu thật sự đọc rollup.
        $customer2 = Customer::factory()->create(['restaurant_id' => $this->restaurant->id]);
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer2->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_amount' => 999999,
            'subtotal' => 999999,
            'discount_amount' => 0,
        ]);

        $this->actingAs($this->owner);
        $response = $this->get(route('customers.cdp'));
        $response->assertOk();

        $metrics = $response->original->getData()['page']['props']['metrics'];
        $this->assertEquals(1, $metrics['total_customers'], 'Phải đọc rollup đã materialize (1 khách cũ), không quét lại toàn bộ khách hàng mỗi request.');
    }

    public function test_recalculate_refreshes_rollup_immediately(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('customers.cdp.recalculate'));
        $response->assertRedirect();

        $this->assertDatabaseHas('analytics_rollups', [
            'restaurant_id' => $this->restaurant->id,
            'view_key' => 'cdp_rfm',
        ]);
    }
}
