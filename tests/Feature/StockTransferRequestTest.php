<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Điều chuyển liên chi nhánh: yêu cầu → Chủ định tuyến (mã giao nhận) → chi nhánh thừa
 * xuất → chi nhánh thiếu nhận bằng mã. Người xuất ≠ người nhận; mã phải khớp; tồn kho
 * chuyển đúng.
 */
class StockTransferRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $managerA;   // chi nhánh THIẾU

    private User $managerB;   // chi nhánh THỪA

    private User $warehouseManager;

    private Restaurant $restaurant;

    private RestaurantBranch $branchA;

    private RestaurantBranch $branchB;

    private Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->branchA = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->branchB = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);

        $this->managerA = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'status' => 'active']);
        $this->managerA->assignRole('manager');
        $this->managerB = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'status' => 'active']);
        $this->managerB->assignRole('manager');
        $this->warehouseManager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->warehouseManager->assignRole('warehouse_manager');

        $unit = Unit::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Kg', 'symbol' => 'kg', 'type' => 'weight']);
        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => null,
            'unit_id' => $unit->id, 'name' => 'Bột mì', 'sku' => 'BM-01',
            'average_cost' => 20000, 'status' => 'active',
        ]);
        // Chi nhánh THỪA có 100kg.
        Inventory::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id,
            'ingredient_id' => $this->ingredient->id, 'quantity_on_hand' => 100,
            'theoretical_quantity' => 100, 'last_cost' => 20000,
        ]);
    }

    private function makeRequest(): StockTransferRequest
    {
        $this->actingAs($this->managerA)->post('/inventory/transfers', [
            'to_branch_id' => $this->branchA->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_requested' => 30,
            'reason' => 'Chi nhánh A hết bột mì đột xuất',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return StockTransferRequest::latest('id')->firstOrFail();
    }

    public function test_full_transfer_flow(): void
    {
        Notification::fake();

        // 1. Yêu cầu → báo Chủ.
        $t = $this->makeRequest();
        $this->assertEquals('requested', $t->status);
        $this->assertSame('urgent', $t->priority);
        $this->assertDatabaseCount('inventory_transactions', 0);
        Notification::assertSentTo($this->owner, StockTransferStageNotification::class);

        // 2. Chủ định tuyến chi nhánh THỪA → sinh mã.
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", [
            'from_branch_id' => $this->branchB->id,
            'owner_note' => 'Lấy từ chi nhánh B',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertEquals('routed', $t->status);
        $this->assertEquals($this->branchB->id, $t->from_branch_id);
        $this->assertNotEmpty($t->handover_code);

        // 3. Chi nhánh THỪA xuất → trừ kho B.
        $this->actingAs($this->warehouseManager)->post("/inventory/transfers/{$t->id}/dispatch", [
            'quantity_dispatched' => 30,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertEquals('dispatched', $t->status);
        $this->assertEquals($this->warehouseManager->id, $t->dispatched_by);
        $invB = Inventory::where('branch_id', $this->branchB->id)->where('ingredient_id', $this->ingredient->id)->first();
        $this->assertEqualsWithDelta(70, (float) $invB->quantity_on_hand, 0.001);

        // 4. Chi nhánh THIẾU nhận bằng mã → cộng kho A.
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/receive", [
            'handover_code' => $t->handover_code,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertEquals('received', $t->status);
        $invA = Inventory::where('branch_id', $this->branchA->id)->where('ingredient_id', $this->ingredient->id)->first();
        $this->assertEqualsWithDelta(30, (float) $invA->quantity_on_hand, 0.001);
    }

    public function test_dispatch_consumes_multiple_fefo_batches(): void
    {
        InventoryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'LOT-EARLY',
            'quantity_remaining' => 40,
            'unit_cost' => 18000,
            'purchased_at' => now()->subDays(2),
            'expiry_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
        InventoryBatch::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'ingredient_id' => $this->ingredient->id,
            'batch_number' => 'LOT-LATE',
            'quantity_remaining' => 60,
            'unit_cost' => 22000,
            'purchased_at' => now()->subDay(),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->managerA)->post('/inventory/transfers', [
            'to_branch_id' => $this->branchA->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_requested' => 80,
            'reason' => 'Kiá»ƒm tra xuáº¥t nhiá»u lÃ´',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transfer = StockTransferRequest::latest('id')->firstOrFail();

        $this->actingAs($this->owner)->post("/inventory/transfers/{$transfer->id}/route", [
            'from_branch_id' => $this->branchB->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->warehouseManager)->post("/inventory/transfers/{$transfer->id}/dispatch", [
            'quantity_dispatched' => 80,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $out = InventoryTransaction::where('source_type', 'stock_transfer')
            ->where('source_id', $transfer->id)->where('direction', 'out')->firstOrFail();
        $allocations = InventoryBatchAllocation::where('inventory_transaction_id', $out->id)->orderBy('id')->get();
        $this->assertCount(2, $allocations);
        $this->assertEqualsWithDelta(40, (float) $allocations[0]->quantity, 0.001);
        $this->assertEqualsWithDelta(40, (float) $allocations[1]->quantity, 0.001);
    }

    public function test_receive_requires_correct_code(): void
    {
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", ['from_branch_id' => $this->branchB->id])->assertRedirect();
        $this->actingAs($this->warehouseManager)->post("/inventory/transfers/{$t->id}/dispatch", ['quantity_dispatched' => 30])->assertRedirect();

        $this->actingAs($this->owner)->from('/inventory/transfers')
            ->post("/inventory/transfers/{$t->id}/receive", ['handover_code' => 'WRONG1'])
            ->assertSessionHasErrors(['handover_code']);
        $this->assertEquals('dispatched', $t->refresh()->status);
    }

    public function test_dispatcher_cannot_also_receive(): void
    {
        // Chủ vừa xuất vừa cố nhận → chặn (người nhận phải khác người xuất).
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", ['from_branch_id' => $this->branchB->id])->assertRedirect();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/dispatch", ['quantity_dispatched' => 30])->assertRedirect();

        $t->refresh();
        $this->actingAs($this->owner)->from('/inventory/transfers')
            ->post("/inventory/transfers/{$t->id}/receive", ['handover_code' => $t->handover_code])
            ->assertSessionHasErrors(['handover_code']);
        $this->assertEquals('dispatched', $t->refresh()->status);
    }

    public function test_route_rejects_same_branch(): void
    {
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->from('/inventory/transfers')
            ->post("/inventory/transfers/{$t->id}/route", ['from_branch_id' => $this->branchA->id])
            ->assertSessionHasErrors(['from_branch_id']);
    }

    public function test_route_rejects_source_without_enough_inventory(): void
    {
        $this->actingAs($this->managerA)->post('/inventory/transfers', [
            'to_branch_id' => $this->branchA->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_requested' => 101,
            'reason' => 'Cần bổ sung vượt quá tồn hiện tại.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transfer = StockTransferRequest::latest('id')->firstOrFail();

        $this->actingAs($this->owner)
            ->from('/inventory/transfers')
            ->post("/inventory/transfers/{$transfer->id}/route", ['from_branch_id' => $this->branchB->id])
            ->assertSessionHasErrors(['from_branch_id']);

        $this->assertEquals('requested', $transfer->refresh()->status);
    }

    public function test_manager_cannot_route(): void
    {
        $t = $this->makeRequest();
        $this->actingAs($this->managerB)->post("/inventory/transfers/{$t->id}/route", [
            'from_branch_id' => $this->branchB->id,
        ])->assertForbidden();
    }

    public function test_manager_can_dispatch_for_own_source_branch_and_receive_for_own_destination_branch(): void
    {
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", [
            'from_branch_id' => $this->branchB->id,
        ])->assertRedirect();

        // Manager A is NOT from branch B -> forbidden
        $this->actingAs($this->managerA)
            ->post("/inventory/transfers/{$t->id}/dispatch", ['quantity_dispatched' => 30])
            ->assertForbidden();

        // Manager B IS from branch B -> success
        $this->actingAs($this->managerB)
            ->post("/inventory/transfers/{$t->id}/dispatch", ['quantity_dispatched' => 30])
            ->assertRedirect();

        $t->refresh();

        // Manager B is NOT to branch A -> forbidden
        $this->actingAs($this->managerB)
            ->post("/inventory/transfers/{$t->id}/receive", [
                'handover_code' => $t->handover_code,
            ])
            ->assertForbidden();

        // Manager A IS to branch A -> success
        $this->actingAs($this->managerA)
            ->post("/inventory/transfers/{$t->id}/receive", [
                'handover_code' => $t->handover_code,
            ])
            ->assertRedirect();

        $this->assertEquals('received', $t->refresh()->status);
        $this->assertEquals($this->managerB->id, $t->dispatched_by);
        $this->assertEquals($this->managerA->id, $t->received_by);
    }

    public function test_manager_accesses_transfers_workspace_with_branch_scoping(): void
    {
        $transfer = $this->makeRequest();

        $this->actingAs($this->managerA)
            ->get('/inventory/transfers')
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/Transfers')
                ->where('permissions.can_route', false)
                ->where('permissions.can_execute', true)
                ->has('transfers', 1)
                ->where('transfers.0.id', $transfer->id)
            );
    }

    public function test_manager_request_is_locked_to_assigned_branch_on_server(): void
    {
        $this->actingAs($this->managerA)
            ->from('/inventory/transfers')
            ->post('/inventory/transfers', [
                'to_branch_id' => $this->branchB->id,
                'ingredient_id' => $this->ingredient->id,
                'quantity_requested' => 10,
                'reason' => 'Cần bổ sung cho chi nhánh khác.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('stock_transfer_requests', 0);
    }

    public function test_partial_dispatch_is_rejected_to_prevent_false_completion(): void
    {
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", [
            'from_branch_id' => $this->branchB->id,
        ])->assertRedirect();

        $this->actingAs($this->warehouseManager)
            ->post("/inventory/transfers/{$t->id}/dispatch", ['quantity_dispatched' => 20])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals('routed', $t->refresh()->status);
        $this->assertEqualsWithDelta(100, (float) Inventory::where('branch_id', $this->branchB->id)->where('ingredient_id', $this->ingredient->id)->value('quantity_on_hand'), 0.001);
    }

    public function test_short_receipt_creates_discrepancy_and_requires_resolution(): void
    {
        Storage::fake('public');
        $t = $this->makeRequest();
        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/route", [
            'from_branch_id' => $this->branchB->id,
        ])->assertRedirect();
        $this->actingAs($this->warehouseManager)->post("/inventory/transfers/{$t->id}/dispatch", [
            'quantity_dispatched' => 30,
        ])->assertRedirect();

        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/receive", [
            'handover_code' => $t->refresh()->handover_code,
            'quantity_received' => 28,
            'received_condition' => 'shortage',
            'received_note' => 'Thiếu 2kg khi kiểm đếm tại cửa nhận.',
            'receiving_evidence' => UploadedFile::fake()->create('bien-ban-thieu.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertEquals('discrepancy', $t->status);
        $this->assertEqualsWithDelta(2, (float) $t->discrepancy_quantity, 0.001);
        $this->assertEqualsWithDelta(28, (float) Inventory::where('branch_id', $this->branchA->id)->where('ingredient_id', $this->ingredient->id)->value('quantity_on_hand'), 0.001);

        $this->actingAs($this->owner)->post("/inventory/transfers/{$t->id}/resolve-discrepancy", [
            'discrepancy_resolution' => 'Đã xác nhận thiếu trong quá trình bàn giao và ghi nhận biên bản hao hụt.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('received', $t->refresh()->status);
    }

    public function test_requester_can_cancel_before_dispatch(): void
    {
        $t = $this->makeRequest();

        $this->actingAs($this->managerA)->post("/inventory/transfers/{$t->id}/cancel", [
            'cancel_reason' => 'Chi nhánh đã tự cân đối được tồn trong ngày.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('cancelled', $t->refresh()->status);
        $this->assertEquals($this->managerA->id, $t->cancelled_by);
    }

    public function test_batch_route_and_batch_reject_grouped_requests(): void
    {
        $unit = Unit::firstOrCreate(['restaurant_id' => $this->restaurant->id, 'name' => 'Gói', 'symbol' => 'goi', 'type' => 'weight']);
        $ingredient2 = Ingredient::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => null,
            'unit_id' => $unit->id, 'name' => 'Bia Tiger', 'sku' => 'BT-01',
            'average_cost' => 15000, 'status' => 'active',
        ]);
        Inventory::create([
            'restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id,
            'ingredient_id' => $ingredient2->id, 'quantity_on_hand' => 50,
            'theoretical_quantity' => 50, 'last_cost' => 15000,
        ]);

        // Manager A creates a grouped request with 2 items
        $this->actingAs($this->managerA)->post('/inventory/transfers', [
            'to_branch_id' => $this->branchA->id,
            'items' => [
                ['ingredient_id' => $this->ingredient->id, 'quantity_requested' => 10],
                ['ingredient_id' => $ingredient2->id, 'quantity_requested' => 5],
            ],
            'priority' => 'urgent',
            'reason' => 'Đơn xin bổ sung đợt cuối tuần',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $groupId = StockTransferRequest::latest('id')->value('request_group_id');
        $this->assertNotNull($groupId);
        $this->assertEquals(2, StockTransferRequest::where('request_group_id', $groupId)->count());

        // Owner batch routes the entire group
        $this->actingAs($this->owner)->post('/inventory/transfers/batch-route', [
            'request_group_id' => $groupId,
            'from_branch_id' => $this->branchB->id,
            'owner_note' => 'Xuất từ kho chi nhánh B',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transfers = StockTransferRequest::where('request_group_id', $groupId)->get();
        $this->assertEquals('routed', $transfers[0]->status);
        $this->assertEquals('routed', $transfers[1]->status);
        $this->assertEquals($transfers[0]->handover_code, $transfers[1]->handover_code);
        $this->assertEquals($this->branchB->id, $transfers[0]->from_branch_id);
    }
}
