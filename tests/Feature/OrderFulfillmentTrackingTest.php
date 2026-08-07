<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Repositories\OrderRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfillmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_stays_in_kitchen_until_served_and_has_item_progress(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $kitchenUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $kitchenUser->givePermissionTo('manage_kitchen');
        $cashierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $cashierUser->givePermissionTo('process_payments');
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $item = OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);

        $repo = app(OrderRepositoryInterface::class);
        $this->assertTrue($repo->getOrdersQuery($restaurant->id, [
            'status' => 'preparing',
            'branch_id' => $branch->id,
        ])->get()->contains('id', $order->id));

        // A paid order must still be visible to the kitchen.
        $this->actingAs($kitchenUser)
            ->get(route('kitchen.index'))
            ->assertInertia(fn ($page) => $page
                ->component('kitchen/Dashboard')
                ->has('pendingItems', 1)
            );

        $this->actingAs($kitchenUser)
            ->post(route('kitchen.serve', $item))
            ->assertSessionHasErrors('item');

        $this->actingAs($kitchenUser)
            ->post(route('kitchen.prepare', $item))
            ->assertRedirect();

        $this->actingAs($kitchenUser)
            ->get(route('kitchen.index'))
            ->assertInertia(fn ($page) => $page
                ->component('kitchen/Dashboard')
                ->has('completedItems', 1)
            );

        $this->actingAs($cashierUser)
            ->post(route('kitchen.serve', $item))
            ->assertRedirect();

        $this->assertNotNull($item->refresh()->prepared_at);
        $this->assertNotNull($item->served_at);
        $this->assertSame('served', $item->status);
    }

    public function test_payment_is_blocked_until_every_item_is_served(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $cashierUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('chưa được phục vụ');

        app(\App\Services\OrderService::class)->payOrder($order, [
            'payment_method' => 'cash',
            'cash_received' => 50000,
            'change_amount' => 0,
        ], $cashierUser);
    }
}
