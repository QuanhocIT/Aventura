<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoyaltyClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_order_points_summary_and_claim_points(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Nhà Hàng Aventura']);

        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Phở',
            'slug' => 'pho',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $product1 = Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'code' => 'PHO-BO',
            'name' => 'Phở Bò Đặc Biệt',
            'slug' => 'pho-bo-dac-biet',
            'price' => 60000,
            'earn_points' => 40,
            'redeem_points' => 400,
            'is_active' => true,
            'is_available' => true,
        ]);

        $product2 = Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'code' => 'TRA-DA',
            'name' => 'Trà Đá',
            'slug' => 'tra-da',
            'price' => 5000,
            'earn_points' => 5,
            'redeem_points' => 50,
            'is_active' => true,
            'is_available' => true,
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-CLAIM-001',
            'total_amount' => 125000,
            'status' => 'completed',
            // Màn hình tích điểm chỉ mở cho đơn ĐÃ thanh toán (siết 2026-07-30)
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 2, // 2 x 40 = 80 pts
            'unit_price' => 60000,
            'line_total' => 120000,
        ]);

        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1, // 1 x 5 = 5 pts
            'unit_price' => 5000,
            'line_total' => 5000,
        ]);

        // 1. Fetch order summary point notification
        $summaryResponse = $this->getJson(route('customer.loyalty.order-summary', $order->id));
        $summaryResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_earned_points' => 12, // 125,000 VND x 0.0001
            ]);

        // 2. Claim points via quick phone login/registration
        // claim_token lấy từ chính màn hình order-summary ở bước 1 — chống quét order_id.
        $claimResponse = $this->postJson(route('customer.loyalty.claim-points'), [
            'order_id' => $order->id,
            'claim_token' => $summaryResponse->json('claim_token'),
            'phone' => '0912345678',
            'full_name' => 'Nguyễn Văn Khách',
            'password' => 'secret123',
        ]);

        $claimResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_new_customer' => true,
                'points_added' => 12,
            ]);

        // Assert Customer created & tagged with restaurant_id
        $customer = Customer::where('phone', '0912345678')->first();
        $this->assertNotNull($customer);
        $this->assertEquals($restaurant->id, $customer->restaurant_id);
        $this->assertEquals(12, $customer->loyalty_points);

        // Assert User created & assigned 'customer' role
        $user = User::where('phone', '0912345678')->first();
        $this->assertNotNull($user);
        $this->assertEquals($restaurant->id, $user->restaurant_id);
        $this->assertTrue($user->hasRole('customer'));

        // Assert Order updated with customer_id
        $order->refresh();
        $this->assertEquals($customer->id, $order->customer_id);
    }
}
