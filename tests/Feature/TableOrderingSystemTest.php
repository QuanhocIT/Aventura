<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Area;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TableOrderingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_create_order_page_auto_seeds_20_tables_and_returns_them(): void
    {
        // 1. Verify there are no tables initially
        $this->assertEquals(0, RestaurantTable::where('restaurant_id', $this->restaurant->id)->count());

        // 2. Call the create order route
        $response = $this->actingAs($this->cashier)->get(route('orders.create'));

        $response->assertOk();

        // 3. Verify exactly 20 tables have been created
        $this->assertEquals(20, RestaurantTable::where('restaurant_id', $this->restaurant->id)->count());

        // 4. Verify they are named A1..A10, B1..B10
        $tables = RestaurantTable::where('restaurant_id', $this->restaurant->id)
            ->get()
            ->sortBy('name', SORT_NATURAL)
            ->pluck('name')
            ->toArray();

        $expectedNames = [];
        $letters = ['A', 'B', 'C', 'D'];
        foreach ($letters as $letter) {
            for ($i = 1; $i <= 5; $i++) {
                $expectedNames[] = "{$letter}{$i}";
            }
        }

        $this->assertEquals($expectedNames, array_values($tables));
    }

    public function test_can_submit_order_from_seeded_table(): void
    {
        // Set up category and product
        $category = ProductCategory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Thức uống',
            'slug' => 'thuc-uong',
            'status' => 'active',
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'code' => 'COCA',
            'name' => 'Coca Cola',
            'slug' => 'coca-cola',
            'price' => 15000,
            'is_active' => true,
            'is_available' => true,
            'description' => 'Nước ngọt có ga giải nhiệt cực đã',
        ]);

        // Access page to trigger table seeding
        $this->actingAs($this->cashier)->get(route('orders.create'));

        $table = RestaurantTable::where('restaurant_id', $this->restaurant->id)->where('name', 'A1')->firstOrFail();

        // Submit order
        $response = $this->actingAs($this->cashier)->post(route('orders.store'), [
            'table_id' => $table->id,
            'note' => 'Giao hàng nhanh',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'notes' => 'Không đá',
                ]
            ]
        ]);

        $response->assertRedirect(route('orders.index'));

        // Assert database has order
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'subtotal' => 30000,
            'total_amount' => 30000,
            'note' => 'Giao hàng nhanh',
        ]);

        $this->assertDatabaseHas('order_items', [
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 15000,
            'notes' => 'Không đá',
        ]);
    }
}
