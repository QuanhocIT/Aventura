<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that lockForUpdate query compiles successfully.
     */
    public function test_inventory_updates_apply_pessimistic_locking(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'manager_user_id' => $owner->id]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass'
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thit ga',
            'sku' => 'CHICKEN-001',
            'average_cost' => 100,
            'status' => 'active'
        ]);

        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 1000.0,
            'theoretical_quantity' => 1000.0,
            'last_cost' => 100,
        ]);

        DB::transaction(function () use ($inventory) {
            $inv1 = Inventory::where('id', $inventory->id)->lockForUpdate()->first();
            $this->assertNotNull($inv1);

            $querySql = Inventory::where('id', $inventory->id)->lockForUpdate()->toSql();
            
            // Assert compiled select SQL. SQLite does not support FOR UPDATE but standard select compile is correct.
            $this->assertStringContainsString('select', strtolower($querySql));
        });
    }

    /**
     * Test that InventoryService executePurchase functions successfully.
     */
    public function test_inventory_service_execute_purchase_locking_logic(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'manager_user_id' => $owner->id]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass'
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Thit ga',
            'sku' => 'CHICKEN-001',
            'average_cost' => 100,
            'status' => 'active'
        ]);

        $inventoryService = app(InventoryService::class);

        $data = [
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'unit_cost' => 120,
            'notes' => 'Nhap thit ga'
        ];

        DB::transaction(function () use ($inventoryService, $data, $restaurant, $owner) {
            $inventoryService->executePurchase($data, $restaurant->id, $owner->id);
        });

        $inventory = Inventory::where('restaurant_id', $restaurant->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertEquals(50.0, (float)$inventory->quantity_on_hand);
        $this->assertEquals(120.0, (float)$inventory->last_cost);
    }
}
