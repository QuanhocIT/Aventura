<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductLoyaltyPointsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Phở & Bún',
            'slug'          => 'pho-bun',
            'display_order' => 1,
            'status'        => 'active',
        ]);
    }

    public function test_owner_can_set_custom_earn_and_redeem_points_on_product(): void
    {
        $this->actingAs($this->owner);

        // Store Product with 30 earn points and 300 redeem points
        $response = $this->post(route('products.store'), [
            'category_id'   => $this->category->id,
            'name'          => 'Phở Bò Đặc Biệt',
            'price'         => 50000,
            'earn_points'   => 30,
            'redeem_points' => 300,
            'description'   => 'Phở truyền thống gia truyền thơm ngon đậm đà',
        ]);

        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Phở Bò Đặc Biệt')->first();
        $this->assertNotNull($product);
        $this->assertEquals(30, $product->earn_points);
        $this->assertEquals(300, $product->redeem_points);

        // Update Product
        $updateResponse = $this->patch(route('products.update', $product->id), [
            'name'          => 'Phở Bò Tái Lăn',
            'price'         => 55000,
            'description'   => 'Phở bò tái lăn đậm vị',
            'earn_points'   => 50,
            'redeem_points' => 450,
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $product->refresh();

        $this->assertEquals(50, $product->earn_points);
        $this->assertEquals(450, $product->redeem_points);
    }

    public function test_loyalty_service_earns_and_redeems_points_per_product(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id'   => $this->category->id,
            'code'          => 'PHO-01',
            'name'          => 'Phở Bò',
            'slug'          => 'pho-bo',
            'price'         => 50000,
            'earn_points'   => 30,
            'redeem_points' => 300,
            'is_active'     => true,
            'is_available'  => true,
        ]);

        $customer = Customer::create([
            'restaurant_id'  => $this->restaurant->id,
            'full_name'      => 'Khách Hàng Thân Thiết',
            'phone'          => '0988777666',
            'loyalty_points' => 500, // Starts with 500 points
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'order_number'  => 'ORD-TEST-001',
            'total_amount'  => 100000,
            'status'        => 'completed',
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'quantity'      => 2, // 2 x Phở = 2 x 30 = 60 points earned
            'unit_price'    => 50000,
            'line_total'    => 100000,
        ]);

        $loyaltyService = app(LoyaltyService::class);

        // 1. Earn points from order
        $earned = $loyaltyService->earnPoints($customer, $order, 100000);
        $this->assertEquals(60, $earned);
        $customer->refresh();
        $this->assertEquals(560, $customer->loyalty_points);

        // 2. Redeem 1 bowl of Phở (costs 300 points, saves 50,000 VND)
        $discount = $loyaltyService->redeemProductWithPoints($customer, $product, 1, $order);
        $this->assertEquals(50000.0, $discount);

        $customer->refresh();
        $this->assertEquals(260, $customer->loyalty_points); // 560 - 300 = 260
    }
}
