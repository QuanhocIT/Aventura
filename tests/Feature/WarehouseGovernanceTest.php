<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SalaryAdjustment;
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
        $warehouseStaff->assignRole('warehouse_staff');

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        $service = app(CentralWarehouseService::class);
        $govService = app(WarehouseGovernanceService::class);

        // 1. Branch Manager creates request for 10kg
        $supplyRequest = $service->createSupplyRequest(
            $restaurant->id,
            $branch1->id,
            $manager,
            [['ingredient_id' => $ingredient->id, 'quantity' => 10]]
        );

        // 2. Central Warehouse approves, prepares, approves dispatch & dispatches 10kg with Seal Code
        $approved = $service->approveSupplyRequest($supplyRequest, $warehouseManager);
        $prepared = $service->prepareDispatch($approved, $warehouseStaff, [
            ['id' => $approved->items->first()->id, 'actual_dispatched_quantity' => 8],
        ]);
        $dispatchApproved = $service->approveDispatch($prepared, $warehouseManager);
        $dispatched = $service->dispatchSupplyRequest($dispatchApproved, $warehouseStaff, 'SEAL-998877');

        $this->assertEquals('SEAL-998877', $dispatched->seal_code);

        // 3. Branch receives ONLY 7kg (missing 1kg from the physical 8kg dispatch)
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
        $this->assertEquals(8.0, (float) $dispute->dispatched_quantity);
        $this->assertEquals(1.0, (float) $dispute->discrepancy_quantity);
        $this->assertEquals(400000, (float) $dispute->financial_loss_amount);
        $this->assertEquals('open', $dispute->status);

        // A retry of the receiving callback must not create a second dispute.
        $this->assertCount(0, $govService->checkAndCreateDisputesFromSupplyRequest(
            $completed,
            [['id' => $completed->items->first()->id, 'received_quantity' => 7]],
        ));
        $this->assertDatabaseCount('inventory_discrepancy_disputes', 1);

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

    public function test_resolving_internal_responsibility_creates_one_payroll_adjustment_when_enabled(): void
    {
        $restaurant = Restaurant::factory()->create();
        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
        ]);
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'average_cost' => 100000,
        ]);
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'warehouse_branch_id' => $centralBranch->id,
        ]);
        $staff->assignRole('warehouse_staff');
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');
        $employee = Employee::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'user_id' => $staff->id,
            'employee_code' => 'WH-001',
            'full_name' => $staff->name,
            'base_salary' => 12000000,
            'status' => 'active',
        ]);

        $dispute = InventoryDiscrepancyDispute::create([
            'restaurant_id' => $restaurant->id,
            'dispute_code' => 'DSP-PAYROLL-001',
            'ingredient_id' => $ingredient->id,
            'dispatched_quantity' => 10,
            'received_quantity' => 8,
            'discrepancy_quantity' => 2,
            'financial_loss_amount' => 200000,
            'responsible_type' => 'unassigned',
            'status' => 'open',
        ]);

        $resolved = app(WarehouseGovernanceService::class)->resolveDispute(
            $dispute->id,
            $restaurant->id,
            $manager,
            'warehouse_staff',
            $staff->id,
            'Đã xác minh lỗi đóng gói.',
        );

        $this->assertEquals('penalized', $resolved->status);
        $this->assertDatabaseHas('salary_adjustments', [
            'restaurant_id' => $restaurant->id,
            'employee_id' => $employee->id,
            'type' => 'inventory_loss',
            'amount' => 200000,
            'reference_id' => $dispute->id,
            'reference_type' => InventoryDiscrepancyDispute::class,
        ]);
        $this->assertEquals(1, SalaryAdjustment::where('reference_id', $dispute->id)->count());
    }
}
