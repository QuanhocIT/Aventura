<?php

namespace Tests\Feature;

use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryDetail;
use App\Models\Delivery\Shipper;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\Delivery\LoadBalancingService;
use App\Services\Delivery\RouteOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage đầu tiên cho module Giao hàng thông minh (TSP routing + cân tải
 * shipper + dispatch batch) — trước đây toàn bộ module này có 0 test.
 */
class DeliveryDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'DELIVERY-A',
        ]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
    }

    private function makeShipper(array $overrides = []): Shipper
    {
        // Employee gắn với 1 User thật — các API PWA (cập nhật trạng thái đơn,
        // GPS ping) yêu cầu người gọi CHÍNH LÀ shipper đó.
        $shipperUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $shipperUser->id,
        ]);

        return Shipper::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'vehicle_type' => 'motorbike',
            'vehicle_plate' => '59X1-'.rand(10000, 99999),
            'is_active' => true,
            'max_orders_per_batch' => 5,
        ], $overrides));
    }

    private function shipperUser(Shipper $shipper): User
    {
        return $shipper->employee->user;
    }

    private function makeDeliveryOrder(float $lat, float $lng, array $orderOverrides = []): Order
    {
        $order = Order::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'created_by' => $this->owner->id,
            'order_number' => 'ORD-DEL-'.uniqid(),
            'channel' => 'delivery',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 150000.0,
            'discount_amount' => 0.0,
            'total_amount' => 150000.0,
        ], $orderOverrides));

        DeliveryDetail::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'customer_name' => 'Khách giao hàng',
            'phone' => '0900000000',
            'address' => 'Địa chỉ test',
            'latitude' => $lat,
            'longitude' => $lng,
            'delivery_fee' => 15000,
            'cod_amount' => 150000,
            'delivery_status' => 'pending',
        ]);

        return $order;
    }

    // ─────────── Thuật toán định tuyến (unit) ───────────

    public function test_haversine_computes_realistic_distance(): void
    {
        $router = app(RouteOptimizationService::class);

        // TP.HCM (10.7769, 106.7009) → Hà Nội (21.0285, 105.8542) ≈ 1.140 km
        $km = $router->haversine(10.7769, 106.7009, 21.0285, 105.8542);

        $this->assertGreaterThan(1100, $km);
        $this->assertLessThan(1200, $km);

        // Cùng 1 điểm → 0 km
        $this->assertEqualsWithDelta(0.0, $router->haversine(10.0, 106.0, 10.0, 106.0), 0.0001);
    }

    public function test_optimize_visits_points_in_geographic_order(): void
    {
        $router = app(RouteOptimizationService::class);

        // 3 điểm nằm trên 1 đường thẳng bắc-nam, xuất phát từ cực nam —
        // lộ trình tối ưu duy nhất là đi lần lượt từ gần đến xa.
        $origin = ['lat' => 10.70, 'lng' => 106.70];
        $points = [
            ['id' => 3, 'lat' => 10.90, 'lng' => 106.70],
            ['id' => 1, 'lat' => 10.75, 'lng' => 106.70],
            ['id' => 2, 'lat' => 10.82, 'lng' => 106.70],
        ];

        $route = $router->optimize($points, $origin);

        $this->assertSame([1, 2, 3], array_column($route, 'id'));
        $this->assertSame([1, 2, 3], array_column($route, 'sequence'));

        // Quãng đường cộng dồn phải tăng dần và có ETA cho từng điểm dừng
        $this->assertGreaterThan(0, $route[0]['cumulative_km']);
        $this->assertGreaterThan($route[0]['cumulative_km'], $route[1]['cumulative_km']);
        $this->assertGreaterThan($route[1]['cumulative_km'], $route[2]['cumulative_km']);
        $this->assertNotEmpty($route[2]['eta']);

        // Tổng ≈ khoảng cách thẳng 10.70 → 10.90 (~22 km), tối ưu không được đi vòng
        $direct = $router->haversine(10.70, 106.70, 10.90, 106.70);
        $this->assertEqualsWithDelta($direct, $router->totalDistanceKm($route), 0.5);
    }

    public function test_optimize_handles_empty_and_single_point(): void
    {
        $router = app(RouteOptimizationService::class);

        $this->assertSame([], $router->optimize([]));

        $single = $router->optimize([['id' => 9, 'lat' => 10.8, 'lng' => 106.7]]);
        $this->assertCount(1, $single);
        $this->assertSame(1, $single[0]['sequence']);
    }

    // ─────────── Cân tải shipper ───────────

    public function test_rank_shippers_prefers_idle_shipper_near_pickup(): void
    {
        $near = $this->makeShipper(['current_lat' => 10.777, 'current_lng' => 106.701, 'last_seen_at' => now()]);
        $far = $this->makeShipper(['current_lat' => 10.95, 'current_lng' => 106.90, 'last_seen_at' => now()]);

        $ranked = app(LoadBalancingService::class)->rankShippers(
            Shipper::with(['employee', 'activeBatch.items'])->get(),
            10.7769,
            106.7009
        );

        $this->assertCount(2, $ranked);
        $this->assertSame($near->id, $ranked[0]['id'], 'Shipper gần điểm lấy hàng phải xếp trên');
        $this->assertSame($far->id, $ranked[1]['id']);
        $this->assertLessThan($ranked[1]['score'], $ranked[0]['score']);
    }

    public function test_rank_shippers_excludes_fully_loaded_shipper(): void
    {
        $full = $this->makeShipper(['max_orders_per_batch' => 1]);

        // Cho shipper 1 batch đang chạy chứa 1 đơn = đầy tải
        $order = $this->makeDeliveryOrder(10.78, 106.70);
        $batch = DeliveryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'shipper_id' => $full->id,
            'created_by' => $this->owner->id,
            'status' => 'dispatched',
            'total_orders' => 1,
        ]);
        $batch->items()->create(['order_id' => $order->id, 'sequence_order' => 1, 'status' => 'pending']);

        $idle = $this->makeShipper();

        $ranked = app(LoadBalancingService::class)->rankShippers(
            Shipper::with(['employee', 'activeBatch.items'])->get(),
            10.7769,
            106.7009
        );

        $this->assertCount(1, $ranked, 'Shipper đầy tải phải bị loại khỏi danh sách gợi ý');
        $this->assertSame($idle->id, $ranked[0]['id']);
    }

    // ─────────── Luồng dispatch qua HTTP (feature) ───────────

    public function test_create_batch_optimizes_sequence_and_assigns_orders(): void
    {
        $shipper = $this->makeShipper(['current_lat' => 10.70, 'current_lng' => 106.70]);

        // Cố tình gửi order_ids theo thứ tự xa → gần; hệ thống phải tự đảo lại
        $farOrder = $this->makeDeliveryOrder(10.90, 106.70);
        $nearOrder = $this->makeDeliveryOrder(10.75, 106.70);

        $response = $this->actingAs($this->owner)->postJson('/delivery/api/batches', [
            'shipper_id' => $shipper->id,
            'order_ids' => [$farOrder->id, $nearOrder->id],
        ]);

        $response->assertStatus(201);

        $batch = DeliveryBatch::first();
        $this->assertNotNull($batch);
        $this->assertSame($this->restaurant->id, (int) $batch->restaurant_id);
        $this->assertSame('pending', $batch->status);
        $this->assertSame(2, (int) $batch->total_orders);

        // Đơn gần hơn phải được xếp đi trước
        $items = $batch->items()->orderBy('sequence_order')->get();
        $this->assertSame($nearOrder->id, (int) $items[0]->order_id);
        $this->assertSame($farOrder->id, (int) $items[1]->order_id);

        // delivery_details chuyển sang assigned
        $this->assertSame(
            2,
            DeliveryDetail::where('delivery_status', 'assigned')->count()
        );
    }

    public function test_full_dispatch_and_delivery_lifecycle(): void
    {
        $shipper = $this->makeShipper();
        $order = $this->makeDeliveryOrder(10.80, 106.72);

        $create = $this->actingAs($this->owner)->postJson('/delivery/api/batches', [
            'shipper_id' => $shipper->id,
            'order_ids' => [$order->id],
        ]);
        $create->assertStatus(201);
        $batch = DeliveryBatch::first();

        // Dispatch: pending → dispatched
        $this->actingAs($this->owner)
            ->postJson("/delivery/api/batches/{$batch->id}/dispatch")
            ->assertOk();

        $batch->refresh();
        $this->assertSame('dispatched', $batch->status);
        $this->assertNotNull($batch->dispatched_at);

        // Shipper (chính chủ) giao xong đơn → batch tự chuyển completed, order completed
        $item = $batch->items()->first();
        $this->actingAs($this->shipperUser($shipper))
            ->postJson("/delivery/api/shipper/items/{$item->id}/status", ['status' => 'delivered'])
            ->assertOk();

        $batch->refresh();
        $order->refresh();

        $this->assertSame('completed', $batch->status);
        $this->assertNotNull($batch->completed_at);
        $this->assertSame('completed', $order->status);
        $this->assertSame(
            'delivered',
            DeliveryDetail::where('order_id', $order->id)->value('delivery_status')
        );
    }

    public function test_cancel_batch_releases_orders_back_to_pending(): void
    {
        $shipper = $this->makeShipper();
        $order = $this->makeDeliveryOrder(10.80, 106.72);

        $this->actingAs($this->owner)->postJson('/delivery/api/batches', [
            'shipper_id' => $shipper->id,
            'order_ids' => [$order->id],
        ])->assertStatus(201);

        $batch = DeliveryBatch::first();

        $this->actingAs($this->owner)
            ->postJson("/delivery/api/batches/{$batch->id}/cancel")
            ->assertOk();

        $batch->refresh();
        $this->assertSame('cancelled', $batch->status);

        // Đơn được trả về pending để dispatch lại cho shipper khác
        $this->assertSame(
            'pending',
            DeliveryDetail::where('order_id', $order->id)->value('delivery_status')
        );
    }

    public function test_cannot_dispatch_batch_of_another_restaurant(): void
    {
        $shipper = $this->makeShipper();
        $order = $this->makeDeliveryOrder(10.80, 106.72);

        $this->actingAs($this->owner)->postJson('/delivery/api/batches', [
            'shipper_id' => $shipper->id,
            'order_ids' => [$order->id],
        ])->assertStatus(201);

        $batch = DeliveryBatch::first();

        $otherRestaurant = Restaurant::factory()->create();
        $otherOwner = User::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'status' => 'active',
        ]);
        $otherOwner->assignRole('owner');
        $otherRestaurant->update(['owner_user_id' => $otherOwner->id]);

        $status = $this->actingAs($otherOwner)
            ->postJson("/delivery/api/batches/{$batch->id}/dispatch")
            ->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertSame('pending', $batch->refresh()->status);
    }

    public function test_create_batch_rejects_orders_of_another_restaurant(): void
    {
        $shipper = $this->makeShipper();

        $otherRestaurant = Restaurant::factory()->create();
        $foreignOrder = Order::create([
            'restaurant_id' => $otherRestaurant->id,
            'order_number' => 'ORD-FOREIGN-DEL',
            'channel' => 'delivery',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 99000.0,
            'total_amount' => 99000.0,
        ]);

        $this->actingAs($this->owner)->postJson('/delivery/api/batches', [
            'shipper_id' => $shipper->id,
            'order_ids' => [$foreignOrder->id],
        ])->assertStatus(422);

        $this->assertSame(0, DeliveryBatch::count());
    }

    public function test_unassigned_orders_endpoint_returns_only_pending_deliveries(): void
    {
        $pending = $this->makeDeliveryOrder(10.78, 106.70);

        // Đơn đã giao xong — không được xuất hiện trong danh sách chờ gán
        $delivered = $this->makeDeliveryOrder(10.79, 106.71);
        DeliveryDetail::where('order_id', $delivered->id)->update(['delivery_status' => 'delivered']);

        $response = $this->actingAs($this->owner)->getJson('/delivery/api/unassigned-orders');

        $response->assertOk();
        $ids = array_column($response->json(), 'id');

        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($delivered->id, $ids);
    }

    public function test_optimize_route_endpoint_returns_route_with_eta(): void
    {
        $orderA = $this->makeDeliveryOrder(10.75, 106.70);
        $orderB = $this->makeDeliveryOrder(10.85, 106.70);

        $response = $this->actingAs($this->owner)->postJson('/delivery/api/optimize-route', [
            'order_ids' => [$orderB->id, $orderA->id],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'route' => [['id', 'sequence', 'cumulative_km', 'eta', 'eta_minutes']],
            'total_distance_km',
            'estimated_duration_minutes',
        ]);
        $this->assertCount(2, $response->json('route'));
    }

    public function test_suggest_shippers_endpoint_ranks_available_shippers(): void
    {
        $this->makeShipper(['current_lat' => 10.777, 'current_lng' => 106.701, 'last_seen_at' => now()]);
        $order = $this->makeDeliveryOrder(10.7769, 106.7009);

        $response = $this->actingAs($this->owner)->postJson('/delivery/api/suggest-shippers', [
            'order_ids' => [$order->id],
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertArrayHasKey('score', $response->json()[0]);
        $this->assertArrayHasKey('available_slots', $response->json()[0]);
    }
}
