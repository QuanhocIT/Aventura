<?php

namespace Tests\Feature;

use App\Events\QrOrderPlaced;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiChannelOrderAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashierUser;

    protected Employee $employee;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected WorkShift $shift;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup roles
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->cashierUser->assignRole($cashierRole);

        $this->employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashierUser->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        $category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Food',
            'slug' => 'food',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'code' => 'TEST-PROD',
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 100000,
            'is_active' => true,
            'is_available' => true,
        ]);

        $this->shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ]);
    }

    public function test_staff_can_update_order_item_status(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-123',
            'channel' => 'dine_in',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 100000,
        ]);

        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->cashierUser)->patchJson(
            route('orders.items.update-status', $item),
            ['status' => 'preparing']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $item->refresh();
        $this->assertSame('preparing', $item->status);
        $this->assertNotNull($item->sent_to_kitchen_at);
        $this->assertNotNull($item->prepared_at);
        $this->assertNull($item->served_at);

        // Transition to served
        $response = $this->actingAs($this->cashierUser)->patchJson(
            route('orders.items.update-status', $item),
            ['status' => 'served']
        );
        $response->assertOk();

        $item->refresh();
        $this->assertSame('served', $item->status);
        $this->assertNotNull($item->served_at);
    }

    public function test_staff_can_search_orders_and_access_history(): void
    {
        // Order today
        $orderToday = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TODAY',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'created_at' => now(),
        ]);

        // Order yesterday
        $orderYesterday = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-YESTERDAY',
            'channel' => 'delivery',
            'third_party_source' => 'GrabFood',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'created_at' => now()->subDay(),
        ]);

        // Default query: gets only today's orders
        $response = $this->actingAs($this->cashierUser)->get('/orders');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('orders', 1)
            ->where('orders.0.order_number', 'ORD-TODAY')
        );

        // Query history: gets both
        $response = $this->actingAs($this->cashierUser)->get('/orders?history=true');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('orders', 2)
        );

        // Query search by third party source
        $response = $this->actingAs($this->cashierUser)->get('/orders?search=GrabFood');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('orders', 1)
            ->where('orders.0.order_number', 'ORD-YESTERDAY')
        );
    }

    public function test_simulate_third_party_order(): void
    {
        Event::fake();

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('orders.third-party.simulate'),
            ['source' => 'ShopeeFood']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'channel' => 'delivery',
            'third_party_source' => 'ShopeeFood',
            'status' => 'pending',
        ]);

        Event::assertDispatched(QrOrderPlaced::class);
    }

    public function test_active_shift_stats_calculation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 10:00:00'));

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'scheduled_date' => '2026-06-11',
            'status' => 'checked_in',
            'check_in_at' => '2026-06-11 07:55:00',
        ]);

        // Create completed order inside shift window (08:00 to 16:00)
        $orderInside = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-INSIDE',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 150000,
            'total_amount' => 150000,
            'completed_at' => '2026-06-11 11:30:00',
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $orderInside->id,
            'payment_method' => 'cash',
            'amount' => 150000,
            'status' => 'paid',
        ]);

        // Create completed order outside shift window
        $orderOutside = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-OUTSIDE',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200000,
            'total_amount' => 200000,
            'completed_at' => '2026-06-11 17:00:00', // After 16:00
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $orderOutside->id,
            'payment_method' => 'cash',
            'amount' => 200000,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->cashierUser)->get('/orders');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('orders/Index')
            ->has('activeShiftStats')
            ->where('activeShiftStats.shift_name', 'Ca Sang')
            ->where('activeShiftStats.total_orders', 1)
            ->where('activeShiftStats.total_revenue', 150000)
            ->where('activeShiftStats.cash_revenue', 150000)
            ->where('activeShiftStats.transfer_revenue', 0)
        );

        Carbon::setTestNow();
    }
}
