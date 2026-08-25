<?php

namespace Tests\Feature;

use App\Events\Kitchen\KitchenWaiterCalled;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiTenderAndCallWaiterTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected User $cashier;

    protected User $kitchen;

    protected RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $roleCashier = Role::findOrCreate('cashier', 'web');
        $permPay = Permission::findOrCreate('process_payments', 'web');
        $permOrder = Permission::findOrCreate('manage_orders', 'web');
        $roleCashier->givePermissionTo([$permPay, $permOrder]);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole('cashier');

        $roleKitchen = Role::findOrCreate('kitchen', 'web');
        $permKitchen = Permission::findOrCreate('manage_kitchen', 'web');
        $roleKitchen->givePermissionTo($permKitchen);

        $this->kitchen = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->kitchen->assignRole('kitchen');

        $this->table = RestaurantTable::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Bàn 10',
        ]);
    }

    public function test_can_pay_order_with_multi_tender_payments(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 1000000,
            'subtotal' => 1000000,
        ]);

        // Add served item to satisfy canBePaid check
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'served_at' => now(),
            'status' => 'served',
        ]);

        $response = $this->actingAs($this->cashier)->post(route('orders.pay', $order), [
            'payment_method' => 'multi',
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 600000, 'cash_received' => 600000, 'change_amount' => 0],
                ['payment_method' => 'vietqr', 'amount' => 400000],
            ],
        ]);

        $response->assertRedirect();

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // Verify Payment records creation
        $this->assertCount(2, $order->payments);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 600000,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'vietqr',
            'amount' => 400000,
        ]);
    }

    public function test_kitchen_can_trigger_call_waiter_event(): void
    {
        Event::fake([KitchenWaiterCalled::class]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'status' => 'processing',
            'order_number' => 'ORD-12345',
        ]);

        $response = $this->actingAs($this->kitchen)->post(route('orders.call-waiter', $order), [
            'item_name' => 'Lẩu Thái Hải Sản',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Đã gửi thông báo réo phục vụ tới các thiết bị!']);

        Event::assertDispatched(KitchenWaiterCalled::class, function (KitchenWaiterCalled $event) use ($order) {
            return $event->restaurantId === $this->restaurant->id
                && $event->orderId === $order->id
                && $event->tableName === 'Bàn 10'
                && $event->itemName === 'Lẩu Thái Hải Sản';
        });
    }
}
