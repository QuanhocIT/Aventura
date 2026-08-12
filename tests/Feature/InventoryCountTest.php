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
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_count_workflow_with_variance_and_ledger_adjustment()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Cá Hồi',
            'sku' => 'FISH-01',
            'average_cost' => 300000,
        ]);

        // Stock in branch = 50kg
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 50,
        ]);

        $counter = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $counter->assignRole('inventory_staff');

        $approver = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $approver->assignRole('warehouse_manager');

        $service = app(InventoryCountService::class);

        // 1. Start count session
        $session = $service->startCountSession($restaurant->id, $branch->id, $counter, 'periodic', false);
        $this->assertEquals('in_progress', $session->status);
        $this->assertCount(1, $session->items);
        $this->assertEquals(50, $session->items->first()->expected_quantity);

        // 2. Submit count = 46kg (shortage of 4kg = loss of 1,200,000 VND)
        $countedSession = $service->submitCounts($session, $counter, [
            ['id' => $session->items->first()->id, 'counted_quantity' => 46, 'notes' => 'Hao hụt tự nhiên'],
        ]);
        $this->assertEquals(-4, $countedSession->items->first()->variance_quantity);

        // 3. Finalize and submit for approval
        $submittedSession = $service->finalizeAndSubmitForApproval(
            $countedSession,
            '/photos/variance.jpg',
            'Đã giải trình chi tiết'
        );
        $this->assertEquals('pending_approval', $submittedSession->status);

        // 4. Approve count session -> Adjusts inventory and creates transaction
        $approvedSession = $service->approveCountSession($submittedSession, $approver);
        $this->assertEquals('approved', $approvedSession->status);

        $inventory->refresh();
        $this->assertEquals(46, $inventory->quantity_on_hand);

        // Assert Ledger transaction recorded
        $this->assertDatabaseHas('inventory_transactions', [
            'branch_id'     => $branch->id,
            'ingredient_id' => $ingredient->id,
            'type'          => 'inventory_count',
            'direction'     => 'out',
            'quantity'      => 4,
        ]);
    }
}
