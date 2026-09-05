<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryCountItem;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\MaterialClosingService;
use App\Services\WarehouseTaskService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class MaterialClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_closing_reconstructs_period_balance_and_employee_task(): void
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
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
            'name' => 'Nguyên liệu chốt',
            'sku' => 'CLOSING-01',
            'average_cost' => 100,
        ]);
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 70,
            'last_cost' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'warehouse_staff_status' => 'active',
        ]);
        $staff->assignRole('warehouse_staff');

        $approver = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $approver->assignRole('owner');

        $periodStart = now()->subDays(20)->startOfDay();
        $periodEnd = now()->subDays(5)->endOfDay();
        $this->transaction($restaurant->id, $central->id, $ingredient->id, $inventory->id, $manager->id, 'in', 10, $periodStart->copy()->addDays(2));
        $this->transaction($restaurant->id, $central->id, $ingredient->id, $inventory->id, $manager->id, 'out', 5, $periodStart->copy()->addDays(5));
        $this->transaction($restaurant->id, $central->id, $ingredient->id, $inventory->id, $manager->id, 'out', 5, now()->subDays(2));

        $session = app(MaterialClosingService::class)->start(
            $restaurant->id,
            $central->id,
            $manager,
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
        );

        $item = $session->items->first();
        $this->assertSame('material_closing', $session->type);
        $this->assertEquals(70, $item->opening_quantity);
        $this->assertEquals(10, $item->inbound_quantity);
        $this->assertEquals(5, $item->outbound_quantity);
        $this->assertEquals(75, $item->expected_quantity);
        $this->assertEquals(7500, $item->expected_value);

        app(InventoryCountService::class)->assignSecondCounter($session, $manager, $staff);
        app(WarehouseTaskService::class)->assignCountingTask($manager, $session, $staff, ['priority' => 'high']);

        $response = $this->actingAs($staff)->postJson(
            route('inventory.material-closing.counts', $session->id),
            ['items' => [['id' => $item->id, 'counted_quantity' => 70]]],
        );
        $response->assertOk();

        $this->assertDatabaseHas('warehouse_task_assignments', [
            'count_session_id' => $session->id,
            'assigned_to' => $staff->id,
            'task_type' => 'counting',
            'status' => 'completed',
        ]);

        $counted = InventoryCountSession::findOrFail($session->id);
        $this->assertEquals(500, $counted->fresh()->total_shortage_value);

        Storage::fake('local');
        Storage::disk('local')->put('proof/closing.jpg', 'proof');
        $submitted = app(InventoryCountService::class)->finalizeAndSubmitForApproval($counted, 'proof/closing.jpg');
        app(InventoryCountService::class)->approveCountSession($submitted, $approver);

        $this->assertEquals(65, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_transactions', [
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'inventory_count',
            'direction' => 'out',
            'quantity' => 5,
        ]);
    }

    public function test_next_material_closing_must_start_at_last_approved_boundary(): void
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
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
            'name' => 'Nguyên liệu liên kỳ',
            'sku' => 'CONTIGUOUS-01',
            'average_cost' => 100,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 10,
            'last_cost' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $previous = InventoryCountSession::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'type' => 'material_closing',
            'status' => 'approved',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'period_start_at' => '2026-08-01 00:00:00',
            'period_end_at' => '2026-08-15 23:59:59',
            'counted_by' => $manager->id,
            'approved_by' => $manager->id,
            'approved_at' => '2026-08-16 09:00:00',
        ]);

        try {
            app(MaterialClosingService::class)->start(
                $restaurant->id,
                $central->id,
                $manager,
                '2026-08-20',
                '2026-08-31',
            );
            $this->fail('A gap after the last approved closing must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('15/08/2026', $exception->getMessage());
        }

        $next = app(MaterialClosingService::class)->start(
            $restaurant->id,
            $central->id,
            $manager,
            '2026-08-15',
            '2026-08-31',
        );

        $this->assertSame($previous->id, (int) $next->previous_session_id);
        $this->assertSame('2026-08-15', $next->period_start->toDateString());
        $this->assertSame('2026-08-16 00:00:00', $next->period_start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 23:59:59', $next->period_end_at->format('Y-m-d H:i:s'));
    }

    public function test_count_item_distinguishes_negative_stock_from_shortage(): void
    {
        $negative = new InventoryCountItem([
            'expected_quantity' => -5,
            'unit_cost' => 100,
            'final_quantity' => 0,
            'variance_quantity' => 5,
        ]);

        $shortage = new InventoryCountItem([
            'expected_quantity' => 10,
            'unit_cost' => 100,
            'final_quantity' => 7,
            'variance_quantity' => -3,
        ]);

        $this->assertSame('negative_stock', $negative->inventory_status);
        $this->assertTrue($negative->system_negative);
        $this->assertSame(5.0, $negative->system_negative_quantity);
        $this->assertSame(500.0, $negative->system_negative_value);
        $this->assertSame('shortage', $shortage->inventory_status);
        $this->assertFalse($shortage->system_negative);
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
