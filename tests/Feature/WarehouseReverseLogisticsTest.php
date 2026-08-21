<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryQuarantine;
use App\Models\InventoryReturn;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseReverseLogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_damaged_transfer_only_posts_good_quantity_and_can_be_returned(): void
    {
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $from = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $to = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $fromManager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $from->id]);
        $fromManager->assignRole('manager');
        $toManager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $to->id]);
        $toManager->assignRole('manager');
        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'Kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create(['restaurant_id' => $restaurant->id, 'unit_id' => $unit->id, 'name' => 'Thịt kiểm soát', 'sku' => 'REV-01', 'average_cost' => 100, 'status' => 'active']);
        Inventory::create(['restaurant_id' => $restaurant->id, 'branch_id' => $from->id, 'ingredient_id' => $ingredient->id, 'quantity_on_hand' => 30, 'theoretical_quantity' => 30, 'last_cost' => 100]);

        $this->actingAs($toManager)->post('/inventory/transfers', [
            'to_branch_id' => $to->id,
            'ingredient_id' => $ingredient->id,
            'quantity_requested' => 30,
            'reason' => 'Bổ sung nguyên liệu',
        ])->assertRedirect();
        $transfer = StockTransferRequest::latest('id')->firstOrFail();
        $this->actingAs($owner)->post("/inventory/transfers/{$transfer->id}/route", ['from_branch_id' => $from->id])->assertRedirect();
        $this->actingAs($fromManager)->post("/inventory/transfers/{$transfer->id}/dispatch", ['quantity_dispatched' => 30])->assertRedirect();

        $this->actingAs($toManager)->post("/inventory/transfers/{$transfer->id}/receive", [
            'handover_code' => $transfer->refresh()->handover_code,
            'quantity_received_good' => 20,
            'quantity_received_damaged' => 10,
            'received_condition' => 'damaged',
            'received_note' => 'Mười kg bị dập khi xe đến chi nhánh.',
            'receiving_evidence' => UploadedFile::fake()->image('damage.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame('quarantined', $transfer->status);
        $this->assertEqualsWithDelta(20, (float) Inventory::where('branch_id', $to->id)->where('ingredient_id', $ingredient->id)->value('quantity_on_hand'), 0.001);
        $quarantine = InventoryQuarantine::where('source_type', 'stock_transfer')->where('source_id', $transfer->id)->firstOrFail();
        $this->assertEqualsWithDelta(10, (float) $quarantine->quantity, 0.001);

        $returnResponse = $this->actingAs($toManager)->postJson(route('warehouse.reverse-logistics.quarantines.return', $quarantine->id), [
            'reason' => 'Hoàn trả hàng hỏng cho Kho Tổng.',
        ]);
        $returnResponse->assertCreated();
        $return = InventoryReturn::latest('id')->firstOrFail();

        $this->actingAs($owner)->postJson(route('warehouse.reverse-logistics.returns.approve', $return->id))->assertOk();
        $this->actingAs($owner)->postJson(route('warehouse.reverse-logistics.returns.complete', $return->id), [
            'disposition' => 'supplier_confirmed',
            'notes' => 'Đã bàn giao cho nhà cung cấp.',
        ])->assertOk();

        $this->assertSame('received', $return->refresh()->status);
        $this->assertSame('returned', $quarantine->refresh()->status);
        $this->assertSame('returned', $transfer->refresh()->status);
    }

    public function test_central_supply_damage_is_quarantined_and_only_good_quantity_enters_branch_stock(): void
    {
        Storage::fake('local');
        foreach (['owner', 'warehouse_manager', 'warehouse_staff', 'manager'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'is_central_warehouse' => true, 'warehouse_type' => 'central', 'status' => 'active']);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id, 'status' => 'active']);
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $warehouseManager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $central->id, 'warehouse_branch_id' => $central->id]);
        $warehouseManager->assignRole('warehouse_manager');
        $warehouseStaff = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $central->id, 'warehouse_branch_id' => $central->id]);
        $warehouseStaff->assignRole('warehouse_staff');
        $branchManager = User::factory()->create(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id]);
        $branchManager->assignRole('manager');
        $unit = Unit::create(['restaurant_id' => $restaurant->id, 'name' => 'Kg', 'symbol' => 'kg', 'type' => 'mass']);
        $ingredient = Ingredient::create(['restaurant_id' => $restaurant->id, 'unit_id' => $unit->id, 'name' => 'Nguyên liệu cấp phát', 'sku' => 'SUP-REV-01', 'average_cost' => 100, 'status' => 'active']);
        Inventory::create(['restaurant_id' => $restaurant->id, 'branch_id' => $central->id, 'ingredient_id' => $ingredient->id, 'quantity_on_hand' => 20, 'theoretical_quantity' => 20, 'last_cost' => 100]);

        $service = app(\App\Services\CentralWarehouseService::class);
        $request = $service->createSupplyRequest($restaurant->id, $branch->id, $branchManager, [['ingredient_id' => $ingredient->id, 'quantity' => 20]]);
        $approved = $service->approveSupplyRequest($request, $warehouseManager);
        $prepared = $service->prepareDispatch($approved, $warehouseStaff, [['id' => $approved->items->first()->id, 'actual_dispatched_quantity' => 20]]);
        $dispatchApproved = $service->approveDispatch($prepared, $warehouseManager);
        $dispatched = $service->dispatchSupplyRequest($dispatchApproved, $warehouseStaff, 'SUP-REV-SEAL');

        $response = $this->actingAs($branchManager)->postJson(route('supply-requests.receive', ['id' => $dispatched->id]), [
            'items' => [[
                'id' => $dispatched->items->first()->id,
                'received_quantity' => 20,
                'received_good_quantity' => 12,
                'received_damaged_quantity' => 8,
                'received_condition' => 'damaged',
                'received_note' => 'Tám kg bị hỏng trong quá trình vận chuyển.',
            ]],
            'receipt_photo' => UploadedFile::fake()->image('supply-damage.jpg'),
            'receiver_signature' => UploadedFile::fake()->image('supply-signature.png'),
            'notes' => 'Đã lập biên bản nhận hàng.',
        ]);

        $response->assertOk();
        $this->assertSame('disputed', $dispatched->refresh()->status);
        $this->assertEqualsWithDelta(12, (float) Inventory::where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->value('quantity_on_hand'), 0.001);
        $this->assertDatabaseHas('inventory_quarantines', ['source_type' => 'supply_request', 'source_id' => $dispatched->id, 'quantity' => 8]);
        $this->assertDatabaseHas('inventory_batches', ['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'status' => 'locked', 'quantity_remaining' => 8]);
    }
}
