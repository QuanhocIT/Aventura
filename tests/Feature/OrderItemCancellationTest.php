<?php

namespace Tests\Feature;

use App\Events\Kitchen\KitchenItemCancelled;
use App\Models\ApprovalRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderItemCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $cashier;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $cashierRole = Role::findByName('cashier', 'web');
        $managerRole = Role::findByName('manager', 'web');
        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->cashier->assignRole($cashierRole);
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);
    }

    public function test_cashier_can_cancel_unstarted_item_and_kitchen_is_notified(): void
    {
        Event::fake([KitchenItemCancelled::class]);
        [$order, $item] = $this->makeOrderItem('pending');

        $this->actingAs($this->cashier)
            ->post("/orders/{$order->id}/items/{$item->id}/cancel", [
                'reason' => 'Khách bận việc đột xuất',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('cancelled', $item->status);
        $this->assertSame('Khách bận việc đột xuất', $item->cancellation_reason);
        $this->assertNotNull($item->cancelled_at);
        Event::assertDispatched(KitchenItemCancelled::class);
    }

    public function test_started_item_requires_manager_approval_then_is_cancelled(): void
    {
        [$order, $item] = $this->makeOrderItem('preparing');
        $item->update(['started_preparing_at' => now()]);

        $this->actingAs($this->cashier)
            ->post("/orders/{$order->id}/items/{$item->id}/cancel", [
                'reason' => 'Khách đổi món',
            ])
            ->assertRedirect();

        $approval = ApprovalRequest::where('operation_type', 'order_item_cancel')->firstOrFail();
        $this->assertSame('pending', $approval->status);
        $this->assertSame('preparing', $item->fresh()->status);

        $this->actingAs($this->manager)
            ->patch("/approvals/{$approval->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame('cancelled', $item->fresh()->status);
        $this->assertSame('Khách đổi món', $item->fresh()->cancellation_reason);
    }

    public function test_dine_in_order_can_be_converted_to_takeaway(): void
    {
        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'occupied',
        ]);
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $table->id,
            'order_number' => 'TAKEAWAY-TEST-'.uniqid(),
            'channel' => 'dine_in',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 100000,
        ]);

        $this->actingAs($this->cashier)
            ->post("/orders/{$order->id}/convert-to-takeaway")
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('takeaway', $order->channel);
        $this->assertNull($order->table_id);
    }

    /** @return array{0:Order,1:OrderItem} */
    private function makeOrderItem(string $status): array
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'CANCEL-TEST-'.uniqid(),
            'channel' => 'takeaway',
            'status' => $status === 'preparing' ? 'preparing' : 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);
        $product = \App\Models\Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'track_inventory' => false,
        ]);
        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => $status,
            'sent_to_kitchen_at' => now(),
        ]);

        return [$order, $item];
    }
}
