<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_build_a_basic_restaurant_sales_graph_with_factories(): void
    {
        $owner = User::factory()->create();

        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);

        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);

        $cashier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);

        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $cashier->id,
            'cashier_user_id' => $cashier->id,
            'subtotal' => 120000,
            'total_amount' => 120000,
            'payment_status' => 'paid',
        ]);

        $item = OrderItem::factory()->create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 60000,
            'line_total' => 120000,
        ]);

        $payment = Payment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'processed_by' => $cashier->id,
            'amount' => 120000,
            'cash_received' => 120000,
        ]);

        $this->assertTrue($order->restaurant->is($restaurant));
        $this->assertCount(1, $order->items);
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($payment->order->is($order));
    }
}
