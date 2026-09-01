<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryQuarantine;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestReceivingReport;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use App\Notifications\SupplyRequestReceivingReportNotification;
use App\Services\CentralWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupplyRequestReceivingReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_receiving_goes_through_report_before_stock_and_is_sent_to_stakeholders(): void
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
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);
        $ingredient = Ingredient::create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'name' => 'Gạo',
            'sku' => 'RICE-REPORT-01',
            'average_cost' => 35000,
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

        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
        ]);
        $owner->assignRole('owner');

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
                'seal_code' => 'SEAL-REPORT-01',
            ])
            ->assertOk();

        $deliveryTask = WarehouseTaskAssignment::where('supply_request_id', $supplyRequest->id)
            ->where('task_type', 'delivery')
            ->firstOrFail();
        $this->actingAs($transporter)
            ->postJson(route('warehouse.tasks.start', ['id' => $deliveryTask->id]))
            ->assertOk();
        $this->actingAs($transporter)
            ->postJson(route('warehouse.tasks.complete', ['id' => $deliveryTask->id]))
            ->assertOk();

        $itemId = $supplyRequest->items->first()->id;
        $receiveResponse = $this->actingAs($receiver)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('supply-requests.receive', ['id' => $supplyRequest->id]), [
                'items' => [[
                    'id' => $itemId,
                    'received_quantity' => 10,
                    'received_good_quantity' => 7,
                    'received_damaged_quantity' => 3,
                    'received_expired_quantity' => 0,
                    'received_wrong_item_quantity' => 0,
                    'received_missing_quantity' => 0,
                'received_condition' => 'damaged',
                'received_note' => 'Ba bao gạo bị rách khi mở niêm phong.',
            ]],
            'received_temperature_min_c' => 2,
            'received_temperature_max_c' => 5,
            'receipt_photo' => UploadedFile::fake()->image('receipt.jpg'),
            'receiver_signature' => UploadedFile::fake()->image('signature.png'),
        ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('requires_receiving_report', true);

        $supplyRequest->refresh();
        $report = SupplyRequestReceivingReport::with('items')->where('supply_request_id', $supplyRequest->id)->firstOrFail();
        $this->assertSame(SupplyRequest::STATUS_RECEIVING_REVIEW, $supplyRequest->status);
        $this->assertSame(SupplyRequestReceivingReport::STATUS_PENDING_BRANCH_CONFIRMATION, $report->status);
        $this->assertSame($transporter->id, $report->transporter_id);
        $this->assertSame($transporter->name, $report->transporter_name_snapshot);
        $this->assertSame('7.000', $report->items->first()->submitted_good_quantity);
        $this->assertSame('3.000', $report->items->first()->submitted_damaged_quantity);
        $this->assertSame(0, Inventory::where('restaurant_id', $restaurant->id)->where('branch_id', $branch->id)->count());
        $this->assertSame(0, InventoryQuarantine::where('restaurant_id', $restaurant->id)->count());

        $this->actingAs($receiver)
            ->postJson(route('supply-requests.receiving-report.confirm', ['id' => $supplyRequest->id]))
            ->assertOk()
            ->assertJsonPath('data.status', SupplyRequestReceivingReport::STATUS_CONFIRMED_PENDING_ACK);

        $branchInventory = Inventory::where('restaurant_id', $restaurant->id)
            ->where('branch_id', $branch->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail();
        $this->assertSame(7.0, (float) $branchInventory->quantity_on_hand);
        $this->assertDatabaseHas('inventory_quarantines', [
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 3,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('supply_request_receiving_reports', [
            'id' => $report->id,
            'status' => SupplyRequestReceivingReport::STATUS_CONFIRMED_PENDING_ACK,
            'confirmed_by' => $receiver->id,
        ]);
        $this->assertDatabaseHas('central_supply_request_items', [
            'id' => $itemId,
            'received_temperature_min_c' => 2,
            'received_temperature_max_c' => 5,
        ]);
        Notification::assertSentTo($transporter, SupplyRequestReceivingReportNotification::class);
        Notification::assertSentTo($warehouseManager, SupplyRequestReceivingReportNotification::class);
        Notification::assertSentTo($owner, SupplyRequestReceivingReportNotification::class);

        $this->actingAs($transporter)
            ->postJson(route('supply-requests.receiving-report.driver-confirm', ['id' => $report->id]), [
                'notes' => 'Đã kiểm tra và xác nhận nội dung biên bản.',
            ])
            ->assertOk();

        $this->actingAs($warehouseManager)
            ->postJson(route('supply-requests.receiving-report.review', ['id' => $report->id]), [
                'notes' => 'Đã ghi nhận hàng hỏng, giữ cách ly để xử lý hoàn trả hoặc tiêu hủy theo quy định.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('supply_request_receiving_reports', [
            'id' => $report->id,
            'status' => SupplyRequestReceivingReport::STATUS_RESOLVED,
            'driver_confirmed_by' => $transporter->id,
            'reviewed_by' => $warehouseManager->id,
        ]);
        $this->assertDatabaseHas('central_supply_requests', [
            'id' => $supplyRequest->id,
            'status' => SupplyRequest::STATUS_DISPUTED,
        ]);
    }
}
