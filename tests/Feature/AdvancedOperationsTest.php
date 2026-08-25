<?php

namespace Tests\Feature;

use App\Jobs\RecalculateAverageCostJob;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\RequestForProposal;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RfpBid;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvancedOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branchA;

    protected RestaurantBranch $branchB;

    protected Supplier $supplier;

    protected Unit $unit;

    protected Ingredient $ingredient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        // Owner setup
        $this->owner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->restaurant->plan->update([
            'features' => array_merge($this->restaurant->plan->features ?? [], [
                'supplier_portal' => true,
                'hr_full' => true,
            ]),
        ]);

        $this->branchA = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);

        $this->owner->forceFill([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
        ])->save();

        $this->supplier = Supplier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'NPP Thắng Lợi',
            'status' => 'active',
        ]);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'type' => 'mass',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'supplier_id' => $this->supplier->id,
            'unit_id' => $this->unit->id,
            'name' => 'Thịt heo',
            'sku' => 'HEO-01',
            'average_cost' => 100, // 100đ per g
            'min_stock_level' => 100,
            'reorder_level' => 200,
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Internal transfer recommendations and execution.
     */
    public function test_internal_transfer_recommendations_and_execution(): void
    {
        // Branch A has deficit: 50 < 100 min_stock_level
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 50,
            'theoretical_quantity' => 50,
            'last_cost' => 100,
        ]);

        // Branch B has excess: 500 > 100 min_stock_level
        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 500,
            'theoretical_quantity' => 500,
            'last_cost' => 100,
        ]);

        // Fetch transfer recommendations (via PHP fallback in case FastAPI is offline)
        $response = $this->actingAs($this->owner)
            ->getJson(route('inventory.transfer-recommendations'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['recommendations', 'branches']);

        $data = $response->json();
        $this->assertCount(1, $data['recommendations']);
        $rec = $data['recommendations'][0];
        $this->assertEquals($this->branchB->id, $rec['from_branch_id']);
        $this->assertEquals($this->branchA->id, $rec['to_branch_id']);
        $this->assertEquals($this->ingredient->id, $rec['ingredient_id']);
        $this->assertEquals(50, $rec['suggested_quantity']); // deficit = 100 - 50 = 50

        // Execute transfer
        $postResponse = $this->actingAs($this->owner)
            ->post(route('inventory.internal-transfers'), [
                'from_branch_id' => $this->branchB->id,
                'to_branch_id' => $this->branchA->id,
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 50,
                'notes' => 'AI recommended transfer',
            ]);

        $postResponse->assertStatus(302); // Redirect back

        // Verify inventory levels
        $invA = Inventory::where('branch_id', $this->branchA->id)->where('ingredient_id', $this->ingredient->id)->first();
        $invB = Inventory::where('branch_id', $this->branchB->id)->where('ingredient_id', $this->ingredient->id)->first();

        $this->assertEquals(100.0, (float) $invA->quantity_on_hand); // 50 + 50
        $this->assertEquals(450.0, (float) $invB->quantity_on_hand); // 500 - 50
    }

    /**
     * Test 2: Real-time recipe cost price propagation.
     */
    public function test_recalculate_average_cost_propagates_to_products(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Sườn Xào Chua Ngọt',
            'code' => 'SUON-XAO',
            'slug' => 'suon-xao',
            'price' => 80000,
            'cost_price' => 0,
            'track_inventory' => true,
        ]);

        // Recipe uses 0.5 kg of Meat, waste rate 10%
        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 0.5,
            'waste_rate' => 10.0,
        ]);

        // Run the RecalculateAverageCostJob to simulate ingredient cost update
        // Old avg: 100, New: 120
        $job = new RecalculateAverageCostJob(
            $this->restaurant->id,
            $this->ingredient->id,
            100.0, // old quantity
            100.0, // new quantity
            140.0  // new cost
        );
        $job->handle();

        // Fresh ingredient cost: (100 * 100 + 100 * 140) / 200 = 120
        $this->ingredient->refresh();
        $this->assertEquals(120.0, (float) $this->ingredient->average_cost);

        // Product cost price recalculation: 0.5 kg * 120 * (1 + 10%) = 66
        $product->refresh();
        $this->assertEquals(66.0, (float) $product->cost_price);
    }

    /**
     * Test 3: Auto RFP creation and AI scoring.
     */
    public function test_auto_rfp_creation_and_ai_scoring(): void
    {
        // Cổng Nhà cung cấp mặc định TẮT; route /rfps nằm sau middleware
        // EnsureSupplierPortalEnabled nên phải bật tường minh, không dựa vào .env máy chạy.
        config(['portal.supplier_portal_enabled' => true]);

        // Create an inventory record
        $inventory = Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 205, // slightly above reorder level of 200
            'theoretical_quantity' => 205,
            'last_cost' => 100,
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Thịt heo xào',
            'code' => 'HEO-XAO',
            'slug' => 'thit-heo-xao',
            'price' => 50000,
            'cost_price' => 20,
            'track_inventory' => true,
        ]);

        ProductRecipe::create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'ingredient_id' => $this->ingredient->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10, // deduct 10
            'waste_rate' => 0.0,
        ]);

        // Place order to deduct inventory
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'ORD-12345',
            'total_amount' => 50000,
            'status' => 'completed',
        ]);
        $order->items()->create([
            'restaurant_id' => $this->restaurant->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'line_total' => 50000,
        ]);

        // Execute inventory deduction
        $inventoryService = app(InventoryService::class);
        $inventoryService->deductInventoryForOrder($order, $this->owner);

        // Inventory is now 205 - 10 = 195 (which is below reorder level of 200)
        $inventory->refresh();
        $this->assertEquals(195.0, (float) $inventory->quantity_on_hand);

        // Verify that an auto RFP was created
        $rfp = RequestForProposal::where('restaurant_id', $this->restaurant->id)
            ->where('title', 'like', 'AI Tự động gom hàng%')
            ->first();

        $this->assertNotNull($rfp);
        $this->assertEquals('open', $rfp->status);

        $rfpItem = $rfp->items()->first();
        $this->assertEquals($this->ingredient->name, $rfpItem->ingredient_name);

        // Expected quantity: min_stock_level * 2 - currentStock = 100 * 2 - 195 = 5
        $this->assertEquals(5.0, (float) $rfpItem->quantity_required);

        // Submit a bid to test index scoring
        $bid = RfpBid::create([
            'rfp_id' => $rfp->id,
            'supplier_id' => $this->supplier->id,
            'proposed_delivery_date' => now()->addDays(2),
            'total_amount' => 1000,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('rfps.index'));

        $response->assertStatus(200);
        $props = $response->original->getData()['page']['props'];
        $returnedRfps = $props['rfps'];

        $this->assertNotEmpty($returnedRfps);
        $returnedBid = $returnedRfps[0]['bids'][0];
        $this->assertTrue($returnedBid['is_ai_recommended']);
        $this->assertNotNull($returnedBid['ai_score']);
        $this->assertNotNull($returnedBid['ai_reason']);
    }

    /**
     * Test 4: Manager salary access and fraud violation proposal flow.
     */
    public function test_manager_salary_access_and_fraud_violation_proposal(): void
    {
        // Seed permissions & roles
        $seeder = new PermissionsSeeder;
        $seeder->run();

        // Create a manager user
        $managerRole = Role::where('name', 'manager')->first();
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $manager->assignRole($managerRole);

        // 1. Verify manager can access salary index page (thanks to manage_salary permission)
        $response = $this->actingAs($manager)
            ->get(route('salaries.index'));
        $response->assertStatus(200);

        // Create an employee profile in the same restaurant to record violation against
        $employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'full_name' => 'Nguyen Van A',
            'status' => 'active',
            'employee_code' => 'EMP-TEST-99',
            'role_id' => $managerRole->id,
        ]);

        // 2. Verify manager can report a violation from the fraud page (which goes to approval)
        $violationData = [
            'employee_id' => $employee->id,
            'violation_type' => 'Hao hụt nguyên liệu bất thường',
            'severity' => 'medium',
            'description' => 'Phát hiện hao hụt 5kg thịt heo không rõ nguyên nhân',
            'penalty_amount' => 500000,
            'occurred_at' => now()->toDateString(),
            'apply_deduction' => true,
        ];

        $violationResponse = $this->actingAs($manager)
            ->post(route('fraud.violation.store'), $violationData);

        $violationResponse->assertStatus(302); // Redirect back

        // Verify ViolationReport was created
        $this->assertDatabaseHas('violation_reports', [
            'restaurant_id' => $this->restaurant->id,
            'employee_id' => $employee->id,
            'violation_type' => 'Hao hụt nguyên liệu bất thường',
            'status' => 'open',
        ]);

        // Verify ApprovalRequest was created because the manager cannot approve directly
        $this->assertDatabaseHas('approval_requests', [
            'restaurant_id' => $this->restaurant->id,
            'operation_type' => 'salary_adjustment',
            'status' => 'pending',
        ]);
    }
}
