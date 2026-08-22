<?php

namespace Tests\Feature;

use App\Models\CentralBom;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryNegativeCase;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CentralKitchenService;
use App\Services\CentralWarehouseService;
use App\Services\DeliveryManifestService;
use App\Services\BatchRecallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralWarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected RestaurantBranch $centralWarehouse;
    protected RestaurantBranch $branch;
    protected User $manager;
    protected Unit $unit;
    protected Ingredient $rawIngredient;
    protected Ingredient $wipIngredient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $this->unit = Unit::firstOrCreate([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Kilogram',
            'symbol'        => 'kg',
        ]);

        $this->centralWarehouse = RestaurantBranch::create([
            'restaurant_id'        => $this->restaurant->id,
            'code'                 => 'WH-CENTRAL',
            'name'                 => 'Kho Tổng Sài Gòn',
            'status'               => 'active',
            'is_central_warehouse' => true,
            'warehouse_type'       => 'central',
        ]);

        $this->branch = RestaurantBranch::create([
            'restaurant_id'        => $this->restaurant->id,
            'code'                 => 'BR-Q1',
            'name'                 => 'Chi Nhánh Quận 1',
            'status'               => 'active',
            'is_central_warehouse' => false,
            'warehouse_type'       => 'business',
        ]);

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id'     => $this->centralWarehouse->id,
        ]);
        $this->manager->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));

        $this->rawIngredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $this->unit->id,
            'name'          => 'Thịt Bò Thô',
            'sku'           => 'RAW-BEEF',
            'average_cost'  => 150000,
        ]);

        $this->wipIngredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id'       => $this->unit->id,
            'name'          => 'Thịt Bò Thái Lát Ướp Sốt',
            'sku'           => 'WIP-BEEF',
            'average_cost'  => 180000,
        ]);

        // Stock in Central Warehouse
        Inventory::create([
            'restaurant_id'    => $this->restaurant->id,
            'branch_id'        => $this->centralWarehouse->id,
            'ingredient_id'    => $this->rawIngredient->id,
            'quantity_on_hand' => 100.0,
        ]);
    }

    public function test_central_kitchen_creates_and_executes_work_order(): void
    {
        $service = app(CentralKitchenService::class);

        $bom = $service->createBom($this->restaurant->id, [
            'name'                 => 'Định mức Bò Thái Ướp',
            'output_ingredient_id' => $this->wipIngredient->id,
            'standard_output_qty'  => 10,
            'items'                => [
                [
                    'input_ingredient_id' => $this->rawIngredient->id,
                    'required_quantity'   => 12,
                ],
            ],
        ], $this->manager);

        $this->assertDatabaseHas('central_boms', ['id' => $bom->id]);

        $workOrder = $service->createWorkOrder($this->restaurant->id, $this->centralWarehouse->id, [
            'central_bom_id'       => $bom->id,
            'output_ingredient_id' => $this->wipIngredient->id,
            'target_quantity'      => 10,
        ], $this->manager);

        $executed = $service->executeWorkOrder($workOrder, $this->manager, 9.5);

        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $executed->status);
        $this->assertDatabaseHas('inventory_batches', ['id' => $executed->created_batch_id]);

        // Stock of raw ingredient reduced from 100 to 88
        $this->assertDatabaseHas('inventories', [
            'branch_id'        => $this->centralWarehouse->id,
            'ingredient_id'    => $this->rawIngredient->id,
            'quantity_on_hand' => 88.0,
        ]);
    }

    public function test_central_kitchen_records_negative_raw_stock_and_opens_case(): void
    {
        $service = app(CentralKitchenService::class);
        $bom = $service->createBom($this->restaurant->id, [
            'name' => 'Định mức vượt tồn',
            'output_ingredient_id' => $this->wipIngredient->id,
            'standard_output_qty' => 10,
            'items' => [[
                'input_ingredient_id' => $this->rawIngredient->id,
                'required_quantity' => 120,
            ]],
        ], $this->manager);
        $workOrder = $service->createWorkOrder($this->restaurant->id, $this->centralWarehouse->id, [
            'central_bom_id' => $bom->id,
            'output_ingredient_id' => $this->wipIngredient->id,
            'target_quantity' => 10,
        ], $this->manager);

        $service->executeWorkOrder($workOrder, $this->manager, 10);

        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->rawIngredient->id,
            'quantity_on_hand' => -20.0,
            'theoretical_quantity' => -20.0,
        ]);
        $this->assertDatabaseHas('inventory_negative_cases', [
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->rawIngredient->id,
            'negative_quantity' => 20,
            'status' => 'open',
        ]);
        $this->assertSame(1, InventoryNegativeCase::withoutGlobalScopes()
            ->where('branch_id', $this->centralWarehouse->id)
            ->where('ingredient_id', $this->rawIngredient->id)
            ->where('status', 'open')
            ->count());
    }

    public function test_smart_allocation_suggests_fair_share_when_stock_is_low(): void
    {
        $centralService = app(CentralWarehouseService::class);

        // Raw stock is 100.
        // Create 2 supply requests: Branch 1 requests 80, Branch 2 requests 80 (Total = 160 > 100)
        $req1 = $centralService->createSupplyRequest($this->restaurant->id, $this->branch->id, $this->manager, [
            ['ingredient_id' => $this->rawIngredient->id, 'quantity' => 80],
        ]);

        $branch2 = RestaurantBranch::create([
            'restaurant_id' => $this->restaurant->id,
            'code'          => 'BR-Q3',
            'name'          => 'Chi Nhánh Quận 3',
            'status'        => 'active',
        ]);

        $req2 = $centralService->createSupplyRequest($this->restaurant->id, $branch2->id, $this->manager, [
            ['ingredient_id' => $this->rawIngredient->id, 'quantity' => 80],
        ]);

        $suggestions = $centralService->suggestSmartAllocation($this->restaurant->id, [$req1->id, $req2->id]);

        $this->assertCount(2, $suggestions);
        $this->assertTrue($suggestions[0]['is_shortage']);
        $this->assertEquals(50.0, $suggestions[0]['suggested_qty']); // 80 * (100 / 160) = 50
    }

    public function test_delivery_manifest_groups_and_dispatches_supply_requests(): void
    {
        $centralService  = app(CentralWarehouseService::class);
        $manifestService = app(DeliveryManifestService::class);

        $req = $centralService->createSupplyRequest($this->restaurant->id, $this->branch->id, $this->manager, [
            ['ingredient_id' => $this->rawIngredient->id, 'quantity' => 20],
        ]);

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        $approved = $centralService->approveSupplyRequest($req, $warehouseManager);
        $prepared = $centralService->prepareDispatch($approved, $this->manager, [
            ['id' => $approved->items->first()->id, 'actual_dispatched_quantity' => 20],
        ]);
        $dispatchApproved = $centralService->approveDispatch($prepared, $warehouseManager);

        $manifest = $manifestService->createManifest($this->restaurant->id, $this->centralWarehouse->id, [
            'route_name'         => 'Tuyến Q1 - Q3',
            'driver_name'        => 'Tài xế B',
            'vehicle_number'     => '51D-12345',
            'supply_request_ids' => [$dispatchApproved->id],
        ], $this->manager);

        $this->assertDatabaseHas('delivery_manifests', ['id' => $manifest->id]);

        $dispatched = $manifestService->dispatchManifest($manifest, $this->manager, 'SEAL-9999');
        $this->assertEquals('dispatched', $dispatched->status);
    }

    public function test_central_dispatch_keeps_shortage_as_negative_stock_case(): void
    {
        $centralService = app(CentralWarehouseService::class);
        $request = $centralService->createSupplyRequest($this->restaurant->id, $this->branch->id, $this->manager, [
            ['ingredient_id' => $this->rawIngredient->id, 'quantity' => 10],
        ]);
        $warehouseManager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');
        $warehouseStaff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->centralWarehouse->id,
        ]);
        $warehouseStaff->assignRole('warehouse_staff');

        $approved = $centralService->approveSupplyRequest($request, $warehouseManager);
        $prepared = $centralService->prepareDispatch($approved, $warehouseStaff, [
            ['id' => $approved->items->first()->id, 'actual_dispatched_quantity' => 10],
        ]);
        $dispatchApproved = $centralService->approveDispatch($prepared, $warehouseManager);

        Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('branch_id', $this->centralWarehouse->id)
            ->where('ingredient_id', $this->rawIngredient->id)
            ->update(['quantity_on_hand' => 0, 'theoretical_quantity' => 0]);

        $dispatched = $centralService->dispatchSupplyRequest($dispatchApproved, $warehouseStaff, 'SEAL-NEG-001');

        $this->assertSame('dispatched', $dispatched->status);
        $this->assertDatabaseHas('inventories', [
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->rawIngredient->id,
            'quantity_on_hand' => -10.0,
            'theoretical_quantity' => -10.0,
        ]);
        $this->assertDatabaseHas('inventory_negative_cases', [
            'branch_id' => $this->centralWarehouse->id,
            'ingredient_id' => $this->rawIngredient->id,
            'negative_quantity' => 10,
            'status' => 'open',
        ]);
    }

    public function test_batch_recall_order_locks_batch_systemwide(): void
    {
        $recallService = app(BatchRecallService::class);

        $batch = InventoryBatch::create([
            'restaurant_id'      => $this->restaurant->id,
            'branch_id'          => $this->centralWarehouse->id,
            'ingredient_id'      => $this->rawIngredient->id,
            'batch_code'         => 'LOT-EXPIRED-TEST',
            'quantity_remaining' => 50,
            'purchased_at'       => now(),
            'status'             => 'active',
        ]);

        $recall = $recallService->initiateRecall($this->restaurant->id, $batch->id, [
            'severity' => 'critical',
            'reason'   => 'Kiểm nghiệm vi sinh bị lỗi',
        ], $this->manager);

        $this->assertEquals('recalled', $batch->fresh()->status);
        $this->assertEquals(1, $recall->affected_branches_count);
    }
}
