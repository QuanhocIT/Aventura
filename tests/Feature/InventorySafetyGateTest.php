<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReadinessService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ProductCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySafetyGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tracked_product_is_hidden_and_notifies_owner_until_recipe_is_saved(): void
    {
        [$owner, $restaurant, $branch] = $this->tenant();
        $category = ProductCategory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Món safety',
            'slug' => 'mon-safety',
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('products.store'), [
            'category_id' => $category->id,
            'name' => 'Món mới chờ công thức',
            'price' => 50000,
            'description' => 'Món dùng để kiểm thử chốt menu.',
            'scope' => 'branch',
            'branch_id' => $branch->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('restaurant_id', $restaurant->id)
            ->where('name', 'Món mới chờ công thức')
            ->firstOrFail();
        $this->assertFalse((bool) $product->is_available);
        $this->assertFalse(Product::sellableMenu()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => 'App\\Notifications\\ProductRecipeRequiredNotification',
        ]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
            'conversion_factor_to_base' => 1,
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu menu safety',
            'sku' => 'MENU-SAFETY-001',
            'average_cost' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('inventory.recipes.store'), [
            'product_id' => $product->id,
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'unit_id' => $unit->id,
                'quantity' => 10,
                'waste_rate' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue((bool) $product->fresh()->is_available);
        $this->assertTrue(Product::sellableMenu()->whereKey($product->id)->exists());
    }

    public function test_gram_recipe_quantity_must_be_a_whole_number(): void
    {
        [$owner, $restaurant, $branch] = $this->tenant();
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'track_inventory' => true,
        ]);
        $gram = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
            'conversion_factor_to_base' => 1,
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $gram->id,
            'name' => 'Nguyên liệu gram nguyên',
            'sku' => 'GRAM-WHOLE-001',
            'average_cost' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($owner)->post(route('inventory.recipes.store'), [
            'product_id' => $product->id,
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'unit_id' => $gram->id,
                'quantity' => 10.5,
                'waste_rate' => 0,
            ]],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseMissing('product_recipes', [
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
        ]);
    }

    public function test_kg_purchase_and_gram_recipe_use_one_stock_unit_for_cogs_and_deduction(): void
    {
        [$owner, $restaurant, $branch] = $this->tenant();

        $kg = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
            'conversion_factor_to_base' => 1000,
        ]);
        $gram = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
            'conversion_factor_to_base' => 1,
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $kg->id,
            'name' => 'Thịt bò safety',
            'sku' => 'SAFETY-BEEF-001',
            'average_cost' => 120000,
            'status' => 'active',
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 2,
            'theoretical_quantity' => 2,
            'last_cost' => 120000,
        ]);
        InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'SAFETY-KG-001',
            'quantity_remaining' => 2,
            'unit_cost' => 120000,
            'purchased_at' => now()->toDateString(),
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Bò gram safety',
            'price' => 80000,
            'track_inventory' => true,
        ]);
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $gram->id,
            'quantity' => 250,
            'waste_rate' => 0,
        ]);

        app(ProductCostService::class)->recalculateForProducts([$product->id]);
        $product->refresh();
        $this->assertSame(30000.0, (float) $product->cost_price);

        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'ORD-SAFETY-UNIT-001',
            'channel' => 'dine_in',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 160000,
            'total_amount' => 160000,
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 80000,
            'line_total' => 160000,
            'status' => 'pending',
        ]);

        app(InventoryService::class)->deductInventoryForOrder($order, $owner);

        $inventory->refresh();
        $this->assertSame(1.5, (float) $inventory->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'order_id' => $order->id,
            'type' => 'usage',
            'quantity' => 0.5,
            'total_cost' => 60000,
        ]);
    }

    public function test_payment_rolls_back_when_tracked_product_has_no_recipe(): void
    {
        [$owner, $restaurant, $branch] = $this->tenant();
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Món chưa có BOM',
            'price' => 50000,
            'track_inventory' => true,
        ]);
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'created_by' => $owner->id,
            'order_number' => 'ORD-SAFETY-NOBOM-001',
            'channel' => 'takeaway',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);
        OrderItem::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
            'status' => 'pending',
        ]);

        try {
            app(OrderService::class)->payOrder($order, [
                'payment_method' => 'cash',
                'cash_received' => 50000,
                'change_amount' => 0,
            ], $owner);
            $this->fail('A tracked product without a recipe must not be paid.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('chưa có công thức', $exception->getMessage());
        }

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_stocktake_requires_permission_and_opening_legacy_reconciliation_is_audited(): void
    {
        [$owner, $restaurant, $branch] = $this->tenant();
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
            'conversion_factor_to_base' => 1,
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu legacy safety',
            'sku' => 'SAFETY-LEGACY-001',
            'average_cost' => 20,
            'status' => 'active',
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100,
            'theoretical_quantity' => 100,
            'last_cost' => 20,
        ]);
        $product = Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Món legacy safety',
            'track_inventory' => true,
        ]);
        ProductRecipe::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 10,
            'waste_rate' => 0,
        ]);
        $legacy = InventoryBatch::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'batch_number' => 'LEGACY-'.$inventory->id,
            'quantity_remaining' => 100,
            'unit_cost' => 20,
            'purchased_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $readiness = app(InventoryReadinessService::class)->forBranch($restaurant->id, $branch->id);
        $this->assertFalse($readiness['ready']);
        $this->assertSame(1, $readiness['opening_balance_pending']);
        $this->assertSame(1, $readiness['legacy_batches_pending']);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $staff->assignRole('cashier');
        $this->actingAs($staff)->post(route('inventory.reconcile'), [
            'reconcile_items' => [['ingredient_id' => $ingredient->id, 'physical_qty' => 100]],
            'is_opening_balance' => true,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('inventory.reconcile'), [
            'reconcile_items' => [['ingredient_id' => $ingredient->id, 'physical_qty' => 100]],
            'is_opening_balance' => true,
            'notes' => 'Đối soát số dư đầu kỳ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $inventory->refresh();
        $legacy->refresh();
        $this->assertNotNull($inventory->opening_balance_reconciled_at);
        $this->assertNotNull($legacy->reconciled_at);
        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $restaurant->id,
            'action' => 'inventory_stocktake',
        ]);
        $this->assertTrue(app(InventoryReadinessService::class)
            ->forBranch($restaurant->id, $branch->id)['ready']);
    }

    /** @return array{0: User, 1: Restaurant, 2: RestaurantBranch} */
    private function tenant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => $owner->id,
        ]);
        $owner->forceFill([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ])->save();
        $owner->assignRole('owner');

        return [$owner, $restaurant, $branch];
    }
}
