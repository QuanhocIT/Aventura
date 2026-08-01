<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBOMDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_description_is_required_for_validation(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'manager_user_id' => $owner->id]);
        $owner->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();
        $owner->assignRole('owner');

        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Mon An',
            'slug' => 'mon-an',
            'display_order' => 1,
            'status' => 'active',
        ]);

        // Attempting to create product without description should fail validation
        $response = $this->actingAs($owner)->post(route('products.store'), [
            'category_id' => $category->id,
            'name' => 'Pho Dac Biet',
            'price' => 75000,
            'scope' => 'branch',
            'branch_id' => $branch->id,
            // description is missing
        ]);

        $response->assertSessionHasErrors(['description']);

        // Attempting to create product with description should succeed
        $response2 = $this->actingAs($owner)->post(route('products.store'), [
            'category_id' => $category->id,
            'name' => 'Pho Dac Biet',
            'price' => 75000,
            'description' => 'Pho ngon thom lung vi sa 12345',
            'scope' => 'branch',
            'branch_id' => $branch->id,
        ]);

        $response2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'name' => 'Pho Dac Biet',
            'description' => 'Pho ngon thom lung vi sa 12345',
        ]);
    }

    public function test_bom_inventory_deduction_and_transaction_created_on_order_completed(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'manager_user_id' => $owner->id]);

        $cashier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        // assign roles
        $cashier->assignRole('cashier');

        // Create Category
        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Pho',
            'slug' => 'pho',
            'status' => 'active',
        ]);

        // Create Unit & Ingredient
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);

        $beef = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thit bo',
            'sku' => 'BEEF-001',
            'average_cost' => 200,
            'status' => 'active',
        ]);

        // Create Inventory record with starting balance
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $beef->id,
            'quantity_on_hand' => 1000.0,
            'theoretical_quantity' => 1000.0,
            'last_cost' => 200,
        ]);

        // Create Product with recipe (BOM)
        $pho = Product::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'code' => 'PHO-01',
            'name' => 'Pho bo chin',
            'slug' => 'pho-bo-chin',
            'description' => 'Pho ngon thom lung 12345',
            'price' => 60000,
            'cost_price' => 20000,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);

        // Define recipe: 1 bowl of Pho uses 100g of Beef, waste rate 5%
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $pho->id,
            'ingredient_id' => $beef->id,
            'unit_id' => $unit->id,
            'quantity' => 100.0,
            'waste_rate' => 5.0,
        ]);

        // Create Order: 2 bowls of Pho bo chin (total beef used = 2 * 100 * 1.05 = 210g)
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $cashier->id,
            'order_number' => 'ORD-TEST-100',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 120000,
            'total_amount' => 120000,
        ]);

        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $pho->id,
            'quantity' => 2,
            'unit_price' => 60000,
            'line_total' => 120000,
            'status' => 'pending',
        ]);

        // Set up holding reservation in database
        $reservation = InventoryReservation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ingredient_id' => $beef->id,
            'reserved_quantity' => 210.0,
            'status' => 'holding',
            'expires_at' => now()->addMinutes(30),
        ]);

        // Update status to completed
        $response = $this->actingAs($cashier)->patch(route('orders.update-status', $order), [
            'status' => 'completed',
        ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        // 1. Order status should be completed and paid
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);

        // 2. Inventory should be deducted correctly
        // 1000 - (100 * 2 * 1.05) = 1000 - 210 = 790
        $inventory->refresh();
        $this->assertEquals(790.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(790.0, (float) $inventory->theoretical_quantity);

        // 3. Inventory Transaction of type usage should be recorded
        $this->assertDatabaseHas('inventory_transactions', [
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $beef->id,
            'order_id' => $order->id,
            'type' => 'usage',
            'direction' => 'out',
            'quantity' => 210.0,
        ]);

        // 4. Reservation should be committed
        $reservation->refresh();
        $this->assertEquals('committed', $reservation->status);
    }

    public function test_bom_inventory_reservations_are_released_on_order_cancelled(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'manager_user_id' => $owner->id]);

        $cashier = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $cashier->assignRole('cashier');

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Gia vi',
            'sku' => 'SPICE-01',
            'average_cost' => 50,
            'status' => 'active',
        ]);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $cashier->id,
            'order_number' => 'ORD-TEST-200',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);

        $reservation = InventoryReservation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'ingredient_id' => $ingredient->id,
            'reserved_quantity' => 100.0,
            'status' => 'holding',
            'expires_at' => now()->addMinutes(30),
        ]);

        // Update status to cancelled
        $response = $this->actingAs($cashier)->patch(route('orders.update-status', $order), [
            'status' => 'cancelled',
            'bypass_code' => 'MANAGER123',
        ]);

        $response->assertSessionHasNoErrors();
        $order->refresh();

        $this->assertEquals('cancelled', $order->status);

        // Reservation should be released
        $reservation->refresh();
        $this->assertEquals('released', $reservation->status);
    }
}
