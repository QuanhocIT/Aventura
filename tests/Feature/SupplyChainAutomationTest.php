<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplierPriceHistory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplyChainAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected Unit $unit;

    protected Ingredient $ingredientLow;

    protected Ingredient $ingredientSafe;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        // Owner user
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

        // Manager user (for role-based access tests)
        $this->manager = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->manager->assignRole($managerRole);

        // Suppliers
        $this->supplierA = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'NCC Rau Sạch Đà Lạt',
            'status' => 'active',
        ]);

        $this->supplierB = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'NCC Hải Sản Vũng Tàu',
            'status' => 'active',
        ]);

        // Unit
        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        // Ingredient LOW stock (below min_stock_level) - linked to supplier A
        $this->ingredientLow = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplierA->id,
            'unit_id' => $this->unit->id,
            'name' => 'Rau Xà Lách',
            'sku' => 'RAU-XL-01',
            'average_cost' => 25000,
            'min_stock_level' => 50.0, // 50 kg minimum
            'status' => 'active',
        ]);

        // Stock only 10kg => deficit = 40kg
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredientLow->id,
            'quantity_on_hand' => 10.0,
            'theoretical_quantity' => 10.0,
            'last_cost' => 25000,
        ]);

        // Ingredient SAFE stock (above min_stock_level)
        $this->ingredientSafe = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplierB->id,
            'unit_id' => $this->unit->id,
            'name' => 'Tôm Hùm',
            'sku' => 'TOM-HUM-01',
            'average_cost' => 800000,
            'min_stock_level' => 5.0, // 5 kg minimum
            'status' => 'active',
        ]);

        // Stock 20kg => safe, no deficit
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredientSafe->id,
            'quantity_on_hand' => 20.0,
            'theoretical_quantity' => 20.0,
            'last_cost' => 800000,
        ]);
    }

    // =========================================================================
    // REPLENISH COCKPIT TESTS
    // =========================================================================

    /**
     * Test cockpit returns only understocked ingredients.
     */
    public function test_cockpit_returns_only_understocked_ingredients(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertOk();

        $data = $response->json();
        $recommendations = $data['recommendations'];

        // Only ingredientLow should appear (stock 10 < min 50)
        $this->assertCount(1, $recommendations);
        $this->assertEquals($this->ingredientLow->id, $recommendations[0]['ingredient_id']);
        $this->assertEquals('Rau Xà Lách', $recommendations[0]['ingredient_name']);
        $this->assertEquals('RAU-XL-01', $recommendations[0]['sku']);
    }

    /**
     * Test cockpit calculates correct deficit and suggested quantity (×1.2 margin).
     */
    public function test_cockpit_calculates_correct_deficit_and_suggested_quantity(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertOk();

        $rec = $response->json('recommendations.0');

        // Deficit = min_stock(50) - current_stock(10) = 40
        // Suggested = 40 * 1.2 = 48
        $this->assertEquals(10.0, $rec['current_stock']);
        $this->assertEquals(50.0, $rec['min_stock_level']);
        $this->assertEquals(48.0, $rec['suggested_quantity']);
    }

    /**
     * Test cockpit identifies optimal supplier from price history (lowest price).
     */
    public function test_cockpit_identifies_optimal_supplier_by_lowest_price(): void
    {
        // Supplier A offers at 22000đ/kg (cheaper)
        SupplierPriceHistory::create([
            'supplier_id' => $this->supplierA->id,
            'ingredient_id' => $this->ingredientLow->id,
            'price' => 22000,
            'effective_date' => now()->subDays(3),
        ]);

        // Supplier B offers at 28000đ/kg (more expensive)
        SupplierPriceHistory::create([
            'supplier_id' => $this->supplierB->id,
            'ingredient_id' => $this->ingredientLow->id,
            'price' => 28000,
            'effective_date' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertOk();

        $rec = $response->json('recommendations.0');

        // Supplier A should be optimal (lowest price)
        $this->assertNotNull($rec['optimal_supplier']);
        $this->assertEquals($this->supplierA->id, $rec['optimal_supplier']['id']);
        $this->assertEquals(22000, $rec['optimal_price']);
        $this->assertTrue($rec['is_cheapest_from_history']);
    }

    /**
     * Test cockpit falls back to default supplier when no price history exists.
     */
    public function test_cockpit_falls_back_to_default_supplier_without_price_history(): void
    {
        // No price history created
        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertOk();

        $rec = $response->json('recommendations.0');

        // Should fall back to the ingredient's default supplier
        $this->assertNotNull($rec['default_supplier']);
        $this->assertEquals($this->supplierA->id, $rec['default_supplier']['id']);
    }

    /**
     * Test cockpit returns empty when all stock levels are safe.
     */
    public function test_cockpit_returns_empty_when_stock_is_safe(): void
    {
        // Boost the low ingredient's stock above minimum
        Inventory::where('ingredient_id', $this->ingredientLow->id)->update([
            'quantity_on_hand' => 100.0,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertOk();
        $this->assertEmpty($response->json('recommendations'));
    }

    /**
     * Test cockpit rejects unauthorized access (waiter role).
     */
    public function test_cockpit_rejects_unauthorized_users(): void
    {
        $waiter = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $waiter->assignRole('waiter');

        $response = $this->actingAs($waiter)
            ->get(route('suppliers.replenish-cockpit'));

        $response->assertForbidden();
    }

    // =========================================================================
    // BULK DRAFT PO TESTS
    // =========================================================================

    /**
     * Test bulk draft PO creates purchase orders grouped by supplier.
     */
    public function test_bulk_draft_po_creates_orders_grouped_by_supplier(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'quantity' => 48.0,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 22000,
                    ],
                    [
                        'ingredient_id' => $this->ingredientSafe->id,
                        'quantity' => 10.0,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 750000,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Only 1 PO should be created (both items go to supplier A)
        $pos = PurchaseOrder::where('restaurant_id', $this->restaurant->id)
            ->where('supplier_id', $this->supplierA->id)
            ->where('status', 'pending_approval')
            ->get();

        $this->assertCount(1, $pos);

        $po = $pos->first();
        $this->assertCount(2, $po->items);
        $this->assertEquals('pending_approval', $po->status);

        // Verify total amount: (48 * 22000) + (10 * 750000) = 1,056,000 + 7,500,000 = 8,556,000
        $expectedTotal = (48.0 * 22000) + (10.0 * 750000);
        $this->assertEquals($expectedTotal, (float) $po->total_amount);
    }

    /**
     * Test bulk draft PO creates separate POs for different suppliers.
     */
    public function test_bulk_draft_po_splits_by_supplier(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'quantity' => 48.0,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 22000,
                    ],
                    [
                        'ingredient_id' => $this->ingredientSafe->id,
                        'quantity' => 5.0,
                        'supplier_id' => $this->supplierB->id,
                        'price' => 800000,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // 2 separate POs: one for supplier A, one for supplier B
        $poA = PurchaseOrder::where('supplier_id', $this->supplierA->id)
            ->where('status', 'pending_approval')
            ->first();
        $poB = PurchaseOrder::where('supplier_id', $this->supplierB->id)
            ->where('status', 'pending_approval')
            ->first();

        $this->assertNotNull($poA);
        $this->assertNotNull($poB);
        $this->assertCount(1, $poA->items);
        $this->assertCount(1, $poB->items);
    }

    /**
     * Test bulk draft PO validates required fields.
     */
    public function test_bulk_draft_po_validates_required_fields(): void
    {
        // Empty items array
        $response = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [],
            ]);

        $response->assertSessionHasErrors('items');

        // Missing quantity
        $response = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 22000,
                        // 'quantity' missing
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    /**
     * Test bulk draft PO rejects invalid supplier_id.
     */
    public function test_bulk_draft_po_rejects_invalid_supplier(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'quantity' => 10.0,
                        'supplier_id' => 99999, // nonexistent
                        'price' => 22000,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.supplier_id');
    }

    /**
     * Test PO number format and notes from cockpit auto-drafting.
     */
    public function test_bulk_draft_po_sets_correct_metadata(): void
    {
        $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'quantity' => 48.0,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 22000,
                    ],
                ],
            ]);

        $po = PurchaseOrder::where('restaurant_id', $this->restaurant->id)
            ->where('status', 'pending_approval')
            ->first();

        $this->assertNotNull($po);
        $this->assertStringStartsWith('PO-', $po->po_number);
        $this->assertStringContainsString('DRAFT', $po->po_number);
        $this->assertEquals($this->owner->id, $po->created_by);
        $this->assertNotNull($po->delivery_due_date);
        $this->assertStringContainsString('Cockpit', $po->notes);
    }

    /**
     * Test bulk draft PO rejects unauthorized users.
     */
    public function test_bulk_draft_po_rejects_unauthorized_users(): void
    {
        $waiter = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $waiter->assignRole('waiter');

        $response = $this->actingAs($waiter)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $this->ingredientLow->id,
                        'quantity' => 10.0,
                        'supplier_id' => $this->supplierA->id,
                        'price' => 22000,
                    ],
                ],
            ]);

        $response->assertForbidden();
    }

    // =========================================================================
    // SLA DASHBOARD TESTS
    // =========================================================================

    /**
     * Test SLA dashboard returns all active suppliers with metrics.
     */
    public function test_sla_dashboard_returns_all_active_suppliers(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.sla-dashboard'));

        $response->assertOk();

        $suppliers = $response->json('suppliers');

        // Both active suppliers should appear
        $this->assertCount(2, $suppliers);

        $names = collect($suppliers)->pluck('supplier_name')->toArray();
        $this->assertContains('NCC Rau Sạch Đà Lạt', $names);
        $this->assertContains('NCC Hải Sản Vũng Tàu', $names);
    }

    /**
     * Test SLA dashboard excludes inactive suppliers.
     */
    public function test_sla_dashboard_excludes_inactive_suppliers(): void
    {
        // Deactivate supplier B
        $this->supplierB->update(['status' => 'inactive']);

        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.sla-dashboard'));

        $response->assertOk();

        $suppliers = $response->json('suppliers');
        $this->assertCount(1, $suppliers);
        $this->assertEquals('NCC Rau Sạch Đà Lạt', $suppliers[0]['supplier_name']);
    }

    /**
     * Test SLA dashboard calculates correct metrics for supplier with delivered orders.
     */
    public function test_sla_dashboard_calculates_correct_sla_metrics(): void
    {
        // Create 2 POs for supplier A:
        // PO1: On-time, accurate, rated 5
        PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierA->id,
            'po_number' => 'PO-SLA-DASH-1',
            'status' => 'delivered',
            'total_amount' => 300000,
            'invoice_total_amount' => 300000,
            'delivery_due_date' => now()->addHours(2),
            'delivered_at' => now()->addHour(),
            'is_discrepant' => false,
            'rating' => 5,
            'rating_notes' => 'Hoàn hảo',
            'created_by' => $this->owner->id,
        ]);

        // PO2: Late, discrepant, rated 3
        PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierA->id,
            'po_number' => 'PO-SLA-DASH-2',
            'status' => 'frozen',
            'total_amount' => 400000,
            'invoice_total_amount' => 500000,
            'delivery_due_date' => now()->subHours(3),
            'delivered_at' => now(),
            'is_discrepant' => true,
            'rating' => 3,
            'rating_notes' => 'Giao trễ',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.sla-dashboard'));

        $response->assertOk();

        $suppliers = $response->json('suppliers');
        $supplierAData = collect($suppliers)->firstWhere('supplier_id', $this->supplierA->id);

        $this->assertNotNull($supplierAData);
        $this->assertEquals(2, $supplierAData['total_orders_analyzed']);
        $this->assertEquals(50.0, $supplierAData['on_time_rate']);    // 1/2
        $this->assertEquals(50.0, $supplierAData['accuracy_rate']);   // 1/2
        $this->assertEquals(4.0, $supplierAData['average_rating']);   // (5+3)/2
    }

    /**
     * Test SLA dashboard returns default metrics when no orders exist.
     */
    public function test_sla_dashboard_returns_defaults_for_no_orders(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('suppliers.sla-dashboard'));

        $response->assertOk();

        $suppliers = $response->json('suppliers');
        $supplierAData = collect($suppliers)->firstWhere('supplier_id', $this->supplierA->id);

        // With no orders analyzed, rates should default to 100%
        $this->assertEquals(0, $supplierAData['total_orders_analyzed']);
        $this->assertEquals(100.0, $supplierAData['on_time_rate']);
        $this->assertEquals(100.0, $supplierAData['accuracy_rate']);
    }

    /**
     * Test SLA dashboard rejects unauthorized access.
     */
    public function test_sla_dashboard_rejects_unauthorized_users(): void
    {
        $waiter = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $waiter->assignRole('waiter');

        $response = $this->actingAs($waiter)
            ->get(route('suppliers.sla-dashboard'));

        $response->assertForbidden();
    }

    /**
     * Test manager can access cockpit and SLA dashboard.
     */
    public function test_manager_can_access_cockpit_and_sla(): void
    {
        $cockpitResponse = $this->actingAs($this->manager)
            ->get(route('suppliers.replenish-cockpit'));
        $cockpitResponse->assertOk();

        $slaResponse = $this->actingAs($this->manager)
            ->get(route('suppliers.sla-dashboard'));
        $slaResponse->assertOk();
    }

    // =========================================================================
    // END-TO-END INTEGRATION TEST
    // =========================================================================

    /**
     * Test full flow: Cockpit detects understock → Bulk PO draft → PO appears in system.
     */
    public function test_full_cockpit_to_po_creation_flow(): void
    {
        // Set up price history so cockpit can identify optimal supplier
        SupplierPriceHistory::create([
            'supplier_id' => $this->supplierA->id,
            'ingredient_id' => $this->ingredientLow->id,
            'price' => 20000,
            'effective_date' => now()->subDay(),
        ]);

        // Step 1: Cockpit scan
        $cockpitResponse = $this->actingAs($this->owner)
            ->get(route('suppliers.replenish-cockpit'));

        $cockpitResponse->assertOk();
        $recs = $cockpitResponse->json('recommendations');
        $this->assertCount(1, $recs);

        $rec = $recs[0];
        $this->assertEquals($this->ingredientLow->id, $rec['ingredient_id']);

        // Step 2: Use cockpit data to bulk draft PO
        $poResponse = $this->actingAs($this->owner)
            ->post(route('suppliers.draft-po-bulk'), [
                'items' => [
                    [
                        'ingredient_id' => $rec['ingredient_id'],
                        'quantity' => $rec['suggested_quantity'],
                        'supplier_id' => $rec['optimal_supplier']['id'],
                        'price' => $rec['optimal_price'],
                    ],
                ],
            ]);

        $poResponse->assertRedirect();
        $poResponse->assertSessionHas('success');

        // Step 3: Verify PO was created
        $po = PurchaseOrder::where('restaurant_id', $this->restaurant->id)
            ->where('supplier_id', $this->supplierA->id)
            ->where('status', 'pending_approval')
            ->first();

        $this->assertNotNull($po);
        $this->assertEquals('pending_approval', $po->status);
        $this->assertCount(1, $po->items);

        $poItem = $po->items->first();
        $this->assertEquals($this->ingredientLow->id, $poItem->ingredient_id);
        $this->assertEquals(48.0, (float) $poItem->quantity_ordered);
        $this->assertEquals(20000, (float) $poItem->price_per_unit);

        // Step 4: Verify SLA dashboard includes both suppliers
        $slaResponse = $this->actingAs($this->owner)
            ->get(route('suppliers.sla-dashboard'));

        $slaResponse->assertOk();
        $this->assertCount(2, $slaResponse->json('suppliers'));
    }
}
