<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;

    private User $managerA;

    private User $cashierA;

    private Restaurant $restaurantA;

    private RestaurantBranch $branchA;

    private User $ownerB;

    private Restaurant $restaurantB;

    private RestaurantBranch $branchB;

    private Role $ownerRole;

    private Role $managerRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles Setup
        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // 2. Tenant A Setup
        $this->ownerA = User::factory()->create(['status' => 'active']);
        $this->ownerA->assignRole($this->ownerRole);
        $this->restaurantA = Restaurant::factory()->create(['owner_user_id' => $this->ownerA->id]);
        $this->ownerA->update(['restaurant_id' => $this->restaurantA->id]);

        $this->branchA = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurantA->id,
            'manager_user_id' => $this->ownerA->id,
        ]);
        $this->ownerA->update(['branch_id' => $this->branchA->id]);

        $this->managerA = User::factory()->create([
            'restaurant_id' => $this->restaurantA->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
        $this->managerA->assignRole($this->managerRole);

        $this->cashierA = User::factory()->create([
            'restaurant_id' => $this->restaurantA->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
        ]);
        $this->cashierA->assignRole($this->cashierRole);

        $this->employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->cashierA->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
        ]);

        // 3. Tenant B Setup
        $this->ownerB = User::factory()->create(['status' => 'active']);
        $this->ownerB->assignRole($this->ownerRole);
        $this->restaurantB = Restaurant::factory()->create(['owner_user_id' => $this->ownerB->id]);
        $this->ownerB->update(['restaurant_id' => $this->restaurantB->id]);

        $this->branchB = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurantB->id,
            'manager_user_id' => $this->ownerB->id,
        ]);
        $this->ownerB->update(['branch_id' => $this->branchB->id]);
    }

    public function test_tenant_isolation_prevents_leakage(): void
    {
        // 1. Create a customer for Tenant A
        app(TenantContext::class)->setRestaurantId($this->restaurantA->id);
        $customerA = Customer::create([
            'full_name' => 'Khách Hàng A',
            'phone' => '0901111111',
            'email' => 'khachA@example.com',
            'gender' => 'male',
        ]);

        // 2. Create a customer for Tenant B
        app(TenantContext::class)->setRestaurantId($this->restaurantB->id);
        $customerB = Customer::create([
            'full_name' => 'Khách Hàng B',
            'phone' => '0902222222',
            'email' => 'khachB@example.com',
            'gender' => 'female',
        ]);

        // 3. Verify under global scope, Tenant A query only returns Customer A
        app(TenantContext::class)->setRestaurantId($this->restaurantA->id);
        $allA = Customer::all();
        $this->assertCount(1, $allA);
        $this->assertSame('Khách Hàng A', $allA->first()->full_name);

        // 4. Verify under global scope, Tenant B query only returns Customer B
        app(TenantContext::class)->setRestaurantId($this->restaurantB->id);
        $allB = Customer::all();
        $this->assertCount(1, $allB);
        $this->assertSame('Khách Hàng B', $allB->first()->full_name);
    }

    public function test_read_and_write_permission_for_authorised_roles(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurantA->id);

        // 1. Owner can access CRM and create
        $response = $this->actingAs($this->ownerA)->get('/customers');
        $response->assertStatus(200);

        $response = $this->actingAs($this->ownerA)->post('/customers', [
            'full_name' => 'Khách Thành Viên Mới',
            'phone' => '0903333333',
            'email' => 'member@example.com',
            'gender' => 'male',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'restaurant_id' => $this->restaurantA->id,
            'phone' => '0903333333',
        ]);

        // 2. Manager can access CRM
        $response = $this->actingAs($this->managerA)->get('/customers');
        $response->assertStatus(200);
    }

    public function test_owner_role_can_successfully_export_customer_csv(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurantA->id);
        Customer::create([
            'full_name' => 'Khách Hàng A',
            'phone' => '0901111111',
            'email' => 'khachA@example.com',
        ]);

        $response = $this->actingAs($this->ownerA)->get('/customers/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Khách Hàng A', $content);
        $this->assertStringContainsString('0901111111', $content);
    }

    public function test_manager_and_cashier_roles_are_blocked_from_exporting(): void
    {
        app(TenantContext::class)->setRestaurantId($this->restaurantA->id);
        Customer::create([
            'full_name' => 'Khách Hàng A',
            'phone' => '0901111111',
        ]);

        // 1. Manager check
        $response = $this->actingAs($this->managerA)->get('/customers/export');
        $response->assertStatus(403);

        // Create an active work shift and assign it to the cashier today so they can bypass SetTenantContext
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurantA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_overnight' => false,
            'status' => 'active',
        ]);

        $employee = Employee::where('user_id', $this->cashierA->id)->first();
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurantA->id,
            'branch_id' => $this->branchA->id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // 2. Cashier check
        $response = $this->actingAs($this->cashierA)->get('/customers/export');
        $response->assertStatus(403);
    }
}
