<?php

namespace Tests\Feature;

use App\Events\QrOrderPlaced;
use App\Models\Area;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QrOrderSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected RestaurantTable $table;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup role and user
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->cashier = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->cashier->assignRole($cashierRole);

        // Setup area and table
        $area = Area::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khu chính',
            'code' => 'khu-chinh',
            'display_order' => 1,
            'status' => 'active',
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
            'name' => 'T1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'test-table-token-12345',
        ]);

        // Setup category and product
        $category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Lẩu',
            'slug' => 'lau',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'code' => 'LAU-GA',
            'name' => 'Lẩu gà Hỏa Đứng',
            'slug' => 'lau-ga-hoa-dung',
            'price' => 250000,
            'is_active' => true,
            'is_available' => true,
            'description' => 'Món lẩu đặc trưng thơm ngon',
        ]);
    }

    public function test_guest_can_access_qr_menu_with_valid_token(): void
    {
        $response = $this->get(route('qr.order.show', [
            'restaurant' => $this->restaurant->id,
            'table_token' => 'test-table-token-12345',
        ]));

        $response->assertOk();
    }

    public function test_guest_cannot_access_qr_menu_with_invalid_token(): void
    {
        $response = $this->get(route('qr.order.show', [
            'restaurant' => $this->restaurant->id,
            'table_token' => 'invalid-token',
        ]));

        $response->assertStatus(404);
    }

    public function test_guest_can_submit_qr_order_and_broadcast_event(): void
    {
        Event::fake();

        $response = $this->postJson(route('qr.order.submit', [
            'restaurant' => $this->restaurant->id,
            'table_token' => 'test-table-token-12345',
        ]), [
            'note' => 'Ghi chú cho bếp',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'notes' => 'Không hành',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify order is stored as draft in DB
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'channel' => 'qr',
            'status' => 'pending',
            'subtotal' => 250000,
            'total_amount' => 250000,
            'note' => 'Ghi chú cho bếp',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 250000,
            'notes' => 'Không hành',
        ]);

        // Verify broadcast event was dispatched
        Event::assertDispatched(QrOrderPlaced::class, function ($event) {
            return $event->order->restaurant_id === $this->restaurant->id
                && $event->order->channel === 'qr';
        });
    }

    public function test_staff_can_confirm_submitted_qr_order(): void
    {
        // Place draft order
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-QR-MOCK',
            'channel' => 'qr',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 250000,
            'total_amount' => 250000,
        ]);

        $response = $this->actingAs($this->cashier)->patch(route('orders.update-status', $order), [
            'status' => 'confirmed',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }
}
