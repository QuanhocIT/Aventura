<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\AdvisorQueryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryChainScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_page_sums_the_same_ingredient_across_all_branches(): void
    {
        [$restaurant, $owner, $branchA, $branchB, $ingredient] = $this->inventoryFixture();

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10,
            'theoretical_quantity' => 8,
            'last_cost' => 100,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 20,
            'theoretical_quantity' => 18,
            'last_cost' => 120,
        ]);

        $response = $this->actingAs($owner)->get(route('inventory.index'));

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $item = collect($props['ingredients'])->firstWhere('id', $ingredient->id);

        $this->assertNotNull($item);
        $this->assertNull($props['activeBranchId']);
        $this->assertSame(30.0, (float) $item['stock']);
        $this->assertSame(26.0, (float) $item['theoretical_stock']);
        $this->assertSame(-4.0, (float) $item['variance']);
    }

    public function test_dashboard_low_stock_alerts_use_one_chain_row_per_ingredient(): void
    {
        [$restaurant, $owner, $branchA, $branchB, $ingredient] = $this->inventoryFixture();
        $ingredient->update(['min_stock_level' => 10]);

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 2,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 3,
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $items = collect($props['lowStockInventory'])
            ->where('id', $ingredient->id)
            ->values();

        $this->assertCount(1, $items);
        $this->assertSame(5.0, (float) $items->first()['quantity_on_hand']);
    }

    public function test_ai_advisor_counts_chain_low_stock_ingredients_once(): void
    {
        [$restaurant, $owner, $branchA, $branchB, $ingredient] = $this->inventoryFixture();
        $ingredient->update(['min_stock_level' => 10]);

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 2,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 3,
        ]);

        $inventoryAnswer = (new AdvisorQueryEngine($restaurant->id))->handle('inventory');
        $generalAnswer = (new AdvisorQueryEngine($restaurant->id))->handle('hello');

        $this->assertStringContainsString('5.00', $inventoryAnswer['answer']);
        $this->assertSame(1, substr_count($inventoryAnswer['answer'], $ingredient->name));
        $this->assertStringContainsString('1 mặt hàng', $generalAnswer['answer']);
    }

    public function test_ai_inventory_forecast_sums_stock_across_all_branches(): void
    {
        [$restaurant, $owner, $branchA, $branchB, $ingredient] = $this->inventoryFixture();

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10,
            'theoretical_quantity' => 10,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branchB->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 20,
            'theoretical_quantity' => 20,
        ]);

        Http::fake([
            '*/api/analytics/inventory-forecast' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($owner)->getJson(route('inventory.ai-forecast'));

        $response->assertOk();
        $forecast = collect($response->json('forecast'))->firstWhere('ingredient_id', $ingredient->id);

        $this->assertNotNull($forecast);
        $this->assertSame(30.0, (float) $forecast['current_stock']);
    }

    public function test_reconcile_rejects_all_chain_scope_instead_of_writing_to_an_implicit_branch(): void
    {
        [$restaurant, $owner, $branchA, $branchB, $ingredient] = $this->inventoryFixture();

        $response = $this->actingAs($owner)->post(route('inventory.reconcile'), [
            'reconcile_items' => [
                ['ingredient_id' => $ingredient->id, 'physical_qty' => 999],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('inventory_transactions', [
            'restaurant_id' => $restaurant->id,
            'type' => 'stocktake',
        ]);
        $this->assertDatabaseMissing('inventories', [
            'branch_id' => $branchA->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 999,
        ]);
        $this->assertDatabaseMissing('inventories', [
            'branch_id' => $branchB->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 999,
        ]);
    }

    /** @return array{0: Restaurant, 1: User, 2: RestaurantBranch, 3: RestaurantBranch, 4: Ingredient} */
    private function inventoryFixture(): array
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));
        $restaurant->update(['owner_user_id' => $owner->id]);
        $restaurant->plan->update([
            'features' => array_merge($restaurant->plan->features ?? [], ['inventory_basic' => true]),
        ]);

        $branchA = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
            'name' => 'Chi nhánh A',
        ]);
        $branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'manager_user_id' => null,
            'name' => 'Chi nhánh B',
        ]);
        $unit = Unit::factory()->create([
            'restaurant_id' => $restaurant->id,
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu dùng chung',
            'sku' => 'CHAIN-ING-01',
            'average_cost' => 110,
            'min_stock_level' => 0,
        ]);

        return [$restaurant, $owner, $branchA, $branchB, $ingredient];
    }
}
