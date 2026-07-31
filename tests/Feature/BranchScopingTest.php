<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * TRƯỚC ĐÂY: OrdersController/InventoryManagementController/SalaryController/
 * ScheduleController hoàn toàn KHÔNG lọc theo chi nhánh đang chọn (active_branch_id)
 * dù các bảng liên quan đều có cột branch_id — owner chuyển chi nhánh trên UI
 * nhưng vẫn thấy dữ liệu MỌI chi nhánh trộn lẫn.
 */
class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected RestaurantBranch $branchA;

    protected RestaurantBranch $branchB;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web'])
            ->syncPermissions(['create_orders', 'manage_orders', 'process_payments', 'manage_kitchen', 'manage_salary']);

        $this->restaurant = Restaurant::factory()->create(['status' => 'active']);
        $this->branchA = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'code' => 'BR-A']);
        $this->branchB = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id, 'code' => 'BR-B']);

        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole($ownerRole);
        $ownerRole->syncPermissions(Permission::all());
    }

    private function switchToBranch(RestaurantBranch $branch): void
    {
        $this->post(route('branch.switch'), ['branch_id' => $branch->id])->assertRedirect();
    }

    public function test_orders_index_only_shows_active_branch_orders(): void
    {
        Order::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'order_number' => 'ORD-AAA111']);
        Order::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'order_number' => 'ORD-BBB222']);

        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->get(route('orders.index'));
        $response->assertOk();

        $orders = $response->original->getData()['page']['props']['orders'];
        $orderNumbers = collect($orders)->pluck('order_number')->all();

        $this->assertContains('ORD-AAA111', $orderNumbers);
        $this->assertNotContains('ORD-BBB222', $orderNumbers, 'Đơn hàng chi nhánh B không được xuất hiện khi đang xem chi nhánh A.');
    }

    public function test_inventory_page_only_shows_active_branch_stock(): void
    {
        $ingA = Ingredient::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'name' => 'Nguyên liệu chi nhánh A']);
        $ingB = Ingredient::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'name' => 'Nguyên liệu chi nhánh B']);
        Inventory::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'ingredient_id' => $ingA->id]);
        Inventory::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'ingredient_id' => $ingB->id]);

        $this->restaurant->plan->update(['features' => array_merge($this->restaurant->plan->features ?? [], ['inventory_basic' => true])]);

        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->get(route('inventory.index'));
        $response->assertOk();

        $ingredients = $response->original->getData()['page']['props']['ingredients'];
        $names = collect($ingredients)->pluck('name')->all();

        $this->assertContains('Nguyên liệu chi nhánh A', $names);
        $this->assertNotContains('Nguyên liệu chi nhánh B', $names, 'Nguyên liệu chi nhánh B không được xuất hiện khi đang xem chi nhánh A.');
    }

    public function test_salary_index_only_shows_active_branch_payroll(): void
    {
        $this->restaurant->plan->update(['features' => array_merge($this->restaurant->plan->features ?? [], ['hr_full' => true])]);

        $empA = Employee::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'full_name' => 'Nhân viên A']);
        $empB = Employee::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'full_name' => 'Nhân viên B']);

        $periodStart = today()->startOfMonth()->toDateString();
        $periodEnd = today()->endOfMonth()->toDateString();

        Salary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'employee_id' => $empA->id,
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'base_salary' => 5000000,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_salary' => 5000000,
            'status' => 'draft',
        ]);
        Salary::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'employee_id' => $empB->id,
            'pay_period_start' => $periodStart,
            'pay_period_end' => $periodEnd,
            'base_salary' => 6000000,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_salary' => 6000000,
            'status' => 'draft',
        ]);

        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->get(route('salaries.index'));
        $response->assertOk();

        $salaries = $response->original->getData()['page']['props']['salaries'];
        $names = collect($salaries)->pluck('employee_name')->all();

        $this->assertContains('Nhân viên A', $names);
        $this->assertNotContains('Nhân viên B', $names, 'Bảng lương chi nhánh B không được xuất hiện khi đang xem chi nhánh A.');
    }

    public function test_salary_service_persists_branch_id_from_employee(): void
    {
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 5000000,
        ]);

        app(SalaryService::class)->generateMonthlyDrafts(
            $this->restaurant->id,
            today()->format('Y-m'),
        );

        $this->assertDatabaseHas('salaries', [
            'employee_id' => $employee->id,
            'branch_id' => $this->branchA->id,
        ]);
    }

    public function test_schedule_index_only_shows_active_branch_assignments(): void
    {
        $this->restaurant->plan->update(['features' => array_merge($this->restaurant->plan->features ?? [], ['hr_timekeeping' => true])]);

        $empA = Employee::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchA->id, 'full_name' => 'Nhân viên ca A']);
        $empB = Employee::factory()->create(['restaurant_id' => $this->restaurant->id, 'branch_id' => $this->branchB->id, 'full_name' => 'Nhân viên ca B']);
        $shift = WorkShift::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'employee_id' => $empA->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today(),
            'status' => 'scheduled',
        ]);
        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchB->id,
            'employee_id' => $empB->id,
            'shift_id' => $shift->id,
            'scheduled_date' => today(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->owner);
        $this->switchToBranch($this->branchA);

        $response = $this->get(route('schedules.index'));
        $response->assertOk();

        $assignments = $response->original->getData()['page']['props']['assignments'];
        $names = collect($assignments)->pluck('employee_name')->all();

        $this->assertContains('Nhân viên ca A', $names);
        $this->assertNotContains('Nhân viên ca B', $names, 'Lịch trực chi nhánh B không được xuất hiện khi đang xem chi nhánh A.');
    }

    public function test_manager_cannot_switch_to_a_branch_they_are_not_assigned_to(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'user_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        $this->post(route('branch.switch'), ['branch_id' => $this->branchA->id])->assertRedirect();

        $response = $this->post(route('branch.switch'), ['branch_id' => $this->branchB->id]);
        $response->assertForbidden();
    }

    public function test_owner_can_switch_to_any_branch(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('branch.switch'), ['branch_id' => $this->branchA->id])->assertRedirect();
        $this->post(route('branch.switch'), ['branch_id' => $this->branchB->id])->assertRedirect();
    }

    public function test_owner_can_switch_to_all_branches(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('branch.switch'), ['branch_id' => $this->branchA->id])->assertRedirect();
        $this->post(route('branch.switch'), ['scope' => 'all'])->assertRedirect();

        $this->assertFalse(session()->has('active_branch_id'));
        $this->assertSame('all', session('active_branch_scope'));
    }

    public function test_manager_cannot_switch_to_all_branches(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'user_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        $response = $this->post(route('branch.switch'), ['scope' => 'all']);
        $response->assertForbidden();
    }

    public function test_manager_session_cannot_override_assigned_branch(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branchA->id,
            'user_id' => $manager->id,
        ]);

        $response = $this->withSession(['active_branch_id' => $this->branchB->id])
            ->actingAs($manager)
            ->get(route('orders.index'));

        $response->assertOk();
        $this->assertSame($this->branchA->id, session('active_branch_id'));
    }

    public function test_unassigned_manager_is_denied_tenant_pages(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $manager->assignRole($managerRole);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
