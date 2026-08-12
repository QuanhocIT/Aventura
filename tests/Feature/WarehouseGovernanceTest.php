<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Services\CentralWarehouseService;
use App\Services\WarehouseGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_governance_rules_threshold_and_auto_dispute_creation()
    {
        $restaurant = Restaurant::factory()->create();

        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
        ]);

        $branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
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
            'name' => 'Cá Hồi Nắn',
            'sku' => 'SALMON-01',
            'average_cost' => 400000,
        ]);

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

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
        ]);

        $service = app(CentralWarehouseService::class);
        $govService = app(WarehouseGovernanceService::class);

        // 1. Branch Manager creates request for 10kg
        $supplyRequest = $service->createSupplyRequest(
            $restaurant->id,
            $branch1->id,
            $manager,
            [['ingredient_id' => $ingredient->id, 'quantity' => 10]]
        );

        // 2. Central Warehouse approves & dispatches 10kg with Seal Code
        $service->approveSupplyRequest($supplyRequest, $warehouseStaff);
        $dispatched = $service->dispatchSupplyRequest($supplyRequest, $warehouseStaff, 'SEAL-998877');

        $this->assertEquals('SEAL-998877', $dispatched->seal_code);

        // 3. Branch receives ONLY 7kg (missing 3kg salmon = 1,200,000 VND loss)
        $completed = $service->receiveSupplyRequest(
            $dispatched,
            $manager,
            [['id' => $dispatched->items->first()->id, 'received_quantity' => 7]],
            '/uploads/receipts/proof_7kg.jpg',
            '/uploads/signatures/sign_manager.png'
        );

        $this->assertTrue($completed->discrepancy_flag);

        // 4. Verify auto created dispute
        $dispute = InventoryDiscrepancyDispute::where('supply_request_id', $completed->id)->first();
        $this->assertNotNull($dispute);
        $this->assertEquals(3.0, (float) $dispute->discrepancy_quantity);
        $this->assertEquals(1200000, (float) $dispute->financial_loss_amount);
        $this->assertEquals('open', $dispute->status);

        // 5. Warehouse Manager resolves dispute and assigns penalty to warehouseStaff
        $resolved = $govService->resolveDispute(
            $dispute->id,
            $restaurant->id,
            $warehouseManager,
            'warehouse_staff',
            $warehouseStaff->id,
            'Nhân viên đóng gói thiếu 3kg cá hồi tại kho tổng.'
        );

        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals('warehouse_staff', $resolved->responsible_type);
        $this->assertEquals($warehouseStaff->id, $resolved->responsible_user_id);
    }
}
