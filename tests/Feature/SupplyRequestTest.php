<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_inventory_and_supplier_roles_have_expected_permissions()
    {
        $restaurant = Restaurant::factory()->create();

        $warehouseManager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $warehouseManager->assignRole('warehouse_manager');

        $warehouseStaff = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $warehouseStaff->assignRole('warehouse_staff');

        $inventoryStaff = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $inventoryStaff->assignRole('inventory_staff');

        $supplier = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $supplier->assignRole('supplier');

        $this->assertTrue($warehouseManager->canViewAllBranches());
        $this->assertTrue($warehouseManager->can('warehouse.manage'));
        $this->assertTrue($warehouseManager->can('warehouse_governance.manage'));
        $this->assertTrue($warehouseManager->can('supply_requests.approve'));
        $this->assertTrue($warehouseManager->can('supply_requests.dispatch'));
        $this->assertFalse($warehouseManager->can('supply_requests.create'));
        $this->assertFalse($warehouseManager->can('supply_requests.receive'));

        $this->assertTrue($warehouseStaff->can('warehouse.view'));
        $this->assertTrue($warehouseStaff->can('supply_requests.dispatch'));
        $this->assertFalse($warehouseStaff->can('supply_requests.approve'));
        $this->assertFalse($warehouseStaff->can('supply_requests.receive'));

        $this->assertTrue($inventoryStaff->can('adjust_inventory'));
        $this->assertTrue($inventoryStaff->can('supply_requests.create'));
        $this->assertTrue($inventoryStaff->can('supply_requests.receive'));
        $this->assertFalse($inventoryStaff->can('warehouse.manage'));

        $this->assertTrue($supplier->can('supplier.portal.view'));
        $this->assertFalse($supplier->can('warehouse.view'));
        $this->assertFalse($supplier->can('operational_audit.report'));
    }

    public function test_branch_can_create_supply_request_to_central_warehouse()
    {
        $restaurant = Restaurant::factory()->create();

        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'name' => 'Kho Tổng Miền Nam',
        ]);

        $branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'name' => 'Chi nhánh Quận 1',
        ]);

        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilôgam',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Thịt Bò Mỹ',
            'sku' => 'BEEF-01',
            'average_cost' => 250000,
        ]);

        // Add 100kg to Central Warehouse stock
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch1->id,
        ]);

        $warehouseStaff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
        ]);

        $service = app(CentralWarehouseService::class);

        // 1. Branch Manager creates supply request for 20kg beef
        $supplyRequest = $service->createSupplyRequest(
            $restaurant->id,
            $branch1->id,
            $manager,
            [
                ['ingredient_id' => $ingredient->id, 'quantity' => 20],
            ],
            now()->addDay()->toDateString(),
            'Cần hàng gấp sáng mai'
        );

        $this->assertEquals('pending', $supplyRequest->status);
        $this->assertEquals(5000000, $supplyRequest->total_amount);

        // 2. Central Warehouse approves request
        $approvedRequest = $service->approveSupplyRequest($supplyRequest, $warehouseStaff);
        $this->assertEquals('approved', $approvedRequest->status);

        // 3. Central Warehouse dispatches items (deducts central stock)
        $dispatchedRequest = $service->dispatchSupplyRequest($approvedRequest, $warehouseStaff, 'SEAL-DEMO-001');
        $this->assertEquals('dispatched', $dispatchedRequest->status);

        $centralInventory = Inventory::where('branch_id', $centralBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();
        $this->assertEquals(80, $centralInventory->quantity_on_hand);

        // 4. Branch receives items (adds to branch stock)
        $completedRequest = $service->receiveSupplyRequest($dispatchedRequest, $manager);
        $this->assertEquals('completed', $completedRequest->status);

        $branchInventory = Inventory::where('branch_id', $branch1->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();
        $this->assertEquals(20, $branchInventory->quantity_on_hand);
    }
}
