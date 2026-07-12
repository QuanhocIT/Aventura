<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status' => 'active'
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
            'track_inventory' => false
        ]);

        $this->table = RestaurantTable::create([
            'restaurant_id' => $this->restaurant->id,
            'area_id' => \App\Models\Area::firstOrCreate(['restaurant_id' => $this->restaurant->id, 'code' => 'SANH-TEST'], ['name' => 'Sảnh Test', 'display_order' => 1, 'status' => 'active'])->id,
            'name' => 'T1',
            'capacity' => 4,
            'status' => 'available',
            'qr_token' => 'qr-token-test-123',
            'qr_code' => 'QR-T1-' . $this->restaurant->id
        ]);
    }

    public function test_kitchen_staff_can_pause_product(): void
    {
        $response = $this->actingAs($this->kitchenStaff)->post(route('kitchen.products.pause', $this->product), [
            'minutes' => 30
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
            'minutes' => 120
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

    public function test_cashier_cannot_order_paused_product_via_pos(): void
    {
        $this->product->update([
            'paused_until' => now()->addMinutes(30)
        ]);

        $response = $this->actingAs($this->cashier)->post(route('orders.store'), [
            'table_id' => $this->table->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1
                ]
            ]
        ]);

        $response->assertSessionHasErrors(['items']);
        $this->assertDatabaseMissing('orders', [
            'restaurant_id' => $this->restaurant->id
        ]);
    }

    public function test_customer_cannot_order_paused_product_via_qr(): void
    {
        $this->product->update([
            'paused_until' => now()->addMinutes(30)
        ]);

        $response = $this->post(route('customer.qr-order.submit', [
            'restaurant' => $this->restaurant->id,
            'token' => $this->table->qr_token
        ]), [
            'customer_name' => 'Khách Ăn Thử',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2
                ]
            ]
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('temporary_orders', [
            'restaurant_id' => $this->restaurant->id
        ]);
    }
}
