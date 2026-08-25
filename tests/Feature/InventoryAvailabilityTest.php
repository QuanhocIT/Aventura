<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_portions_exclude_holding_stock_and_notify_branch_when_sold_out(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->forceFill([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
        ])->save();
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thit ga availability',
            'sku' => 'CHICKEN-AVAIL-001',
            'average_cost' => 100,
            'status' => 'active',
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 3,
            'theoretical_quantity' => 3,
            'last_cost' => 100,
        ]);
        InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'AVAIL-LOT-001',
            'quantity_remaining' => 3,
            'unit_cost' => 100,
            'purchased_at' => now()->toDateString(),
            'expiry_date' => now()->addDays(3)->toDateString(),
            'status' => 'active',
        ]);

        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Availability Test',
            'slug' => 'availability-test',
            'status' => 'active',
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'code' => 'AVAIL-001',
            'name' => 'Mon availability',
            'slug' => 'mon-availability',
            'description' => 'Availability test product',
            'price' => 10000,
            'cost_price' => 100,
            'track_inventory' => true,
        ]);
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'waste_rate' => 0,
        ]);

        $service = app(InventoryAvailabilityService::class);
        $availability = $service->forProducts(
            collect([$product->fresh()->load('recipes.ingredient.unit')]),
            $restaurant->id,
            $branch->id,
        );
        $this->assertSame(3, $availability->get($product->id)['available_portions']);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'ORD-AVAIL-001',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 10000,
            'total_amount' => 10000,
        ]);
        InventoryReservation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ingredient_id' => $ingredient->id,
            'reserved_quantity' => 2,
            'status' => 'holding',
            'expires_at' => now()->addHours(4),
        ]);

        $availability = $service->forProducts(
            collect([$product->fresh()->load('recipes.ingredient.unit')]),
            $restaurant->id,
            $branch->id,
        );
        $this->assertSame(1, $availability->get($product->id)['available_portions']);

        InventoryReservation::where('order_id', $order->id)->update(['reserved_quantity' => 3]);
        $service->refreshBranch($restaurant->id, $branch->id);

        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(1, $owner->notifications()->count());

        $service->refreshBranch($restaurant->id, $branch->id);
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(1, $owner->notifications()->count());
    }
}
