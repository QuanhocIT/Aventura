<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryCountCentralWarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_warehouse_manager_can_start_count_only_for_central_warehouse(): void
    {
        [$restaurant, $central, $branch, $ingredient, $branchOnlyIngredient] = $this->inventoryFixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $service = app(InventoryCountService::class);
        $session = $service->startCountSession($restaurant->id, $central->id, $manager);

        $this->assertSame($central->id, $session->branch_id);
        $this->assertSame($ingredient->id, $session->items->first()->ingredient_id);
        $this->assertFalse($session->items->pluck('ingredient_id')->contains($branchOnlyIngredient->id));

        $this->expectException(InvalidArgumentException::class);
        $service->startCountSession($restaurant->id, $branch->id, $manager);
    }

    public function test_warehouse_manager_must_be_assigned_to_central_warehouse_before_counting(): void
    {
        [$restaurant, $central, $branch] = $this->inventoryFixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $branch->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $this->expectException(InvalidArgumentException::class);
        app(InventoryCountService::class)->startCountSession($restaurant->id, $central->id, $manager);
    }

    public function test_central_warehouse_manager_cannot_operate_a_branch_count_session(): void
    {
        [$restaurant, $central, $branch] = $this->inventoryFixture();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $branchSession = app(InventoryCountService::class)
            ->startCountSession($restaurant->id, $branch->id, $owner);

        $response = $this->actingAs($manager)->postJson(
            route('inventory.count-sessions.counts', $branchSession->id),
            ['items' => [['id' => $branchSession->items->first()->id, 'counted_quantity' => 1]]],
        );

        $response->assertForbidden();
    }

    public function test_count_page_exposes_only_central_warehouse_and_rejects_branch_filter(): void
    {
        [$restaurant, $central, $branch] = $this->inventoryFixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $this->actingAs($manager)
            ->get(route('inventory.count-sessions', ['branch_id' => $branch->id]))
            ->assertForbidden();

        $response = $this->actingAs($manager)->get(route('inventory.count-sessions'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($central, $branch): void {
            $props = $page->toArray()['props'];
            $branches = collect($props['branches']);

            $this->assertTrue($props['isCentralWarehouseScope']);
            $this->assertSame($central->id, $props['activeBranchId']);
            $this->assertCount(1, $branches);
            $this->assertSame($central->id, $branches->first()['id']);
            $this->assertFalse($branches->contains('id', $branch->id));
        });
    }

    public function test_count_api_rejects_a_branch_id_from_central_warehouse_account(): void
    {
        [$restaurant, $central, $branch] = $this->inventoryFixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $response = $this->actingAs($manager)->postJson(route('inventory.count-sessions.store'), [
            'branch_id' => $branch->id,
            'type' => 'periodic',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('inventory_count_sessions', 0);

        $quickPresetResponse = $this->actingAs($manager)->postJson('/inventory/counts/quick-preset', [
            'branch_id' => $branch->id,
            'preset' => 'low_stock',
        ]);

        $quickPresetResponse->assertForbidden();
        $this->assertDatabaseCount('inventory_count_sessions', 0);
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2: RestaurantBranch, 3: Ingredient, 4: Ingredient} */
    private function inventoryFixture(): array
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
            'status' => 'active',
        ]);
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Central count ingredient',
            'sku' => 'CENTRAL-COUNT-01',
            'average_cost' => 100000,
        ]);
        $branchOnlyIngredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Branch-only count ingredient',
            'sku' => 'BRANCH-ONLY-COUNT-01',
            'average_cost' => 90000,
        ]);

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 20,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $branchOnlyIngredient->id,
            'quantity_on_hand' => 12,
        ]);

        return [$restaurant, $central, $branch, $ingredient, $branchOnlyIngredient];
    }
}
