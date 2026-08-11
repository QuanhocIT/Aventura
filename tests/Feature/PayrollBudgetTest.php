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
