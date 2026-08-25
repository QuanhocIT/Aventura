<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\RequestForProposal;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RfpBid;
use App\Models\RfpItem;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierB2BIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $supplierUser;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected Supplier $supplier;

    protected Unit $unit;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        // Portal behavior is opt-in; these tests cover the retained portal
        // implementation without changing the product default.
        config(['portal.supplier_portal_enabled' => true]);

        Storage::fake('public');

        // Create roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $supplierRole = Role::firstOrCreate(['name' => 'supplier', 'guard_name' => 'web']);

        // Owner setup
        $this->owner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ])->save();

        // Supplier setup
        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'NPP Hải Sản Hùng Vương',
            'status' => 'active',
        ]);

        $this->supplierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->supplierUser->assignRole($supplierRole);

        // Unit and Ingredient Setup
        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'type' => 'mass',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'unit_id' => $this->unit->id,
            'name' => 'Tôm Sú',
            'sku' => 'TOM-SU-01',
            'average_cost' => 500, // 500đ per g
            'min_stock_level' => 1000, // 1kg
            'status' => 'active',
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 1200.0, // 1.2kg
            'theoretical_quantity' => 1200.0,
            'last_cost' => 500,
        ]);
    }

    /**
     * Test Pha 1: SLA Scorecard metrics calculation.
     */
    public function test_supplier_sla_metrics_calculation(): void
    {
        // 1. Create price history to test price volatility
        SupplierPriceHistory::create([
            'supplier_id' => $this->supplier->id,
            'ingredient_id' => $this->ingredient->id,
            'price' => 500,
            'effective_date' => now()->subDays(5),
        ]);
        SupplierPriceHistory::create([
            'supplier_id' => $this->supplier->id,
            'ingredient_id' => $this->ingredient->id,
            'price' => 550,
            'effective_date' => now()->subDays(2),
        ]);

        // 2. Create delivered purchase orders with SLA stats
        // PO 1: On time, no discrepancy, 5 stars
        $po1 = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-SLA-1',
            'status' => 'delivered',
            'total_amount' => 500000,
            'invoice_total_amount' => 500000,
            'delivery_due_date' => now()->addHours(2),
            'delivered_at' => now()->addHour(), // early / on-time
            'is_discrepant' => false,
            'rating' => 5,
            'rating_notes' => 'Giao hàng tốt',
            'created_by' => $this->owner->id,
        ]);

        // PO 2: Late (past 30 mins limit), discrepant, 2 stars
        $po2 = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-SLA-2',
            'status' => 'frozen',
            'total_amount' => 500000,
            'invoice_total_amount' => 600000,
            'delivery_due_date' => now()->subHours(2),
            'delivered_at' => now(), // 2 hours late
            'is_discrepant' => true,
            'rating' => 2,
            'rating_notes' => 'Giao trễ và lệch giá',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('suppliers.sla', $this->supplier->id));

        $response->assertOk();
        $response->assertJsonFragment([
            'supplier_name' => 'NPP Hải Sản Hùng Vương',
            'total_orders_analyzed' => 2,
            'on_time_rate' => 50, // 1 out of 2 on time
            'accuracy_rate' => 50, // 1 out of 2 accurate
            'average_rating' => 3.5, // (5 + 2) / 2
        ]);
    }

    /**
     * Test Pha 2: AI Auto-Replenishment forecasting and PO generation.
     */
    public function test_ai_auto_replenishment_triggers_and_drafts_po(): void
    {
        // 1. Setup a product and recipe (BOM)
        $product = Product::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Lẩu Tôm Sú',
        ]);

        // Recipe uses 300g of shrimp per pot
        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 300.0,
            'waste_rate' => 0.0,
        ]);

        // 2. Setup completed orders consuming shrimp in the last 30 days
        // 5 pots of shrimp soup completed -> consumes 1500g -> stock drops below min stock
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(1),
        ]);
        $order->items()->create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 200000,
            'line_total' => 1000000,
        ]);

        // 3. Trigger auto replenish
        $response = $this->actingAs($this->owner)->post(route('suppliers.auto-replenish'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert draft PO was created for shrimp supplier
        $po = PurchaseOrder::where('restaurant_id', $this->restaurant->id)
            ->where('supplier_id', $this->supplier->id)
            ->where('status', 'pending_approval')
            ->first();

        $this->assertNotNull($po);
        $this->assertGreaterThan(0, $po->total_amount);
        $this->assertCount(1, $po->items);
        $this->assertEquals($this->ingredient->id, $po->items->first()->ingredient_id);
    }

    /**
     * Test Pha 4: RFP bidding process.
     */
    public function test_rfp_bidding_flow(): void
    {
        // 1. Owner creates RFP
        $response = $this->actingAs($this->owner)->post(route('rfps.store'), [
            'title' => 'Gói thầu tôm sú quý 3',
            'description' => 'Yêu cầu tôm sú tươi cấp đông',
            'due_date' => now()->addDays(5)->toDateTimeString(),
            'items' => [
                [
                    'ingredient_name' => 'Tôm Sú',
                    'quantity_required' => 50.0,
                    'unit_symbol' => 'kg',
                    'notes' => 'Hàng loại 1',
                ],
            ],
        ]);

        $response->assertRedirect();

        $rfp = RequestForProposal::latest()->first();
        $this->assertNotNull($rfp);
        $this->assertEquals('open', $rfp->status);
        $this->assertCount(1, $rfp->items);

        // 2. Supplier submits a bid
        $rfpItem = $rfp->items->first();
        $otherRfp = RequestForProposal::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'RFP khác',
            'due_date' => now()->addDays(6),
            'status' => 'open',
        ]);
        $foreignRfpItem = RfpItem::create([
            'rfp_id' => $otherRfp->id,
            'ingredient_name' => 'Mặt hàng khác',
            'quantity_required' => 10,
            'unit_symbol' => 'kg',
        ]);

        $this->actingAs($this->supplierUser)
            ->postJson(route('supplier.rfps.bid', $rfp->id), [
                'proposed_delivery_date' => now()->addDays(7)->toDateString(),
                'items' => [['rfp_item_id' => $foreignRfpItem->id, 'proposed_price' => 1]],
            ])
            ->assertStatus(422);

        $response = $this->actingAs($this->supplierUser)->post(route('supplier.rfps.bid', $rfp->id), [
            'proposed_delivery_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Giá tốt nhất thị trường',
            'items' => [
                [
                    'rfp_item_id' => $rfpItem->id,
                    'proposed_price' => 450.0, // 450đ/g or /unit
                ],
            ],
        ]);

        $response->assertRedirect();

        $bid = RfpBid::where('rfp_id', $rfp->id)->where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($bid);
        $this->assertEquals('submitted', $bid->status);
        $this->assertEquals(50 * 450, $bid->total_amount);

        // 3. Owner accepts winning bid
        $response = $this->actingAs($this->owner)->post(route('rfps.bids.accept', $bid->id));

        $response->assertRedirect();

        // RFP should be completed
        $rfp->refresh();
        $this->assertEquals('completed', $rfp->status);

        // Bid should be accepted
        $bid->refresh();
        $this->assertEquals('accepted', $bid->status);

        // PO should be automatically generated and approved, with escrow locked
        $po = PurchaseOrder::where('po_number', 'LIKE', "%-RFP-{$rfp->id}%")->first();
        $this->assertNotNull($po);
        $this->assertEquals('approved', $po->status);
        $this->assertEquals($bid->total_amount, $po->total_amount);
        $this->assertEquals('escrow_locked', $po->payment_status);
        $this->assertNotNull($po->escrow_transaction_id);
    }

    /**
     * Test Pha 5: B2B Escrow Payments lifecycle.
     */
    public function test_b2b_escrow_payments_lifecycle(): void
    {
        $this->withoutExceptionHandling();

        // 1. Locked upon approval
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-ESCROW-TEST',
            'status' => 'approved',
            'total_amount' => 100000,
            'created_by' => $this->owner->id,
        ]);

        // Approve it and verify escrow is locked
        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.approve', $po->id));
        $po->refresh();
        $this->assertEquals('escrow_locked', $po->payment_status);
        $this->assertNotNull($po->escrow_transaction_id);

        // 2. Auto-release when verification succeeds with 0 discrepancy
        $po->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity_ordered' => 10.0,
            'price_per_unit' => 500,
            'total_cost' => 5000,
        ]);

        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.verify', $po->id), [
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity_received' => 10.0,
                    'invoice_price' => 500,
                ],
            ],
        ]);

        $po->refresh();
        $this->assertEquals('delivered', $po->status);
        $this->assertEquals('paid', $po->payment_status); // released!

        // 3. Keep locked and freeze when discrepancy is detected
        $po2 = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-ESCROW-DISCREPANT',
            'status' => 'approved',
            'total_amount' => 100000,
            'payment_status' => 'escrow_locked',
            'escrow_transaction_id' => 'ESC-MOCK-ID',
            'created_by' => $this->owner->id,
        ]);
        $po2->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity_ordered' => 10.0,
            'price_per_unit' => 500,
            'total_cost' => 5000,
        ]);

        // Verify with price mismatch -> freezes (bắt buộc ảnh + lý do khi lệch).
        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.verify', $po2->id), [
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity_received' => 10.0,
                    'invoice_price' => 600, // discrepant!
                ],
            ],
            'invoice_file' => UploadedFile::fake()->image('inv.jpg'),
            'mismatch_reason' => 'Giá hoá đơn cao hơn báo giá',
        ]);

        $po2->refresh();
        $this->assertEquals('frozen', $po2->status);
        $this->assertEquals('escrow_locked', $po2->payment_status); // remains locked!

        // 4. Manual release
        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.release-escrow', $po2->id));
        $po2->refresh();
        $this->assertEquals('paid', $po2->payment_status);
        $this->assertEquals('delivered', $po2->status);

        // Reset to lock to test manual refund
        $po2->update([
            'payment_status' => 'escrow_locked',
        ]);

        // 5. Manual refund
        $response = $this->actingAs($this->owner)->post(route('suppliers.orders.refund-escrow', $po2->id));
        $po2->refresh();
        $this->assertEquals('refunded', $po2->payment_status);
        $this->assertEquals('cancelled', $po2->status);
    }
}
