<?php

namespace Tests\Feature;

use App\Events\Kitchen\KitchenItemCancelled;
use App\Events\Kitchen\KitchenUpdated;
use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KitchenMenuAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $kitchenStaff;

    protected User $cashier;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected ProductCategory $category;

    protected Product $product;

    protected RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'manager_user_id' => $this->owner->id]);

        $this->owner->forceFill(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id])->save();
        $this->owner->assignRole('owner');

        $this->kitchenStaff = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        $this->kitchenStaff->assignRole('kitchen');

        $this->cashier = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branch->id]);
        $this->cashier->assignRole('cashier');

        $this->category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Thực Đơn',
            'slug' => 'thuc-don',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'code' => 'TEST-01',
            'name' => 'Món Ăn Thử Nghiệm',
            'slug' => 'mon-an-thu-nghiem',
            'description' => 'Mô tả món ăn thử nghiệm ngon tuyệt vời 12345',
            'price' => 50000,
            'cost_price' => 15000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => Area::firstOrCreate(['restaurant_id' => $this->restaurant->id, 'code' => 'SANH-TEST'], ['name' => 'Sảnh Test', 'display_order' => 1, 'status' => 'active'])->id,
            'name' => 'T1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'qr-token-test-123',
            'qr_code' => 'QR-T1-'.$this->restaurant->id,
        ]);
    }

    public function test_kitchen_staff_can_pause_product(): void
    {
        $response = $this->actingAs($this->kitchenStaff)->post(route('kitchen.products.pause', $this->product), [
            'minutes' => 30,
        ]);

        $response->assertRedirect();
        $this->product->refresh();
        $this->assertNotNull($this->product->paused_until);
        $this->assertTrue($this->product->paused_until->isFuture());
        $this->assertNull($this->product->out_of_stock_until);
    }

    public function test_kitchen_staff_can_mark_product_out_of_stock(): void
    {
        $response = $this->actingAs($this->kitchenStaff)->post(route('kitchen.products.out-of-stock', $this->product), [
            'minutes' => 120,
        ]);

        $response->assertRedirect();
        $this->product->refresh();
        $this->assertNotNull($this->product->out_of_stock_until);
        $this->assertTrue($this->product->out_of_stock_until->isFuture());
        $this->assertNull($this->product->paused_until);
    }

    public function test_kitchen_staff_can_resume_product(): void
    {
        $this->product->update([
            'paused_until' => now()->addMinutes(60),
            'out_of_stock_until' => now()->addMinutes(120),
        ]);

        $response = $this->actingAs($this->kitchenStaff)->post(route('kitchen.products.resume', $this->product));

        $response->assertRedirect();
        $this->product->refresh();
        $this->assertNull($this->product->paused_until);
        $this->assertNull($this->product->out_of_stock_until);
    }

    public function test_kitchen_staff_can_activate_multiple_products_until_end_of_day_with_reason(): void
    {
        $secondProduct = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'code' => 'TEST-02',
            'name' => 'Món ăn thứ hai',
            'slug' => 'mon-an-thu-hai',
            'description' => 'Món thứ hai',
            'price' => 60000,
            'cost_price' => 15000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        $response = $this->actingAs($this->kitchenStaff)->post(route('kitchen.menu-control.activate'), [
            'product_ids' => [$this->product->id, $secondProduct->id],
            'reason' => 'Thiết bị bếp gặp sự cố, không thể phục vụ trong hôm nay.',
        ]);

        $response->assertRedirect();

        $this->product->refresh();
        $secondProduct->refresh();
        $this->assertTrue($this->product->out_of_stock_until?->isToday());
        $this->assertTrue($secondProduct->out_of_stock_until?->isToday());
        $this->assertSame(
            'Thiết bị bếp gặp sự cố, không thể phục vụ trong hôm nay.',
            $this->product->out_of_stock_reason,
        );
        $this->assertNull($this->product->paused_until);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kitchen_menu_unavailable',
            'subject_id' => $this->product->id,
            'branch_id' => $this->branch->id,
        ]);
        $notification = $this->owner->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertSame('kitchen_menu_unavailable', $notification->data['type']);
        $this->assertSame('/audit-logs', $notification->data['url']);
    }

    public function test_cashier_cannot_order_a_kitchen_disabled_product_via_pos(): void
    {
        $this->product->update([
            'out_of_stock_until' => now()->endOfDay(),
            'out_of_stock_reason' => 'Hết nguyên liệu trong ngày.',
        ]);

        $response = $this->actingAs($this->cashier)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('orders', [
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_cashier_cannot_order_paused_product_via_pos(): void
    {
        $this->product->update([
            'paused_until' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($this->cashier)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('orders', [
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_customer_cannot_order_paused_product_via_qr(): void
    {
        $this->product->update([
            'paused_until' => now()->addMinutes(30),
        ]);

        $response = $this->post(route('customer.qr-order.submit', [
            'restaurant' => $this->restaurant->id,
            'token' => $this->table->qr_token,
        ]), [
            'customer_name' => 'Khách Ăn Thử',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('temporary_orders', [
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_kitchen_can_cancel_only_part_of_an_item_quantity(): void
    {
        Event::fake([KitchenItemCancelled::class, KitchenUpdated::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'KITCHEN-PARTIAL-1',
            'channel' => 'qr',
            'status' => 'preparing',
            'payment_status' => 'unpaid',
            'subtotal' => 150000,
            'total_amount' => 150000,
        ]);

        $item = OrderItem::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 50000,
            'line_total' => 150000,
            'status' => 'preparing',
            'sent_to_kitchen_at' => now(),
        ]);

        $response = $this->actingAs($this->kitchenStaff)->post(
            route('kitchen.cancel-item', $item),
            [
                'scope' => 'single',
                'quantity' => 1,
                'reason' => 'Hết nguyên liệu một phần',
            ],
        );

        $response->assertRedirect();
        $item->refresh();
        $order->refresh();

        $this->assertSame(2.0, (float) $item->quantity);
        $this->assertSame('preparing', $item->status);
        $this->assertSame(100000.0, (float) $item->line_total);
        $this->assertSame(100000.0, (float) $order->subtotal);
        $this->assertSame(100000.0, (float) $order->total_amount);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'line_total' => 50000,
            'status' => 'cancelled',
        ]);

        Event::assertDispatched(KitchenItemCancelled::class, function (KitchenItemCancelled $event) use ($order): bool {
            return $event->orderId === $order->id
                && $event->cancelledCount === 1
                && $event->quantity === 1.0;
        });
    }
}
