<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Unit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Area;
use App\Events\OrderStatusUpdated;
use App\Events\SpamOrderCancelled;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderVerificationAndAntiSpamTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private RestaurantTable $table;
    private Product $product;
    private Ingredient $ingredient;
    private Inventory $inventory;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
        ]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Branch',
        ]);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->cashier->assignRole($cashierRole);

        $area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'khu-a',
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
            'name' => 'Bàn 1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'token-123',
        ]);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'name' => 'Meat',
            'sku' => 'meat-001',
            'status' => 'active',
        ]);

        $this->inventory = Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 100.0,
            'theoretical_quantity' => 100.0,
        ]);

        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'code' => 'P-001',
            'name' => 'Beef Steak',
            'slug' => 'beef-steak',
            'price' => 100000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $this->product->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10.0,
            'waste_rate' => 0.0,
        ]);
    }

    private function createPendingOrder(): Order
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'created_by' => null,
            'order_number' => 'ORD-TEST123',
            'channel' => 'qr',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total_amount' => 100000,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1.0,
            'unit_price' => 100000,
            'line_total' => 100000,
            'status' => 'pending',
        ]);

        InventoryReservation::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'ingredient_id' => $this->ingredient->id,
            'reserved_quantity' => 10.0,
            'status' => 'holding',
            'expires_at' => now()->addMinutes(15),
        ]);

        return $order;
    }

    public function test_cashier_can_confirm_qr_order_and_deduct_inventory()
    {
        Event::fake();

        $order = $this->createPendingOrder();

        $response = $this->actingAs($this->cashier)
            ->postJson(route('orders.confirm-qr', $order->id));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify order is confirmed
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);

        // Verify inventory reservation is committed
        $this->assertDatabaseHas('inventory_reservations', [
            'order_id' => $order->id,
            'ingredient_id' => $this->ingredient->id,
            'status' => 'committed',
        ]);

        // Verify physical inventory is deducted (100.0 - 10.0 = 90.0)
        $this->inventory->refresh();
        $this->assertEquals(90.0, (float) $this->inventory->quantity_on_hand);
        $this->assertEquals(90.0, (float) $this->inventory->theoretical_quantity);

        // Verify table status is updated
        $this->table->refresh();
        $this->assertEquals('occupied', $this->table->status);

        // Verify event was broadcasted
        Event::assertDispatched(OrderStatusUpdated::class);
    }

    public function test_cashier_can_cancel_spam_qr_order_completely()
    {
        Event::fake();

        $order = $this->createPendingOrder();

        $response = $this->actingAs($this->cashier)
            ->postJson(route('orders.cancel-spam', $order->id));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify order and items are hard-deleted
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);

        // Verify reservations are deleted
        $this->assertDatabaseMissing('inventory_reservations', ['order_id' => $order->id]);

        // Verify table status remains available since no other orders
        $this->table->refresh();
        $this->assertEquals('available', $this->table->status);

        // Verify SpamOrderCancelled report event was broadcasted
        Event::assertDispatched(SpamOrderCancelled::class);
    }

    public function test_double_deduction_is_prevented_when_order_goes_to_completed()
    {
        $order = $this->createPendingOrder();

        // 1. Confirm QR Order first (causes inventory deduction)
        $response1 = $this->actingAs($this->cashier)
            ->postJson(route('orders.confirm-qr', $order->id));
        $response1->assertStatus(200);

        $this->inventory->refresh();
        $this->assertEquals(90.0, (float) $this->inventory->quantity_on_hand);

        // 2. Mark Order as Completed/Paid
        $response2 = $this->actingAs($this->cashier)
            ->patchJson(route('orders.update-status', $order->id), [
                'status' => 'completed',
            ]);
        $response2->assertStatus(302); // Redirect back

        // 3. Verify stock is still 90.0 (NO second deduction)
        $this->inventory->refresh();
        $this->assertEquals(90.0, (float) $this->inventory->quantity_on_hand);
    }
}
