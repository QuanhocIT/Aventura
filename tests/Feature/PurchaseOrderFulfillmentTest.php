<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCreated;
use App\Events\PurchaseOrderStatusUpdated;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Restaurant $restaurant;

    protected Restaurant $otherRestaurant;

    protected RestaurantBranch $branch;

    protected Unit $unit;

    protected Supplier $supplier;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        // Set up owner 1
        $this->owner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
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

        // Set up owner 2 (for isolation testing)
        $this->otherOwner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->otherOwner->assignRole($ownerRole);

        $this->otherRestaurant = Restaurant::factory()->create(['owner_user_id' => $this->otherOwner->id]);
        $this->otherOwner->forceFill([
            'restaurant_id' => $this->otherRestaurant->id,
        ])->save();

        // Set up Unit and Supplier and Ingredient
        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilôgam',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Nhà phân phối Thịt heo sạch',
            'status' => 'active',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'unit_id' => $this->unit->id,
            'name' => 'Thịt ba rọi heo',
            'price' => 120000,
            'average_cost' => 0,
            'status' => 'active',
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_can_create_purchase_order_with_items_and_dispatches_event(): void
    {
        Event::fake();

        $response = $this->actingAs($this->owner)->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'notes' => 'Giao trước 8 giờ sáng',
            'items' => [
                [
                    'ingredient_id' => $this->ingredient->id,
                    'quantity' => 10,
                    'unit_cost' => 115000,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $po = PurchaseOrder::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertNotNull($po);
        $this->assertEquals('received', $po->status);
        $this->assertEquals(1150000.0, (float) $po->total_amount);
        $this->assertEquals('Giao trước 8 giờ sáng', $po->notes);
        $this->assertEquals($this->owner->id, $po->created_by);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 10.0,
            'unit_cost' => 115000.0,
        ]);

        Event::assertDispatched(PurchaseOrderCreated::class, function ($event) use ($po) {
            return $event->purchaseOrder->id === $po->id;
        });
    }

    public function test_can_advance_purchase_order_status_sequentially(): void
    {
        Event::fake();

        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20260620-001',
            'status' => 'received',
            'total_amount' => 1150000,
            'created_by' => $this->owner->id,
        ]);

        // received -> preparing
        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'preparing',
        ]);
        $response->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertEquals('preparing', $po->status);
        Event::assertDispatched(PurchaseOrderStatusUpdated::class);

        // preparing -> shipping
        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'shipping',
        ]);
        $response->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertEquals('shipping', $po->status);

        // shipping -> delivered
        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'delivered',
        ]);
        $response->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertEquals('delivered', $po->status);
    }

    public function test_cannot_skip_status_in_workflow_transition(): void
    {
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20260620-002',
            'status' => 'received',
            'total_amount' => 1150000,
            'created_by' => $this->owner->id,
        ]);

        // received -> shipping (invalid transition, skips preparing)
        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'shipping',
        ]);

        $response->assertSessionHasErrors(['status']);
        $po->refresh();
        $this->assertEquals('received', $po->status);
    }

    public function test_delivered_purchase_order_automatically_updates_inventory_and_logs_transaction(): void
    {
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20260620-003',
            'status' => 'shipping',
            'total_amount' => 1150000,
            'created_by' => $this->owner->id,
        ]);

        $item = $po->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 10,
            'unit_cost' => 115000,
        ]);

        // Check initial stock is 0
        $this->assertEquals(0, (float) $this->ingredient->average_cost);

        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'delivered',
        ]);

        $response->assertSessionHasNoErrors();

        // 1. Stock in Inventories should be updated
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();

        $this->assertNotNull($inventory);
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);
        $this->assertEquals(115000.0, (float) $inventory->last_cost);

        // 2. Average cost of ingredient should be updated
        $this->ingredient->refresh();
        $this->assertEquals(115000.0, (float) $this->ingredient->average_cost);

        // 3. Inventory Transaction should be written
        $transaction = InventoryTransaction::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('purchase', $transaction->type);
        $this->assertEquals('in', $transaction->direction);
        $this->assertEquals(10.0, (float) $transaction->quantity);
        $this->assertEquals(115000.0, (float) $transaction->unit_cost);
        $this->assertEquals(1150000.0, (float) $transaction->total_cost);
        $this->assertEquals('Tự động nhập hàng hoàn thành từ PO #PO-20260620-003', $transaction->notes);
    }

    public function test_multi_tenant_isolation_prevents_updating_other_restaurant_po(): void
    {
        // PO of restaurant 1
        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20260620-004',
            'status' => 'received',
            'total_amount' => 1150000,
            'created_by' => $this->owner->id,
        ]);

        // Owner 2 attempts to update PO status of restaurant 1
        $response = $this->actingAs($this->otherOwner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'preparing',
        ]);

        // Model isolation or route binding context should fail (e.g., throwing 403 or 404)
        // Since BelongsToRestaurant applies global scopes, $po is not found or not accessible
        $response->assertStatus(404);

        $po->refresh();
        $this->assertEquals('received', $po->status);
    }

    public function test_delivered_purchase_order_with_invoice_file_uploads_to_s3_and_propagates_url(): void
    {
        Storage::fake('s3');

        $po = PurchaseOrder::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20260620-005',
            'status' => 'shipping',
            'total_amount' => 120000,
            'created_by' => $this->owner->id,
        ]);

        $po->items()->create([
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 1,
            'unit_cost' => 120000,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->owner)->patch(route('purchase-orders.update-status', $po), [
            'status' => 'delivered',
            'invoice_file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $po->refresh();
        $this->assertEquals('delivered', $po->status);
        $this->assertNotNull($po->invoice_file_url);

        // Check file was stored on s3
        Storage::disk('s3')->assertExists('po-invoices/'.$file->hashName());

        // Check the generated transaction has the same url
        $transaction = InventoryTransaction::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($po->invoice_file_url, $transaction->invoice_file_url);
    }
}
