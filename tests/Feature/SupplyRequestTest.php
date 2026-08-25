<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

        $this->assertTrue($warehouseStaff->can('warehouse.view'));
        $this->assertTrue($warehouseStaff->can('warehouse.pick'));
        $this->assertTrue($warehouseStaff->can('warehouse.pack'));
        $this->assertTrue($warehouseStaff->can('warehouse.handover'));
        $this->assertFalse($warehouseStaff->can('supply_requests.approve'));

        $this->assertTrue($inventoryStaff->can('adjust_inventory'));
        $this->assertTrue($inventoryStaff->can('supply_requests.create'));
        $this->assertTrue($inventoryStaff->can('supply_requests.receive'));

        $this->assertTrue($supplier->can('supplier.portal.view'));
    }

    public function test_branch_supply_request_flow_with_reservations_and_disputes()
    {
        $restaurant = Restaurant::factory()->create();

        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'name' => 'Kho Tổng Miền Nam',
        ]);

        $branch1 = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
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

        // Stock = 100kg in Central Warehouse
        $inventory = Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 100,
        ]);

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch1->id,
        ]);
        $manager->assignRole('manager');

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        $warehouseStaff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
        ]);
        $warehouseStaff->assignRole('warehouse_staff');

        Notification::fake();
        $service = app(CentralWarehouseService::class);

        // 1. Branch Manager creates supply request for 60kg beef
        $supplyRequest = $service->createSupplyRequest(
            $restaurant->id,
            $branch1->id,
            $manager,
            [
                ['ingredient_id' => $ingredient->id, 'quantity' => 60],
            ],
            now()->addDay()->toDateString(),
            'Cần hàng gấp sáng mai'
        );

        $this->assertEquals(SupplyRequest::STATUS_PENDING, $supplyRequest->status);
        $this->assertEquals(15000000, $supplyRequest->total_amount);

        // 2. Central Warehouse approves -> Holds Inventory Reservation
        $approvedRequest = $service->approveSupplyRequest($supplyRequest, $warehouseManager);
        $this->assertEquals(SupplyRequest::STATUS_APPROVED, $approvedRequest->status);

        // Assert Reservation exists and quantity_available is reduced
        $this->assertDatabaseHas('inventory_reservations', [
            'supply_request_id' => $supplyRequest->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 60,
        ]);

        $inventory->refresh();
        $this->assertEquals(100, $inventory->quantity_on_hand);
        $this->assertEquals(40, $inventory->quantity_available);

        // 3. Second request for 50kg beef must fail because available is only 40kg
        $branch2 = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'name' => 'Chi nhánh Quận 3',
        ]);
        $manager2 = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch2->id]);

        try {
            $service->createSupplyRequest(
                $restaurant->id,
                $branch2->id,
                $manager2,
                [['ingredient_id' => $ingredient->id, 'quantity' => 50]]
            );
            $this->fail('Yêu cầu vượt quá tồn khả dụng phải bị từ chối.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('khả dụng', $e->getMessage());
        }

        // 4. Layer 1: Soạn hàng (warehouse staff)
        $preparedRequest = $service->prepareDispatch($approvedRequest, $warehouseStaff, [
            [
                'id' => $approvedRequest->items->first()->id,
                'actual_dispatched_quantity' => 60,
            ],
        ]);
        $this->assertEquals(SupplyRequest::STATUS_PREPARING, $preparedRequest->status);

        // 5. Layer 2: Trưởng kho duyệt xuất
        $approvedDispatchRequest = $service->approveDispatch($preparedRequest, $warehouseManager);
        $this->assertEquals(SupplyRequest::STATUS_DISPATCH_PENDING, $approvedDispatchRequest->status);

        // 6. Layer 3: Bàn giao xuất kho -> Deducts central stock and releases reservation
        $dispatchedRequest = $service->dispatchSupplyRequest($approvedDispatchRequest, $warehouseStaff, 'SEAL-DEMO-999');
        $this->assertEquals(SupplyRequest::STATUS_DISPATCHED, $dispatchedRequest->status);

        $inventory->refresh();
        $this->assertEquals(40, $inventory->quantity_on_hand);

        // Check reservation is released
        $reservation = InventoryReservation::where('supply_request_id', $supplyRequest->id)->first();
        $this->assertNotNull($reservation->released_at);

        // 7. Receive with shortage (Branch receives only 55kg out of 60kg) -> Status becomes 'disputed'
        $receivedRequest = $service->receiveSupplyRequest(
            $dispatchedRequest,
            $manager,
            [
                ['id' => $supplyRequest->items->first()->id, 'received_quantity' => 55],
            ],
            '/photos/receipt.jpg',
            '/signatures/sig.png',
            'Nhận thiếu 5kg do vỡ bao bì'
        );

        $this->assertEquals(SupplyRequest::STATUS_DISPUTED, $receivedRequest->status);
        $this->assertTrue($receivedRequest->discrepancy_flag);

        // Branch inventory increased by 55
        $branchInventory = Inventory::where('branch_id', $branch1->id)->where('ingredient_id', $ingredient->id)->first();
        $this->assertEquals(55, $branchInventory->quantity_on_hand);

        // Check Governance Dispute created automatically
        $this->assertDatabaseHas('inventory_discrepancy_disputes', [
            'supply_request_id' => $supplyRequest->id,
            'discrepancy_quantity' => 5,
        ]);
    }

    public function test_prevent_self_approval_middleware_blocks_self_approval()
    {
        $restaurant = Restaurant::factory()->create();
        $centralBranch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'is_central_warehouse' => true]);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $manager->givePermissionTo('supply_requests.approve');

        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create(['restaurant_id' => $restaurant->id, 'unit_id' => $unit->id, 'name' => 'Gà', 'sku' => 'CHICKEN']);
        Inventory::create(['restaurant_id' => $restaurant->id, 'branch_id' => $centralBranch->id, 'ingredient_id' => $ingredient->id, 'quantity_on_hand' => 50]);

        $supplyRequest = app(CentralWarehouseService::class)->createSupplyRequest(
            $restaurant->id,
            $branch->id,
            $manager,
            [['ingredient_id' => $ingredient->id, 'quantity' => 10]]
        );

        // Manager tries to self-approve request created by self -> 403 Forbidden
        $response = $this->actingAs($manager)->postJson("/api/supply-requests/{$supplyRequest->id}/approve");
        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'self_approval_prevented']);
    }
}
