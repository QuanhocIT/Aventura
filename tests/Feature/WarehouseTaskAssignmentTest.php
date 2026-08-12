<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_picking_task_and_staff_can_update_progress(): void
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'WH-CENTRAL',
            'name' => 'Kho Tổng',
            'status' => 'active',
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $branch = RestaurantBranch::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'BR-01',
            'name' => 'Chi nhánh 01',
            'status' => 'active',
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
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
            'name' => 'Rau xanh',
            'sku' => 'VEG-01',
            'average_cost' => 50000,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');

        $supplyRequest = app(CentralWarehouseService::class)->createSupplyRequest(
            $restaurant->id,
            $branch->id,
            $manager,
            [['ingredient_id' => $ingredient->id, 'quantity' => 10]],
        );
        $approvedRequest = app(CentralWarehouseService::class)->approveSupplyRequest($supplyRequest, $manager);

        $response = $this->actingAs($manager)->postJson(route('warehouse.tasks.assign'), [
            'supply_request_id' => $approvedRequest->id,
            'assigned_to' => $staff->id,
            'task_type' => 'picking',
            'priority' => 'high',
        ]);

        $response->assertOk()->assertJsonPath('data.assigned_to', $staff->id);
        $task = WarehouseTaskAssignment::firstOrFail();
        $this->assertSame('assigned', $task->status);

        $this->actingAs($staff)
            ->postJson(route('warehouse.tasks.status', $task->id), ['status' => 'in_progress'])
            ->assertOk();

        $this->assertDatabaseHas('warehouse_task_assignments', [
            'id' => $task->id,
            'assigned_to' => $staff->id,
            'status' => 'in_progress',
        ]);
    }
}
