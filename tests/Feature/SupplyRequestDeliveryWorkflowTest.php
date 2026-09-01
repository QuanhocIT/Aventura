<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupplyRequestDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transporter_must_confirm_delivery_before_branch_receives_goods(): void
    {
        Notification::fake();

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
        $unit = Unit::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Thịt bò',
            'sku' => 'BEEF-DELIVERY-01',
            'average_cost' => 100000,
        ]);
        Inventory::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'ingredient_id' => $ingredient->id,
            'quantity_on_hand' => 20,
            'theoretical_quantity' => 20,
        ]);

        $requester = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $requester->assignRole('manager');

        $receiver = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $receiver->assignRole('manager');

        $warehouseManager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'warehouse_branch_id' => $central->id,
        ]);
        $warehouseManager->assignRole('warehouse_manager');

        $warehouseStaff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'warehouse_branch_id' => $central->id,
            'warehouse_staff_status' => 'active',
        ]);
        $warehouseStaff->assignRole('warehouse_staff');

        $transporter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'warehouse_branch_id' => $central->id,
            'warehouse_staff_status' => 'active',
        ]);
        $transporter->assignRole('warehouse_staff');

        $service = app(CentralWarehouseService::class);
        $supplyRequest = $service->createSupplyRequest(
            $restaurant->id,
            $branch->id,
            $requester,
            [['ingredient_id' => $ingredient->id, 'quantity' => 10]],
        );
        $approved = $service->approveSupplyRequest($supplyRequest, $warehouseManager);
        $prepared = $service->prepareDispatch($approved, $warehouseStaff, [[
            'id' => $approved->items->first()->id,
            'actual_dispatched_quantity' => 10,
        ]]);
        $dispatchApproved = $service->approveDispatch($prepared, $warehouseManager);

        WarehouseTaskAssignment::create([
            'restaurant_id' => $restaurant->id,
            'supply_request_id' => $dispatchApproved->id,
            'assigned_to' => $warehouseStaff->id,
            'assigned_by' => $warehouseManager->id,
            'task_type' => 'handover',
            'status' => 'assigned',
            'priority' => 'high',
        ]);

        $this->actingAs($warehouseStaff)
            ->postJson(route('supply-requests.dispatch', ['id' => $dispatchApproved->id]), [
                'transporter_id' => $transporter->id,
                'seal_code' => 'SEAL-DELIVERY-01',
            ])
            ->assertOk();

        $supplyRequest->refresh();
        $deliveryTask = WarehouseTaskAssignment::where('supply_request_id', $supplyRequest->id)
            ->where('task_type', 'delivery')
            ->firstOrFail();

        $this->assertSame($transporter->id, $supplyRequest->transporter_id);
        $this->assertSame('assigned', $deliveryTask->status);
        $this->assertSame($transporter->id, $deliveryTask->assigned_to);

        $this->actingAs($receiver)
            ->postJson(route('supply-requests.receive', ['id' => $supplyRequest->id]), [
                'items' => [[
                    'id' => $supplyRequest->items->first()->id,
                    'received_quantity' => 10,
                    'received_good_quantity' => 10,
                ]],
            ])
            ->assertStatus(422);

        $this->actingAs($transporter)
            ->postJson(route('warehouse.tasks.start', ['id' => $deliveryTask->id]))
            ->assertOk();

        $this->actingAs($transporter)
            ->postJson(route('warehouse.tasks.complete', ['id' => $deliveryTask->id]), [
                'result_notes' => 'Đã giao đủ kiện hàng tới chi nhánh.',
            ])
            ->assertOk();

        $supplyRequest->refresh();
        $this->assertNotNull($supplyRequest->delivery_confirmed_at);
        $this->assertSame($transporter->id, $supplyRequest->delivery_confirmed_by);

        $this->actingAs($receiver)
            ->postJson(route('supply-requests.receive', ['id' => $supplyRequest->id]), [
                'items' => [[
                    'id' => $supplyRequest->items->first()->id,
                    'received_quantity' => 10,
                    'received_good_quantity' => 10,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('central_supply_requests', [
            'id' => $supplyRequest->id,
            'status' => SupplyRequest::STATUS_COMPLETED,
            'transporter_id' => $transporter->id,
            'delivery_confirmed_by' => $transporter->id,
        ]);
    }
}
