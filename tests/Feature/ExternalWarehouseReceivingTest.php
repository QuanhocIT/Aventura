<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryBatch;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingReport;
use App\Models\WarehouseReceivingVoucher;
use App\Notifications\ExternalReceiptVerifiedNotification;
use App\Notifications\ExternalReceiptVerificationAssignedNotification;
use App\Notifications\WarehouseReceivingReportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalWarehouseReceivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_receipt_is_stored_without_supplier_or_purchase_order_and_posts_as_external_receipt(): void
    {
        [$restaurant, $central, $ingredient, $location] = $this->fixture();
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $restaurant->update(['owner_user_id' => $owner->id]);
        Notification::fake();

        $response = $this->actingAs($manager)->postJson(route('warehouse.receiving-vouchers.store'), [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_receipt_reason' => 'external_donation',
            'external_source_name' => 'Đơn vị hỗ trợ cộng đồng',
            'external_reference' => 'BB-EXT-001',
            'verification_assigned_to' => $staff->id,
            // These legacy fields must never be copied to a new external receipt.
            'supplier_id' => 999999,
            'purchase_order_id' => 999999,
            'submit_for_review' => true,
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'unit_label' => 'kg',
                'actual_qty' => 5.5,
                'unit_cost' => 120000,
                'lot_number' => 'EXT-LOT-001',
                'expiry_date' => now()->addDays(14)->toDateString(),
                'location_id' => $location->id,
            ]],
        ]);

        $response->assertCreated();
        $voucher = WarehouseReceivingVoucher::latest('id')->firstOrFail();
        $this->assertSame('pending_review', $voucher->status);
        $this->assertSame($staff->id, (int) $voucher->verification_assigned_to);
        $this->assertSame('external_donation', $voucher->external_receipt_reason);
        $this->assertSame('Đơn vị hỗ trợ cộng đồng', $voucher->external_source_name);
        $this->assertSame('kg', $voucher->items->firstOrFail()->unit_label);
        $this->assertSame(660000.0, (float) $voucher->invoice_total_amount);
        $this->assertStringStartsWith('NXT-', $voucher->voucher_code);
        $this->assertNull($voucher->supplier_id);
        $this->assertNull($voucher->purchase_order_id);
        $this->assertSame(0.0, (float) $voucher->total_discrepancy_qty);
        Notification::assertSentTo($staff, ExternalReceiptVerificationAssignedNotification::class);

        $this->actingAs($staff)
            ->get(route('inventory.staff-portal'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('assignedVerificationVouchers.0.id', $voucher->id));

        $this->assertDatabaseCount('inventory_transactions', 0);

        $this->actingAs($manager)
            ->postJson(route('warehouse.receiving-vouchers.confirm', $voucher->id), [
                'notes' => 'Kiểm kê thực tế thiếu 0.25 kg so với số khai báo ban đầu.',
                'quality_status' => 'passed',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->postJson(route('warehouse.receiving-vouchers.confirm', $voucher->id), [
                'notes' => 'recount differs from the manager declaration by 0.25 kg.',
                'quality_status' => 'passed',
                'verification_items' => [[
                    'voucher_item_id' => $voucher->items->firstOrFail()->id,
                    'actual_qty' => 5.25,
                ]],
            ])
            ->assertOk();

        $transaction = InventoryTransaction::where('source_type', 'warehouse_receiving_voucher')
            ->where('source_id', $voucher->id)
            ->firstOrFail();
        $this->assertSame('external_receipt', $transaction->type);
        $this->assertNull($transaction->supplier_id);

        $batch = InventoryBatch::where('branch_id', $central->id)
            ->where('ingredient_id', $ingredient->id)
            ->firstOrFail();
        $this->assertNull($batch->supplier_id);
        $this->assertSame(5.25, (float) $batch->quantity_remaining);
        $this->assertSame(5.25, (float) $voucher->refresh()->total_actual_qty);
        $this->assertNotNull($voucher->owner_notified_at);
        Notification::assertSentTo($owner, ExternalReceiptVerifiedNotification::class);
        Notification::assertSentTo($owner, WarehouseReceivingReportNotification::class);
        Notification::assertSentTo($manager, WarehouseReceivingReportNotification::class);

        $report = WarehouseReceivingReport::where('voucher_id', $voucher->id)
            ->with('items')
            ->firstOrFail();
        $this->assertSame('employee_confirmed', $report->status);
        $this->assertSame('quantity_mismatch', $report->issue_type);
        $this->assertSame($staff->id, (int) $report->employee_confirmed_by);
        $this->assertSame(5.25, (float) $report->items->firstOrFail()->actual_quantity);
        $this->assertSame(-0.25, (float) $report->items->firstOrFail()->difference_quantity);

        $this->actingAs($owner)
            ->get(route('inventory.staff-portal', ['tab' => 'incident', 'report_id' => $report->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('warehouseReceivingReports.0.id', $report->id));

        $this->actingAs($manager)
            ->get(route('inventory.staff-portal', ['tab' => 'incident', 'report_id' => $report->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('warehouseReceivingReports.0.id', $report->id));
    }

    public function test_external_receipt_requires_a_source_name(): void
    {
        [$restaurant, $central, $ingredient, $location] = $this->fixture();
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        $this->actingAs($manager)
            ->postJson(route('warehouse.receiving-vouchers.store'), [
                'received_at' => now()->format('Y-m-d H:i:s'),
                'external_receipt_reason' => 'other',
                'items' => [[
                    'ingredient_id' => $ingredient->id,
                    'actual_qty' => 1,
                    'lot_number' => 'EXT-LOT-002',
                    'location_id' => $location->id,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Phiếu nhập ngoài phải có lý do nhập và bên giao/nguồn bên ngoài.');

        $this->assertDatabaseCount('warehouse_receiving_vouchers', 0);
    }

    public function test_external_receipt_quality_issue_creates_report_before_disposition(): void
    {
        [$restaurant, $central, $ingredient, $location] = $this->fixture();
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $restaurant->update(['owner_user_id' => $owner->id]);
        Notification::fake();

        $this->actingAs($manager)->postJson(route('warehouse.receiving-vouchers.store'), [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_receipt_reason' => 'other',
            'external_source_name' => 'Nguồn hỗ trợ',
            'verification_assigned_to' => $staff->id,
            'submit_for_review' => true,
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'unit_label' => 'kg',
                'actual_qty' => 3,
                'unit_cost' => 90000,
                'lot_number' => 'EXT-LOT-003',
                'expiry_date' => now()->addDays(14)->toDateString(),
                'location_id' => $location->id,
            ]],
        ])->assertCreated();

        $voucher = WarehouseReceivingVoucher::latest('id')->firstOrFail();

        $this->actingAs($staff)
            ->postJson(route('warehouse.receiving-vouchers.confirm', $voucher->id), [
                'quality_status' => 'failed',
                'quality_notes' => 'Bao bì rách, không đủ điều kiện nhập kho.',
                'verification_items' => [[
                    'voucher_item_id' => $voucher->items->firstOrFail()->id,
                    'actual_qty' => 3,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('requires_disposition', true)
            ->assertJsonPath('report.issue_type', 'quality_issue');

        $this->assertSame('pending_disposition', $voucher->refresh()->status);
        $this->assertSame(3.0, (float) $voucher->items->firstOrFail()->actual_qty);
        $this->assertDatabaseHas('warehouse_receiving_reports', [
            'voucher_id' => $voucher->id,
            'status' => 'employee_confirmed',
            'issue_type' => 'quality_issue',
        ]);
        $this->assertDatabaseCount('inventory_transactions', 0);
        Notification::assertSentTo($owner, WarehouseReceivingReportNotification::class);
        Notification::assertSentTo($manager, WarehouseReceivingReportNotification::class);
    }

    public function test_external_receipt_handles_duplicate_uploaded_evidence_documents_gracefully(): void
    {
        [$restaurant, $central, $ingredient, $location] = $this->fixture();
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');
        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $manager->assignRole('warehouse_manager');

        Storage::fake('local');
        $file1 = UploadedFile::fake()->image('doc.jpg');
        $file2 = UploadedFile::fake()->image('doc.jpg');

        $response = $this->actingAs($manager)->postJson(route('warehouse.receiving-vouchers.store'), [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_receipt_reason' => 'other',
            'external_source_name' => 'Nguồn test',
            'verification_assigned_to' => $staff->id,
            'submit_for_review' => true,
            'evidence' => [$file1, $file2],
            'evidence_types' => ['external_record', 'external_record'],
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'unit_label' => 'kg',
                'actual_qty' => 5,
                'unit_cost' => 10000,
                'lot_number' => 'EXT-LOT-DUP',
                'location_id' => $location->id,
            ]],
        ]);

        $response->assertCreated();
        $voucher = WarehouseReceivingVoucher::latest('id')->firstOrFail();

        $this->assertDatabaseHas('warehouse_receiving_documents', [
            'voucher_id' => $voucher->id,
            'original_name' => 'doc.jpg',
        ]);
        $this->assertDatabaseCount('warehouse_receiving_documents', 1);
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2: Ingredient, 3: WarehouseLocation} */
    private function fixture(): array
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $unit = Unit::factory()->create([
            'restaurant_id' => $restaurant->id,
            'symbol' => 'kg',
        ]);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'branch_id' => null,
            'average_cost' => 100000,
            'status' => 'active',
        ]);
        $location = WarehouseLocation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'zone' => 'A',
            'location_code' => 'A-EXT-01',
            'status' => 'active',
        ]);

        return [$restaurant, $central, $ingredient, $location];
    }
}
