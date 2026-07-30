<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_membership_rank_based_discounts()
    {
        // 1. Setup restaurant, branch, and user
        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 2. Setup Gold Customer
        $goldCustomer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'full_name' => 'Khách Vàng',
            'phone' => '0988776655',
            'membership_level' => 'gold',
            'total_spent' => 3000000.0, // Gold range is 2,000,000 to 5,000,000
            'loyalty_points' => 100,
        ]);

        // Create a prior completed order of 3,000,000 VND so aggregate calculation matches
        Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $goldCustomer->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-GOLD-PRIOR',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 3000000.0,
            'discount_amount' => 0.0,
            'total_amount' => 3000000.0,
            'completed_at' => now()->subDay(),
        ]);

        // 3. Create order for Gold Customer
        $orderService = app(OrderService::class);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $goldCustomer->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-GOLD-TEST',
            'channel' => 'dine_in',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 1000000.0, // 1,000,000 VND
            'discount_amount' => 0.0,
            'total_amount' => 1000000.0,
        ]);

        // 4. Pay the order using OrderService to trigger discount calculation and loyalty points
        $orderService->payOrder($order, [
            'payment_method' => 'cash',
            'cash_received' => 1000000,
        ], $user);

        // 5. Assertions
        $order->refresh();
        
        // Gold gets 5% discount = 50,000 VND on a 1,000,000 VND order
        $this->assertEquals(50000.0, $order->discount_amount);
        $this->assertEquals(950000.0, $order->total_amount);
        $this->assertStringContainsString('[Ưu đãi Hội viên Vàng: -50,000đ]', $order->note);

        // Earned points: 950,000 / 10,000 = 95 points. Total = 100 + 95 = 195 points
        $goldCustomer->refresh();
        $this->assertEquals(195, $goldCustomer->loyalty_points);
        
        // Total spent updated: 3,000,000 + 950,000 = 3,950,000
        $this->assertEquals(3950000.0, (float)$goldCustomer->total_spent);
    }

    public function test_customer_points_redemption_discount()
    {
        // 1. Setup restaurant, branch, and user
        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
        ]);
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 2. Setup Customer with 200 points
        $customer = Customer::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'full_name' => 'Khách Tích Điểm',
            'phone' => '0912345678',
            'membership_level' => 'silver',
            'total_spent' => 500000.0,
            'loyalty_points' => 200,
        ]);

        // 3. Create order
        $orderService = app(OrderService::class);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'order_number' => 'ORD-REDEEM-TEST',
            'channel' => 'dine_in',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000.0,
            'discount_amount' => 0.0,
            'total_amount' => 100000.0,
        ]);

        // 4. Pay order and redeem 150 points (1 point = 100 VND discount -> 150 * 100 = 15,000 VND discount)
        $orderService->payOrder($order, [
            'payment_method' => 'cash',
            'cash_received' => 100000,
            'redeem_points' => 150,
        ], $user);

        // 5. Assertions
        $order->refresh();
        $this->assertEquals(15000.0, $order->discount_amount);
        $this->assertEquals(85000.0, $order->total_amount);
        $this->assertStringContainsString('[Đã quy đổi 150 điểm loyalty thưởng: -15,000đ]', $order->note);

        // Remaining points: 200 - 150 + (85,000/10,000 = 8 points) = 58 points
        $customer->refresh();
        $this->assertEquals(58, $customer->loyalty_points);
    }
}
