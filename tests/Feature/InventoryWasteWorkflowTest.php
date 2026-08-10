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
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
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
            // branch_id: khớp với cách app thật ghi (kế thừa từ ingredient) —
            // xem InventoryManagementController/InventoryService — cần thiết vì
            // trang tồn kho giờ lọc theo chi nhánh đang xem.
            'branch_id' => $this->branch->id,
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
            'branch_id' => $this->branch->id,
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
            'branch_id' => $this->branch->id,
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

    public function test_kitchen_waste_report_waits_for_owner_confirmation_before_changing_stock(): void
    {
        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $kitchen = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $kitchen->assignRole($kitchenRole);

        $approvePermission = Permission::firstOrCreate([
            'name' => 'approve_requests',
            'guard_name' => 'web',
        ]);
        $this->owner->givePermissionTo($approvePermission);

        // Even if a staff member is accidentally included in the request,
        // a kitchen report is still a report without salary responsibility.
        $response = $this->actingAs($kitchen)->post(route('inventory.waste.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 2,
            'employee_id' => $this->employee->id,
            'waste_category' => 'expired',
            'notes' => 'Lô đã quá hạn, không quy trách nhiệm',
        ]);

        $response->assertSessionHasNoErrors();

        $approval = ApprovalRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('inventory_waste', $approval->operation_type);
        $this->assertSame('pending', $approval->status);
        $this->assertSame($this->ingredient->name, $approval->operation_data['ingredient_name']);
        $this->assertNull($approval->operation_data['employee_id']);
        $this->assertSame('expired', $approval->operation_data['waste_category']);
        $this->assertDatabaseMissing('inventory_transactions', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
        ]);
        $this->assertEquals(0, \DB::table('salary_adjustments')->count());
        $inventory = Inventory::where('ingredient_id', $this->ingredient->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $inventory->quantity_on_hand);

        $response = $this->actingAs($this->owner)->patch(route('approvals.approve', $approval));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_transactions', [
            'ingredient_id' => $this->ingredient->id,
            'type' => 'waste',
            'waste_category' => 'expired',
            'performed_by' => $kitchen->id,
            'quantity' => 2,
        ]);
        $this->assertEquals(8.0, (float) $inventory->fresh()->quantity_on_hand);
        $this->assertEquals(0, \DB::table('salary_adjustments')->count());
    }

    public function test_kitchen_waste_report_notifies_owner_and_branch_manager(): void
    {
        $this->branch->update(['manager_user_id' => $this->manager->id]);

        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $kitchen = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);
        $kitchen->assignRole($kitchenRole);

        $this->actingAs($kitchen)->post(route('inventory.waste.store'), [
            'ingredient_id' => $this->ingredient->id,
            'quantity' => 1,
            'waste_category' => 'damaged',
            'notes' => 'Hộp bị đổ trong lúc sơ chế',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->owner->id,
            'type' => ApprovalRequestedNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->manager->id,
            'type' => ApprovalRequestedNotification::class,
        ]);
    }
}
