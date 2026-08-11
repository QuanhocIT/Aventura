<?php

namespace Tests\Feature;

use App\Models\BranchExpenseBudget;
use App\Models\OperatingExpense;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\ExpenseBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hạn mức chi tiêu chi nhánh: Chủ đặt hạn mức; Quản lý ghi chi phí BẮT BUỘC hoá đơn và
 * KHÔNG được vượt hạn mức; Chủ được phép vượt. Kèm kiểm tra service tính toán.
 */
class ExpenseBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $manager;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);

        // Quản lý gắn chi nhánh → activeBranchId() = branch trong request.
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->manager->assignRole($managerRole);
    }

    private function setBudget(float $amount, bool $requireReceipt = true): BranchExpenseBudget
    {
        return BranchExpenseBudget::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'effective_month' => Carbon::now()->startOfMonth(),
            'budget_amount' => $amount,
            'require_receipt' => $requireReceipt,
        ]);
    }

    public function test_owner_can_set_branch_budget(): void
    {
        $this->actingAs($this->owner)->post('/expenses/branch-budget', [
            'branch_id' => $this->branch->id,
            'budget_amount' => 5000000,
            'require_receipt' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branch_expense_budgets', [
            'branch_id' => $this->branch->id,
            'require_receipt' => true,
        ]);
    }

    public function test_manager_cannot_set_branch_budget(): void
    {
        $this->actingAs($this->manager)->post('/expenses/branch-budget', [
            'branch_id' => $this->branch->id,
            'budget_amount' => 5000000,
        ])->assertForbidden();
    }

    public function test_manager_over_budget_is_blocked(): void
    {
        $this->setBudget(1000000);

        $this->actingAs($this->manager)->from('/expenses')->post('/expenses', [
            'amount' => 2000000,
            'expense_date' => Carbon::now()->toDateString(),
            'description' => 'Mua thiết bị vượt hạn mức',
            'invoice' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect('/expenses')->assertSessionHasErrors(['amount']);

        $this->assertDatabaseCount('operating_expenses', 0);
    }

    public function test_manager_must_attach_receipt_when_required(): void
    {
        $this->setBudget(5000000, requireReceipt: true);

        $this->actingAs($this->manager)->from('/expenses')->post('/expenses', [
            'amount' => 100000,
            'expense_date' => Carbon::now()->toDateString(),
            'description' => 'Chi lặt vặt không hoá đơn',
        ])->assertRedirect('/expenses')->assertSessionHasErrors(['invoice']);

        $this->assertDatabaseCount('operating_expenses', 0);
    }

    public function test_manager_within_budget_with_receipt_succeeds(): void
    {
        $this->setBudget(5000000, requireReceipt: true);

        $this->actingAs($this->manager)->from('/expenses')->post('/expenses', [
            'amount' => 100000,
            'expense_date' => Carbon::now()->toDateString(),
            'description' => 'Chi hợp lệ có hoá đơn',
            'invoice' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect('/expenses')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('operating_expenses', 1);
    }

    public function test_owner_can_exceed_budget(): void
    {
        $this->setBudget(1000000);

        // Chủ chọn chi nhánh qua session → chi vượt hạn mức vẫn được ghi.
        $this->actingAs($this->owner)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->from('/expenses')
            ->post('/expenses', [
                'amount' => 9000000,
                'expense_date' => Carbon::now()->toDateString(),
                'description' => 'Chủ chi khoản lớn vượt hạn mức (được phép)',
            ])->assertRedirect('/expenses')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('operating_expenses', 1);
    }

    public function test_service_computes_committed_and_remaining(): void
    {
        $this->setBudget(3000000);
        OperatingExpense::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'amount' => 1200000,
            'expense_date' => Carbon::now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);

        $svc = app(ExpenseBudgetService::class);
        $this->assertEqualsWithDelta(1200000, $svc->committedThisMonth($this->restaurant->id, $this->branch->id), 1);
        $this->assertEqualsWithDelta(1800000, $svc->remaining($this->restaurant->id, $this->branch->id), 1);
        $this->assertTrue($svc->canFit($this->restaurant->id, $this->branch->id, 1800000));
        $this->assertFalse($svc->canFit($this->restaurant->id, $this->branch->id, 1800001));
    }
}
