<?php

namespace Tests\Feature;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\WageTier;
use App\Services\PayrollBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quỹ lương theo chi nhánh + bậc lương (module mới). Kiểm tra: chỉ chủ quản lý được,
 * đặt quỹ/bậc lương, và service tính đã-cam-kết / còn-lại / chặn vượt quỹ.
 */
class PayrollBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_page_renders_for_owner(): void
    {
        $this->actingAs($this->owner)->get('/payroll-budget')->assertOk();
    }

    public function test_manager_cannot_manage_payroll_budget(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $manager->assignRole('manager');

        $this->actingAs($manager)->get('/payroll-budget')->assertForbidden();
        $this->actingAs($manager)->post('/payroll-budget/budget', [
            'branch_id' => $this->branch->id, 'budget_amount' => 1000000,
        ])->assertForbidden();
    }

    public function test_owner_can_set_budget_and_wage_tier(): void
    {
        $this->actingAs($this->owner)->post('/payroll-budget/budget', [
            'branch_id' => $this->branch->id,
            'budget_amount' => 50000000,
        ])->assertRedirect();

        $budget = BranchPayrollBudget::where('branch_id', $this->branch->id)->first();
        $this->assertNotNull($budget, 'Quỹ lương chưa được lưu.');
        $this->assertEquals(50000000, (float) $budget->budget_amount);
        $this->assertEquals(Carbon::now()->startOfMonth()->toDateString(), $budget->effective_month->toDateString());

        $this->actingAs($this->owner)->post('/payroll-budget/wage-tiers', [
            'name' => 'Phục vụ ca ngày',
            'compensation_type' => 'shift',
            'rate' => 250000,
        ])->assertRedirect();

        $this->assertDatabaseHas('wage_tiers', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phục vụ ca ngày',
            'compensation_type' => 'shift',
        ]);
    }

    public function test_owner_can_edit_pause_and_reactivate_wage_tier(): void
    {
        $tier = WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Phục vụ cũ',
            'compensation_type' => 'fixed',
            'rate' => 7000000,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->put("/payroll-budget/wage-tiers/{$tier->id}", [
            'name' => 'Phục vụ ca ngày',
            'compensation_type' => 'shift',
            'rate' => 250000,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('wage_tiers', [
            'id' => $tier->id,
            'name' => 'Phục vụ ca ngày',
            'compensation_type' => 'shift',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->owner)->patch("/payroll-budget/wage-tiers/{$tier->id}/toggle")
            ->assertRedirect();
        $this->assertDatabaseHas('wage_tiers', ['id' => $tier->id, 'is_active' => false]);

        $this->actingAs($this->owner)->patch("/payroll-budget/wage-tiers/{$tier->id}/toggle")
            ->assertRedirect();
        $this->assertDatabaseHas('wage_tiers', ['id' => $tier->id, 'is_active' => true]);
    }

    public function test_manager_cannot_edit_wage_tier(): void
    {
        $manager = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $manager->assignRole('manager');
        $tier = WageTier::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Bậc được bảo vệ',
            'compensation_type' => 'fixed',
            'rate' => 7000000,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->put("/payroll-budget/wage-tiers/{$tier->id}", [
            'name' => 'Bậc bị sửa',
            'compensation_type' => 'fixed',
            'rate' => 1,
        ])->assertForbidden();
        $this->actingAs($manager)->patch("/payroll-budget/wage-tiers/{$tier->id}/toggle")
            ->assertForbidden();
    }

    public function test_employee_page_exposes_budget_to_branch_manager(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'manage_employees', 'guard_name' => 'web']);
        $managerRole = Role::findByName('manager', 'web');
        $managerRole->givePermissionTo($permission);
        $manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $manager->assignRole($managerRole);

        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'effective_month' => Carbon::now()->startOfMonth(),
            'budget_amount' => 20000000,
        ]);
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'base_salary' => 8000000,
            'compensation_type' => 'fixed',
        ]);

        $response = $this->actingAs($manager)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->get('/employees');

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];
        $budget = $props['payrollBudget'];
        $this->assertSame($this->branch->id, (int) $budget['branch_id']);
        $this->assertTrue($budget['configured']);
        $this->assertEqualsWithDelta(20000000, (float) $budget['budget_amount'], 0.01);
        $this->assertEqualsWithDelta(12000000, (float) $budget['remaining'], 0.01);
    }

    public function test_owner_cannot_lower_budget_below_active_payroll(): void
    {
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'base_salary' => 8000000,
            'compensation_type' => 'fixed',
        ]);

        $response = $this->actingAs($this->owner)->from('/payroll-budget')->post('/payroll-budget/budget', [
            'branch_id' => $this->branch->id,
            'budget_amount' => 7000000,
        ]);

        $response->assertRedirect('/payroll-budget')->assertSessionHasErrors('budget_amount');
        $this->assertDatabaseCount('branch_payroll_budgets', 0);
    }

    public function test_service_computes_committed_and_blocks_over_budget(): void
    {
        BranchPayrollBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'effective_month' => Carbon::now()->startOfMonth(),
            'budget_amount' => 20000000, // 20 triệu
        ]);

        // 2 nhân viên lương tháng 8tr → cam kết 16tr
        Employee::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
            'compensation_type' => 'fixed',
            'base_salary' => 8000000,
        ]);

        $svc = app(PayrollBudgetService::class);

        $this->assertEqualsWithDelta(16000000, $svc->committedMonthlyWages($this->restaurant->id, $this->branch->id), 1);
        $this->assertEqualsWithDelta(4000000, $svc->remaining($this->restaurant->id, $this->branch->id), 1);

        // Thêm 4tr → vừa đủ (còn 4tr) → OK
        $this->assertTrue($svc->canFit($this->restaurant->id, $this->branch->id, 4000000));
        // Thêm 5tr → vượt quỹ → chặn
        $this->assertFalse($svc->canFit($this->restaurant->id, $this->branch->id, 5000000));
    }

    public function test_no_budget_means_no_constraint(): void
    {
        $svc = app(PayrollBudgetService::class);
        // Chưa đặt quỹ → remaining null, canFit luôn true
        $this->assertNull($svc->remaining($this->restaurant->id, $this->branch->id));
        $this->assertTrue($svc->canFit($this->restaurant->id, $this->branch->id, 999999999));
    }

    public function test_wage_tier_monthly_conversion(): void
    {
        $hourly = new WageTier(['compensation_type' => 'hourly', 'rate' => 30000]);
        $shift = new WageTier(['compensation_type' => 'shift', 'rate' => 250000]);
        $monthly = new WageTier(['compensation_type' => 'fixed', 'rate' => 8000000]);

        $this->assertEqualsWithDelta(30000 * WageTier::HOURS_PER_MONTH, $hourly->estimatedMonthly(), 1);
        $this->assertEqualsWithDelta(250000 * WageTier::SHIFTS_PER_MONTH, $shift->estimatedMonthly(), 1);
        $this->assertEqualsWithDelta(8000000, $monthly->estimatedMonthly(), 1);
    }
}
