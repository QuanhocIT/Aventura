<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage cho luồng checkout đặt hàng online (storefront công khai) —
 * trước đây OnlineOrderController có 0 test riêng dù là luồng chạm tiền.
 */
class OnlineOrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private OnlineStoreConfig $config;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $this->config = OnlineStoreConfig::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $this->restaurant->id],
            [
                'slug' => 'quan-test-online',
                'is_active' => true,
                'min_order_amount' => 0,
                'enable_takeaway' => true,
                'enable_delivery' => true,
            ]
        );

        $this->product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cơm tấm sườn',
            'price' => 55000,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Khách Online',
            'phone' => '0911222333',
            'channel' => 'takeaway',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
            'payment_method' => 'cod',
        ], $overrides);
    }

    public function test_storefront_page_renders(): void
    {
        $this->get('/order/quan-test-online')->assertOk();
    }

    public function test_menu_api_returns_active_products(): void
    {
        $response = $this->getJson('/api/online/quan-test-online/menu');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $this->product->id, 'name' => 'Cơm tấm sườn']);
    }

    public function test_guest_can_checkout_cod_takeaway_order(): void
    {
        $response = $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(110000, $response->json('total_amount'));

        $orderNumber = $response->json('order_number');
        $this->assertNotEmpty($orderNumber);

        $order = Order::withoutGlobalScopes()->where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertSame($this->restaurant->id, (int) $order->restaurant_id);
        $this->assertSame('takeaway', $order->channel);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(1, $order->items()->count());

        // Khách được tự tạo hồ sơ Customer theo SĐT trong đúng nhà hàng
        $this->assertNotNull(
            Customer::withoutGlobalScopes()
                ->where('restaurant_id', $this->restaurant->id)
                ->where('phone', '0911222333')
                ->first()
        );
    }

    public function test_checkout_rejects_inactive_store(): void
    {
        $this->config->update(['is_active' => false]);

        $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload())
            ->assertStatus(404);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_checkout_rejects_product_of_another_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignProduct = Product::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'price' => 99000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $response = $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload([
            'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
        ]));

        $response->assertStatus(422);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_checkout_rejects_unavailable_product(): void
    {
        $this->product->update(['is_available' => false]);

        $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload())
            ->assertStatus(422);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_checkout_enforces_minimum_order_amount(): void
    {
        $this->config->update(['min_order_amount' => 200000]);

        $response = $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload([
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('tối thiểu', (string) $response->json('message'));
    }

    public function test_delivery_checkout_requires_address(): void
    {
        $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload([
            'channel' => 'delivery',
        ]))->assertStatus(422);
    }

    public function test_guest_can_track_order_after_checkout(): void
    {
        $checkout = $this->postJson('/api/online/quan-test-online/checkout', $this->checkoutPayload());
        $orderNumber = $checkout->json('order_number');

        $this->get("/order/track/{$orderNumber}")->assertOk();

        $status = $this->getJson("/api/online/order/{$orderNumber}/status");
        $status->assertOk();
    }
}
