<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingDocument;
use App\Models\WarehouseReceivingVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseReceivingApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_receipt_can_be_rejected_without_posting_inventory_and_keeps_documents_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$restaurant, $central, $ingredient, $location, $supplier] = $this->fixture();
        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'warehouse_branch_id' => $central->id,
            'branch_id' => $central->id,
        ]);
        $staff->assignRole('warehouse_staff');
        $manager = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $manager->assignRole('warehouse_manager');

        $response = $this->actingAs($staff)->postJson(route('warehouse.receiving-vouchers.store'), [
            'received_at' => now()->format('Y-m-d H:i:s'),
            'supplier_id' => $supplier->id,
            'submit_for_review' => true,
            'invoice_number' => 'INV-100',
            'evidence_types' => ['invoice'],
            'evidence' => [UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf')],
            'items' => [[
                'ingredient_id' => $ingredient->id,
                'expected_qty' => 10,
                'actual_qty' => 10,
                'unit_cost' => 100,
                'lot_number' => 'LOT-100',
                'expiry_date' => now()->addDays(10)->toDateString(),
                'location_id' => $location->id,
            ]],
        ]);

        $response->assertCreated();
        $voucher = WarehouseReceivingVoucher::latest('id')->firstOrFail();
        $this->assertSame('pending_review', $voucher->status);
        $document = WarehouseReceivingDocument::where('voucher_id', $voucher->id)->firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);
        Storage::disk('public')->assertMissing($document->storage_path);

        $this->actingAs($manager)
            ->postJson(route('warehouse.receiving-vouchers.reject', $voucher->id), [
                'reason' => 'Cần bổ sung biên bản đối chiếu số lượng.',
            ])
            ->assertOk();

        $this->assertSame('rejected', $voucher->refresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_po_cannot_be_received_over_the_remaining_quantity(): void
    {
        [$restaurant, $central, $ingredient, $location, $supplier] = $this->fixture();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');
        $po = PurchaseOrder::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-GRN-GUARD',
            'status' => 'approved',
            'created_by' => $owner->id,
            'total_amount' => 1000,
        ]);
        $po->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity_ordered' => 10,
            'quantity_received' => 8,
            'price_per_unit' => 100,
            'total_cost' => 1000,
        ]);
        $voucher = WarehouseReceivingVoucher::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by' => $owner->id,
            'received_at' => now(),
            'status' => 'pending_review',
            'invoice_number' => 'INV-OVER',
            'total_expected_qty' => 3,
            'total_actual_qty' => 3,
        ]);
        $voucher->items()->create([
            'ingredient_id' => $ingredient->id,
            'expected_qty' => 3,
            'actual_qty' => 3,
            'unit_cost' => 100,
            'lot_number' => 'LOT-OVER',
            'location_id' => $location->id,
        ]);

        $this->actingAs($owner)
            ->postJson(route('warehouse.receiving-vouchers.confirm', $voucher->id), [
                'quality_status' => 'passed',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Số lượng duyệt vượt phần còn lại của PO. Hãy đối chiếu các lần nhận trước khi nhập kho.');

        $this->assertSame('pending_review', $voucher->refresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2: Ingredient, 3: WarehouseLocation, 4: Supplier} */
    private function fixture(): array
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id, 'symbol' => 'kg']);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'unit_id' => $unit->id,
            'branch_id' => null,
            'name' => 'GRN test ingredient',
            'average_cost' => 100,
            'status' => 'active',
        ]);
        $location = WarehouseLocation::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $central->id,
            'zone' => 'A',
            'location_code' => 'A-01',
            'status' => 'active',
        ]);
        $supplier = Supplier::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'status' => 'active',
        ]);

        return [$restaurant, $central, $ingredient, $location, $supplier];
    }
}
