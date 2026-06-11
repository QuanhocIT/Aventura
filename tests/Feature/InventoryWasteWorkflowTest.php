<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryWasteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branch;

    protected Unit $unit;

    protected Ingredient $ingredient;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->owner = User::factory()->create();
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

        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->manager->assignRole($managerRole);

        $this->unit = Unit::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Hộp',
            'symbol' => 'hộp',
            'type' => 'count',
        ]);

        $this->ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'name' => 'Sữa đặc',
            'sku' => 'SUA-DAC',
            'average_cost' => 15000,
            'status' => 'active',
        ]);

        $this->employee = Employee::create([
            'restaurant_id' => $this->restaurant->id,
            'employee_code' => 'EMP-TEST',
            'full_name' => 'Văn Quân Lê',
            'email' => 'vanquanle@example.com',
            'phone' => '0912345678',
            'citizen_id_number' => '123456789013',
            'address' => 'Hà Nội',
            'hire_date' => today(),
            'base_salary' => 6000000,
            'job_title' => 'Quản lý',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role_id' => $managerRole->id,
        ]);

        Inventory::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'ingredient_id' => $this->ingredient->id,
            'quantity_on_hand' => 10.0,
            'theoretical_quantity' => 10.0,
            'last_cost' => 15000,
        ]);
    }

    public function test_inventory_page_includes_recent_wastes(): void
    {
        // 1. Create a waste transaction directly (oldest - index 2)
        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $this->ingredient->id,
            'performed_by' => $this->owner->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 2.0,
            'unit_cost' => 15000,
            'total_cost' => 30000,
            'notes' => 'Sữa chua hỏng',
            'occurred_at' => now()->subMinutes(10),
        ]);

        // 2. Create a pending approval request (middle - index 1)
        $reqPending = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'requester_id' => $this->manager->id,
            'operation_type' => 'inventory_waste',
            'operation_data' => [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 1.0,
                'employee_id' => $this->employee->id,
                'notes' => 'Đổ sữa',
            ],
            'status' => 'pending',
        ]);
        $reqPending->created_at = now()->subMinutes(5);
        $reqPending->save();

        // 3. Create a rejected approval request (newest - index 0)
        $reqRejected = ApprovalRequest::create([
            'restaurant_id' => $this->restaurant->id,
            'requester_id' => $this->manager->id,
            'operation_type' => 'inventory_waste',
            'operation_data' => [
                'ingredient_id' => $this->ingredient->id,
                'quantity' => 3.0,
                'notes' => 'Rau héo',
            ],
            'status' => 'rejected',
            'rejection_reason' => 'Không đúng thực tế',
        ]);
        $reqRejected->created_at = now();
        $reqRejected->save();

        // 4. Call the inventory page
        $response = $this->actingAs($this->owner)->get(route('inventory.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('inventory/Index')
            ->has('recentWastes', 3)
            ->where('recentWastes.0.ingredient_name', 'Sữa đặc')
            ->where('recentWastes.0.status', 'rejected')
            ->where('recentWastes.1.status', 'pending')
            ->where('recentWastes.2.status', 'approved')
        );
    }
}
