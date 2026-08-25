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
        $approver->assignRole('owner');

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
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'inventory_count',
            'direction' => 'out',
            'quantity' => 4,
        ]);
    }

    public function test_mismatched_second_count_requires_reconciliation_before_approval()
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Thịt bò',
            'sku' => 'BEEF-01',
            'average_cost' => 100000,
        ]);

        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 50,
        ]);

        $counter1 = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $counter1->assignRole('inventory_staff');
        $counter2 = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $counter2->assignRole('inventory_staff');

        $service = app(InventoryCountService::class);
        $session = $service->startCountSession($restaurant->id, $branch->id, $counter1);
        $item = $session->items->first();

        $countedOnce = $service->submitCounts($session, $counter1, [
            ['id' => $item->id, 'counted_quantity' => 46],
        ]);
        $countedTwice = $service->submitCounts($countedOnce, $counter2, [
            ['id' => $item->id, 'counted_quantity' => 45],
        ]);

        $item = $countedTwice->fresh()->items->first();
        $this->assertSame('pending', $item->reconciliation_status);
        $this->assertNull($item->final_quantity);

        try {
            $service->finalizeAndSubmitForApproval($countedTwice);
            $this->fail('A session with unreconciled counts must not be submitted.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('hai lan dem khong khop', $exception->getMessage());
        }

        $reconciled = $service->reconcileItem(
            $countedTwice,
            $counter1,
            $item->id,
            45,
            'Đã kiểm đếm lại tại khu lưu trữ và thống nhất số lượng thực tế.',
        );

        $this->assertSame('resolved', $reconciled->items->first()->reconciliation_status);
        $this->assertEquals(45, $reconciled->items->first()->final_quantity);

        $submitted = $service->finalizeAndSubmitForApproval($reconciled, '/photos/recount.jpg');
        $this->assertSame('pending_approval', $submitted->status);
    }
}
