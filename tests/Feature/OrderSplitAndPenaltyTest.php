<?php

namespace Tests\Feature;

use App\Events\OrderSplit;
use App\Models\Area;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderSplitAndPenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashierUser;

    protected User $ownerUser;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected RestaurantTable $tableA;

    protected RestaurantTable $tableB;

    protected Product $product;

    protected WorkShift $shift;

    protected Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

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

        $this->ownerUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->ownerUser->assignRole($ownerRole);

        // Create Employee for cashier user
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->cashierUser->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        $this->area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu A',
            'code' => 'khu-a',
            'status' => 'active',
        ]);

        $this->tableA = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'A1',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $this->tableB = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $this->area->id,
            'name' => 'B2',
            'capacity' => 4,
            'status' => 'available',
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
            'code' => 'P1',
            'name' => 'Hamburger',
            'slug' => 'hamburger',
            'price' => 50000,
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

    public function test_splitting_order_fires_realtime_event_and_sets_flags(): void
    {
        Event::fake([OrderSplit::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-ORIGINAL',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'total_amount' => 100000,
        ]);

        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'line_total' => 100000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->cashierUser)->post(
            route('orders.split', $order),
            [
                'table_id' => $this->tableB->id,
                'items' => [
                    [
                        'order_item_id' => $item->id,
                        'quantity' => 1,
                    ],
                ],
            ]
        );

        $response->assertRedirect();

        // Verify that the database contains the split order and flags
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'is_split' => true,
            'split_from_order_id' => $order->id,
            'is_red_flagged' => true,
            'is_override_split_penalty' => false,
            'table_id' => $this->tableB->id,
        ]);

        // Target table status should become occupied
        $this->tableB->refresh();
        $this->assertSame('occupied', $this->tableB->status);

        // Verify Event was dispatched
        Event::assertDispatched(OrderSplit::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                $event->newOrder->is_split === true &&
                $event->user->id === $this->cashierUser->id;
        });
    }

    public function test_unpaid_split_orders_deducted_from_expected_cash_as_penalty(): void
    {
        // Set fixed current time to within the shift
        Carbon::setTestNow('2026-06-11 10:00:00');

        // Create completed paid order (adds to expected cash)
        $orderPaid = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->tableA->id,
            'order_number' => 'ORD-PAID',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 80000,
            'total_amount' => 80000,
            'completed_at' => now(),
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $orderPaid->id,
            'processed_by' => $this->cashierUser->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 80000,
            'paid_at' => now(),
        ]);

        // Create unpaid split order (penalty)
        $orderSplitUnpaid = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->tableB->id,
            'order_number' => 'ORD-SPLIT-UNPAID',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'is_split' => true,
            'split_from_order_id' => $orderPaid->id,
            'is_red_flagged' => true,
            'is_override_split_penalty' => false,
            'subtotal' => 30000,
            'total_amount' => 30000,
            'created_at' => now(),
        ]);

        // Shift closing preview should have expected_cash = paid cash (80000) - split penalty (30000) = 50000
        $response = $this->actingAs($this->cashierUser)->getJson(
            route('shift-closings.preview', [
                'shift_id' => $this->shift->id,
                'closing_date' => '2026-06-11',
            ])
        );

        $response->assertOk();
        $response->assertJsonPath('expected_cash', 50000);
        $response->assertJsonPath('split_penalty_total', 30000);
        $response->assertJsonCount(1, 'split_orders');
        $response->assertJsonPath('split_orders.0.order_number', 'ORD-SPLIT-UNPAID');

        Carbon::setTestNow();
    }

    public function test_owner_can_override_split_penalty_restoring_expected_cash(): void
    {
        Carbon::setTestNow('2026-06-11 10:00:00');

        $orderPaid = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-PAID',
            'channel' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 80000,
            'total_amount' => 80000,
            'completed_at' => now(),
        ]);

        Payment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $orderPaid->id,
            'processed_by' => $this->cashierUser->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 80000,
            'paid_at' => now(),
        ]);

        $orderSplit = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-SPLIT',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'is_split' => true,
            'split_from_order_id' => $orderPaid->id,
            'is_red_flagged' => true,
            'is_override_split_penalty' => false,
            'subtotal' => 30000,
            'total_amount' => 30000,
            'created_at' => now(),
        ]);

        // Attempt override as cashier (should fail with 403)
        $response = $this->actingAs($this->cashierUser)->patch(
            route('orders.override-split-penalty', $orderSplit)
        );
        $response->assertStatus(403);

        // Attempt override as owner (should succeed)
        $response = $this->actingAs($this->ownerUser)->patch(
            route('orders.override-split-penalty', $orderSplit)
        );
        $response->assertRedirect();

        $orderSplit->refresh();
        $this->assertTrue((bool) $orderSplit->is_override_split_penalty);
        $this->assertFalse((bool) $orderSplit->is_red_flagged);

        // Shift closing preview should now be restored to expected_cash = 80000 (penalty override is active)
        $response = $this->actingAs($this->cashierUser)->getJson(
            route('shift-closings.preview', [
                'shift_id' => $this->shift->id,
                'closing_date' => '2026-06-11',
            ])
        );

        $response->assertOk();
        $response->assertJsonPath('expected_cash', 80000);
        $response->assertJsonPath('split_penalty_total', 0);

        Carbon::setTestNow();
    }
}
