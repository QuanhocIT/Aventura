<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\MaterialClosingService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_closing_is_scoped_to_branch_and_applies_approved_variance(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'is_central_warehouse' => false,
            'warehouse_type' => 'branch',
        ]);
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
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
            'name' => 'Nguyên liệu chi nhánh',
            'sku' => 'BRANCH-CLOSING-01',
            'average_cost' => 100,
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 70,
            'last_cost' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $manager->assignRole('manager');

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $staff->assignRole('inventory_staff');

        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $periodStart = now()->subDays(20)->startOfDay();
        $periodEnd = now()->subDays(5)->endOfDay();
        $this->transaction($restaurant->id, $branch->id, $ingredient->id, $inventory->id, $manager->id, 'in', 10, $periodStart->copy()->addDays(2));
        $this->transaction($restaurant->id, $branch->id, $ingredient->id, $inventory->id, $manager->id, 'out', 5, $periodStart->copy()->addDays(5));
        $this->transaction($restaurant->id, $branch->id, $ingredient->id, $inventory->id, $manager->id, 'out', 5, now()->subDays(2));

        $session = app(MaterialClosingService::class)->start(
            $restaurant->id,
            $branch->id,
            $manager,
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            null,
            'branch_closing',
        );

        $item = $session->items->first();
        $this->assertSame('branch_closing', $session->type);
        $this->assertSame($branch->id, $session->branch_id);
        $this->assertEquals(75, $item->expected_quantity);
        $this->assertEquals(7500, $item->expected_value);

        app(InventoryCountService::class)->assignSecondCounter($session, $manager, $staff);

        $this->actingAs($staff)
            ->postJson(route('inventory.count-sessions.counts', $session->id), [
                'items' => [['id' => $item->id, 'counted_quantity' => 70]],
            ])
            ->assertOk();

        Storage::fake('local');
        Storage::disk('local')->put('proof/branch-closing.jpg', 'proof');
        $submitted = app(InventoryCountService::class)->finalizeAndSubmitForApproval(
            InventoryCountSession::findOrFail($session->id),
            'proof/branch-closing.jpg',
        );
        app(InventoryCountService::class)->approveCountSession($submitted, $owner);

        $this->assertEquals(65, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'inventory_count',
            'direction' => 'out',
            'quantity' => 5,
        ]);
        $this->assertDatabaseMissing('inventory_count_sessions', [
            'branch_id' => $central->id,
            'type' => 'branch_closing',
        ]);
    }

    public function test_branch_manager_can_open_branch_closing_from_route(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'is_central_warehouse' => false,
            'warehouse_type' => 'branch',
        ]);
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Cái',
            'symbol' => 'cái',
            'type' => 'quantity',
        ]);
        Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu route',
            'sku' => 'BRANCH-CLOSING-ROUTE-01',
            'average_cost' => 50,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)->postJson(route('inventory.branch-closing.store'), [
            'branch_id' => $branch->id,
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.type', 'branch_closing');
        $this->assertDatabaseHas('inventory_count_sessions', [
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'type' => 'branch_closing',
        ]);
    }

    public function test_central_warehouse_account_cannot_open_branch_closing(): void
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        $this->actingAs($warehouseManager)
            ->get(route('inventory.branch-closing'))
            ->assertForbidden();
    }

    private function transaction(
        int $restaurantId,
        int $branchId,
        int $ingredientId,
        int $inventoryId,
        int $performedBy,
        string $direction,
        float $quantity,
        CarbonInterface $occurredAt,
    ): void {
        InventoryTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'ingredient_id' => $ingredientId,
            'inventory_id' => $inventoryId,
            'performed_by' => $performedBy,
            'type' => $direction === 'in' ? 'purchase' : 'usage',
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => 100,
            'total_cost' => $quantity * 100,
            'occurred_at' => $occurredAt,
        ]);
    }
}
