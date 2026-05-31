<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Restaurant $restaurant;
    protected RestaurantBranch $branch;
    protected Unit $unit;
    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();

        // Create standard roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'inventory_staff', 'guard_name' => 'web']);

        // Set up owner
        $this->owner = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->owner->assignRole($ownerRole);

        // Set up restaurant and branch
        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id
        ])->save();

        // Set up inventory staff
        $this->staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->staff->assignRole($staffRole);

        // Assign Employee and WorkShift so staff can bypass SetTenantContext
        $employee = \App\Models\Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->staff->id,
            'role_id' => $staffRole->id,
            'status' => 'active',
        ]);

        $shift = \App\Models\WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Test',
            'code' => 'CA_TEST',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        \App\Models\ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Set up Unit
        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass'
        ]);

        // Set up Ingredient
        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'name' => 'Thịt Bò Mỹ',
            'sku' => 'BEEF-US-01',
            'average_cost' => 100000,
            'status' => 'active'
        ]);

        // Set up Inventory
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 10.0,
            'theoretical_quantity' => 10.0,
            'last_cost' => 100000,
        ]);
    }

    public function test_purchase_requires_invoice_file(): void
    {
        $response = $this->actingAs($this->owner)->post(route('inventory.purchases.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 5.0,
            'unit_cost' => 120000,
            // 'invoice_file' is missing
        ]);

        $response->assertSessionHasErrors(['invoice_file']);
    }

    public function test_staff_purchase_creates_pending_approval_request_and_does_not_modify_stock(): void
    {
        $fakeInvoice = UploadedFile::fake()->image('invoice.jpg');

        $response = $this->actingAs($this->staff)->post(route('inventory.purchases.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 5.0,
            'unit_cost' => 120000,
            'invoice_file' => $fakeInvoice,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Stock must remain unchanged
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();
        $this->assertEquals(10.0, (float)$inventory->quantity_on_hand);

        // 2. An ApprovalRequest should be created in pending state
        $approval = ApprovalRequest::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'inventory_purchase')
            ->first();

        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals($this->staff->id, $approval->requester_id);

        // 3. The invoice_file_url should be stored in operation_data
        $this->assertArrayHasKey('invoice_file_url', $approval->operation_data);
        $this->assertStringStartsWith('/storage/invoices/', $approval->operation_data['invoice_file_url']);

        // Verify the file was stored on the fake disk
        $storedPath = str_replace('/storage/', '', $approval->operation_data['invoice_file_url']);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_owner_purchase_applies_immediately(): void
    {
        $fakeInvoice = UploadedFile::fake()->image('owner_invoice.png');

        $response = $this->actingAs($this->owner)->post(route('inventory.purchases.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 20.0,
            'unit_cost' => 130000,
            'invoice_file' => $fakeInvoice,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Stock must be incremented immediately (10 + 20 = 30)
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->first();
        $this->assertEquals(30.0, (float)$inventory->quantity_on_hand);

        // 2. Average cost should be recalculated (weighted average)
        // old stock: 10 @ 100,000 = 1,000,000
        // new stock: 20 @ 130,000 = 2,600,000
        // total cost: 3,600,000 / 30 = 120,000
        $this->ingredient->refresh();
        $this->assertEquals(120000.0, (float)$this->ingredient->average_cost);

        // 3. Inventory Transaction must be recorded with invoice_file_url
        $transaction = InventoryTransaction::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->where('type', 'purchase')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(20.0, (float)$transaction->quantity);
        $this->assertEquals(130000.0, (float)$transaction->unit_cost);
        $this->assertStringStartsWith('/storage/invoices/', $transaction->invoice_file_url);

        // No approval request should exist since it's the Owner
        $this->assertNull(ApprovalRequest::first());
    }

    public function test_owner_approving_staff_request_applies_stock_and_cost_recalculation(): void
    {
        $fakeInvoice = UploadedFile::fake()->image('staff_invoice.png');

        // Staff submits purchase
        $this->actingAs($this->staff)->post(route('inventory.purchases.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 20.0,
            'unit_cost' => 130000,
            'invoice_file' => $fakeInvoice,
        ]);

        $approval = ApprovalRequest::where('restaurant_id', $this->restaurant->id)
            ->where('operation_type', 'inventory_purchase')
            ->first();

        // Stock is still 10.0
        $inventory = Inventory::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertEquals(10.0, (float)$inventory->quantity_on_hand);

        // Owner approves
        $response = $this->actingAs($this->owner)->patch(route('approvals.approve', $approval));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Request status is approved
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);
        $this->assertEquals($this->owner->id, $approval->reviewer_id);

        // 2. Stock must now be incremented (10 + 20 = 30)
        $inventory->refresh();
        $this->assertEquals(30.0, (float)$inventory->quantity_on_hand);

        // 3. Recalculated cost is 120,000
        $this->ingredient->refresh();
        $this->assertEquals(120000.0, (float)$this->ingredient->average_cost);

        // 4. Inventory Transaction recorded
        $transaction = InventoryTransaction::where('restaurant_id', $this->restaurant->id)
            ->where('ingredient_id', $this->ingredient->id)
            ->where('type', 'purchase')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertStringStartsWith('/storage/invoices/', $transaction->invoice_file_url);
    }

    public function test_ai_forecast_endpoint_returns_valid_projections(): void
    {
        // Setup past consumption transactions to simulate AI learning
        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $this->ingredient->id,
            'performed_by' => $this->owner->id,
            'type' => 'usage',
            'direction' => 'out',
            'quantity' => 15.0, // 15kg used
            'occurred_at' => now()->subDays(2),
        ]);

        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $this->ingredient->id,
            'performed_by' => $this->owner->id,
            'type' => 'usage',
            'direction' => 'out',
            'quantity' => 30.0, // 30kg used
            'occurred_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->owner)->get(route('inventory.ai-forecast'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'forecast' => [
                '*' => [
                    'ingredient_id',
                    'ingredient_name',
                    'sku',
                    'unit_symbol',
                    'current_stock',
                    'min_stock_level',
                    'avg_daily_usage',
                    'predicted_usage_next_7_days',
                    'suggested_purchase',
                    'confidence_score',
                    'reason',
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        
        $item = collect($data['forecast'])->firstWhere('ingredient_id', $this->ingredient->id);
        $this->assertNotNull($item);
        $this->assertEquals('Thịt Bò Mỹ', $item['ingredient_name']);
        $this->assertEquals('kg', $item['unit_symbol']);
        $this->assertEquals(10.0, (float)$item['current_stock']);
        // 45kg used in 30 days -> 1.5kg daily usage
        $this->assertEquals(1.5, (float)$item['avg_daily_usage']);
        // predicted 7 days = 1.5 * 7 * 1.1 = 11.55
        $this->assertEquals(11.55, (float)$item['predicted_usage_next_7_days']);
        // suggested = 11.55 - 10.0 = 1.55
        $this->assertEquals(1.55, (float)$item['suggested_purchase']);
        $this->assertGreaterThanOrEqual(92.0, (float)$item['confidence_score']);
        $this->assertLessThanOrEqual(99.0, (float)$item['confidence_score']);
        
        // Assert that mock reason contains 'Phở bò' (hardcoded name in the controller) and '10 kg' (current stock + unit)
        $this->assertStringContainsString('Phở bò', $item['reason']);
        $this->assertStringContainsString('10 kg', $item['reason']);
    }
}
