<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralWarehouseWorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_can_open_each_separated_central_warehouse_workspace(): void
    {
        $restaurant = Restaurant::factory()->create();
        RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => RestaurantBranch::where('restaurant_id', $restaurant->id)->value('id'),
        ]);
        $manager->assignRole('warehouse_manager');

        foreach ([
            ['route' => 'inventory.central-warehouse', 'component' => 'inventory/CentralWarehouseOverview'],
            ['route' => 'inventory.central-warehouse.stock', 'component' => 'inventory/CentralWarehouseInventory'],
            ['route' => 'inventory.central-warehouse.requests', 'component' => 'inventory/CentralWarehouse'],
            ['route' => 'inventory.central-warehouse.receiving', 'component' => 'inventory/CentralWarehouseReceiving'],
            ['route' => 'inventory.central-warehouse.prices', 'component' => 'inventory/CentralWarehousePrices'],
        ] as $workspace) {
            $response = $this->actingAs($manager)->get(route($workspace['route']));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page->component($workspace['component']));
        }

        $this->actingAs($manager)
            ->get(route('inventory.index'))
            ->assertRedirect(route('inventory.central-warehouse.stock'));
    }

    public function test_central_stock_workspace_does_not_mix_branch_inventory(): void
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
        ]);
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $centralIngredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu Kho Tổng',
            'min_stock_level' => 10,
        ]);
        $branchIngredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu Chi nhánh',
        ]);
        $centralInventory = Inventory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $centralIngredient->id,
            'quantity_on_hand' => 50,
        ]);
        Inventory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $branchIngredient->id,
            'quantity_on_hand' => 80,
        ]);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');
        InventoryReservation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $centralIngredient->id,
            'supply_request_id' => null,
            'reservation_type' => 'supply_request',
            'quantity' => 12,
            'expires_at' => now()->addDay(),
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(route('inventory.central-warehouse.stock'));
        $response->assertOk();
        $response->assertInertia(function ($page) use ($centralInventory): void {
            $items = collect($page->toArray()['props']['centralStockItems']);
            $centralItem = $items->firstWhere('name', 'Nguyên liệu Kho Tổng');

            $this->assertNotContains('Nguyên liệu Chi nhánh', $items->pluck('name')->all());
            $this->assertNotNull($centralItem);
            $this->assertEquals(50.0, $centralItem['on_hand']);
            $this->assertEquals(12.0, $centralItem['reserved']);
            $this->assertEquals(38.0, $centralItem['available']);
            $this->assertEquals($centralInventory->id, $centralItem['inventory_id']);
        });
    }

    public function test_central_workspaces_and_staff_portal_stay_within_central_catalog(): void
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
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $centralIngredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu dùng tại Kho Tổng',
        ]);
        $branchIngredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => 'Nguyên liệu riêng chi nhánh',
        ]);
        WarehouseLocation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'zone' => 'A',
            'location_code' => 'CENTRAL-A-01',
        ]);
        WarehouseLocation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'zone' => 'B',
            'location_code' => 'BRANCH-B-01',
        ]);

        $branchWorkOrder = WorkOrder::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'work_order_code' => 'WO-BRANCH-0001',
            'output_ingredient_id' => $branchIngredient->id,
            'target_quantity' => 10,
            'status' => WorkOrder::STATUS_DRAFT,
            'production_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(7)->toDateString(),
        ]);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');

        $this->actingAs($manager)
            ->get(route('inventory.central-warehouse.prices'))
            ->assertOk()
            ->assertInertia(function ($page) use ($centralIngredient, $branchIngredient): void {
                $ingredients = collect($page->toArray()['props']['ingredients']);

                $this->assertContains($centralIngredient->id, $ingredients->pluck('id')->all());
                $this->assertNotContains($branchIngredient->id, $ingredients->pluck('id')->all());
            });

        $this->actingAs($manager)
            ->get(route('inventory.central-kitchen'))
            ->assertOk()
            ->assertInertia(function ($page) use ($centralIngredient, $branchIngredient, $branchWorkOrder): void {
                $props = $page->toArray()['props'];
                $ingredientIds = collect($props['ingredients'])->pluck('id')->all();
                $workOrderIds = collect($props['workOrders'])->pluck('id')->all();

                $this->assertContains($centralIngredient->id, $ingredientIds);
                $this->assertNotContains($branchIngredient->id, $ingredientIds);
                $this->assertNotContains($branchWorkOrder->id, $workOrderIds);
                $this->assertTrue($props['canManageWarehouse']);
            });

        $this->actingAs($staff)
            ->get(route('inventory.staff-portal'))
            ->assertOk()
            ->assertInertia(function ($page) use ($centralIngredient, $branchIngredient): void {
                $props = $page->toArray()['props'];
                $ingredientIds = collect($props['ingredients'])->pluck('id')->all();
                $locationCodes = collect($props['locations'])->pluck('location_code')->all();

                $this->assertContains($centralIngredient->id, $ingredientIds);
                $this->assertNotContains($branchIngredient->id, $ingredientIds);
                $this->assertContains('CENTRAL-A-01', $locationCodes);
                $this->assertNotContains('BRANCH-B-01', $locationCodes);
            });

        $this->actingAs($staff)
            ->get(route('inventory.central-kitchen'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManageWarehouse', false));

        $this->actingAs($staff)
            ->get(route('inventory.batch-recalls'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManageWarehouse', false));

        $this->actingAs($staff)
            ->get(route('inventory.transfers'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson(route('warehouse.ingredient-prices.update'), [
                'prices' => [[
                    'ingredient_id' => $branchIngredient->id,
                    'average_cost' => 12345,
                ]],
            ])
            ->assertStatus(403);

        $this->actingAs($manager)
            ->postJson(route('central-kitchen.work-orders.execute', $branchWorkOrder->id), [
                'actual_yield_quantity' => 5,
            ])
            ->assertNotFound();

        try {
            app(\App\Services\CentralWarehouseService::class)->createSupplyRequest(
                $restaurant->id,
                $branch->id,
                $manager,
                [['ingredient_id' => $branchIngredient->id, 'quantity' => 1]],
            );
            $this->fail('Nguyên liệu riêng chi nhánh không được phép tạo đơn cấp phát từ Kho Tổng.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('ngoài phạm vi Kho Tổng', $exception->getMessage());
        }
    }
}
