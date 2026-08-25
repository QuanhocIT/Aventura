<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\LoyaltyProgram;
use App\Models\OnlineStoreConfig;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Repositories\OrderRepositoryInterface;
use App\Services\LoyaltyService;
use App\Services\OnlineOrderService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionEdgeCasesFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fix_1_cleanup_expired_reservations_command()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $ingredient = Ingredient::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);

        $reservation = InventoryReservation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ingredient_id' => $ingredient->id,
            'reserved_quantity' => 5,
            'status' => 'holding',
            'expires_at' => now()->subMinutes(10),
        ]);

        $this->artisan('reservations:cleanup-expired')->assertExitCode(0);

        $this->assertDatabaseHas('inventory_reservations', [
            'id' => $reservation->id,
            'status' => 'expired',
        ]);
    }

    public function test_fix_2_loyalty_earn_points_is_idempotent()
    {
        $restaurant = Restaurant::factory()->create();
        LoyaltyProgram::create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
            'points_per_vnd' => 0.001,
        ]);
        $customer = Customer::factory()->create(['restaurant_id' => $restaurant->id, 'loyalty_points' => 0]);
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $customer->id, 'total_amount' => 100000]);

        $loyaltyService = app(LoyaltyService::class);
        $points1 = $loyaltyService->earnPoints($customer, $order, 100000);
        $this->assertGreaterThan(0, $points1);

        // Second call should return 0 points and not add to customer balance
        $customer->refresh();
        $initialBalance = $customer->loyalty_points;

        $points2 = $loyaltyService->earnPoints($customer, $order, 100000);
        $this->assertEquals(0, $points2);

        $customer->refresh();
        $this->assertEquals($initialBalance, $customer->loyalty_points);
    }

    public function test_fix_5_models_guarded_id()
    {
        $order = new Order;
        $payment = new Payment;
        $inventory = new Inventory;

        $this->assertContains('id', $order->getGuarded());
        $this->assertContains('id', $payment->getGuarded());
        $this->assertContains('id', $inventory->getGuarded());
    }

    public function test_fix_9_table_display_name_attribute()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id, 'name' => 'Bàn 01']);
        $order = Order::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id, 'table_id' => $table->id]);

        $this->assertEquals('Bàn 01', $order->table_display_name);

        $table->delete();
        $order->refresh();

        $this->assertEquals('Bàn 01 (Đã xóa)', $order->table_display_name);
    }

    public function test_fix_13_delivery_fee_uses_branch_coordinates_fallback()
    {
        $restaurant = Restaurant::factory()->create([
            'latitude' => 10.7769,
            'longitude' => 106.7009,
        ]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $config = OnlineStoreConfig::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'slug' => 'test-store',
            'is_active' => true,
            'max_delivery_km' => 10,
            'delivery_base_fee' => 15000,
            'delivery_fee_per_km' => 5000,
        ]);

        $service = app(OnlineOrderService::class);
        $result = $service->calculateDeliveryFee(10.7810, 106.7060, $restaurant);

        $this->assertTrue($result['deliverable']);
        $this->assertGreaterThan(0, $result['fee']);
    }

    public function test_fix_14_cash_payment_creates_cash_transaction()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $cashRegister = CashRegister::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'cashier_user_id' => $user->id,
            'closing_date' => now()->toDateString(),
            'opened_at' => now(),
            'status' => 'open',
            'opening_balance' => 100000,
            'expected_closing_balance' => 100000,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'subtotal' => 50000,
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        $orderService = app(OrderService::class);
        $result = $orderService->payOrder($order, [
            'payment_method' => 'cash',
            'cash_received' => 50000,
            'change_amount' => 0,
        ], $user);

        $this->assertTrue($result);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_register_id' => $cashRegister->id,
            'type' => 'in',
            'source' => 'order',
            'amount' => 50000,
        ]);

        $cashRegister->refresh();
        $this->assertEquals(150000, (float) $cashRegister->expected_closing_balance);
    }

    public function test_preparing_orders_filtering_and_status_transition()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);
        Role::findOrCreate('owner');
        Permission::findOrCreate('manage_kitchen');
        $user->givePermissionTo('manage_kitchen');
        $user->assignRole('owner');
        $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'pending',
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

        // Kitchen prepares item
        $response = $this->actingAs($user)->post(route('kitchen.prepare', $item));
        $response->assertStatus(302);

        $order->refresh();
        $this->assertEquals('preparing', $order->status);

        // Once the item is finished by the kitchen it no longer belongs to
        // the "Đang chế biến" queue; it belongs to "Chờ phục vụ".
        $repo = app(OrderRepositoryInterface::class);
        $preparingOrders = $repo->getOrdersQuery($restaurant->id, [
            'status' => 'preparing',
            'branch_id' => $branch->id,
        ])->get();

        $this->assertFalse($preparingOrders->contains('id', $order->id));

        $readyOrders = $repo->getOrdersQuery($restaurant->id, [
            'status' => 'ready',
            'branch_id' => $branch->id,
        ])->get();

        $this->assertTrue($readyOrders->contains('id', $order->id));

        $stats = $repo->getSummaryStats($restaurant->id, today()->toDateString(), false, $branch->id);
        $this->assertEquals(1, $stats['preparing']);
    }
}
