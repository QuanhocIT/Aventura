<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\InventoryTransaction;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ScheduleAssignment;
use App\Models\ShiftClosing;
use App\Models\ViolationReport;
use App\Models\WorkShift;
use App\Models\User;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutoPayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Restaurant $restaurant;
    private RestaurantBranch $branch;
    private Role $ownerRole;
    private Role $cashierRole;
    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        $this->ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $this->kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        $this->owner->assignRole($this->ownerRole);
    }

    public function test_automated_payroll_calculates_salary_and_applies_progressive_deductions(): void
    {
        // 1. Setup Cashier and Chef
        $cashierUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $cashierUser->assignRole($this->cashierRole);

        $cashier = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $cashierUser->id,
            'role_id' => $this->cashierRole->id,
            'status' => 'active',
            'base_salary' => 8000000,
        ]);

        $chefUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $chefUser->assignRole($this->kitchenRole);

        $chef = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $chefUser->id,
            'role_id' => $this->kitchenRole->id,
            'status' => 'active',
            'base_salary' => 12000000,
        ]);

        // 2. Mock Cash Register Shortage for Cashier (Negative cash difference of -150,000)
        $shift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Chieu',
            'code' => 'CA_CHIEU',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        ShiftClosing::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'shift_id' => $shift->id,
            'closing_date' => '2026-05-15',
            'cashier_user_id' => $cashierUser->id,
            'expected_cash' => 5000000,
            'actual_cash' => 4850000,
            'cash_difference' => -150000,
            'status' => 'confirmed',
        ]);

        // 3. Mock Disciplinary Violation for Chef (50,000 penalty)
        ViolationReport::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'reported_by' => $this->owner->id,
            'violation_type' => 'Di Tre',
            'severity' => 'low',
            'description' => 'Di tre 15 phut',
            'penalty_amount' => 50000,
            'occurred_at' => '2026-05-10 08:15:00',
            'status' => 'open',
        ]);

        // 4. Mock Inventory Waste for Chef resolved temporally via Scheduled Shift
        // Chef shift: 2026-05-20 08:00:00 - 16:00:00
        $morningShift = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Ca Sang',
            'code' => 'CA_SANG',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $chef->id,
            'shift_id' => $morningShift->id,
            'scheduled_date' => '2026-05-20',
            'status' => 'scheduled',
        ]);

        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Thit Bo',
            'sku' => 'BEEF',
            'average_cost' => 200000,
        ]);

        // Inventory waste happens at 2026-05-20 10:30:00 (during Chef shift)
        InventoryTransaction::create([
            'restaurant_id' => $this->restaurant->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'waste',
            'direction' => 'out',
            'quantity' => 1.5,
            'unit_cost' => 200000,
            'total_cost' => 300000, // Loss is 300,000
            'occurred_at' => '2026-05-20 10:30:00',
            'notes' => 'Lam hong thit bo',
        ]);

        // 5. Generate monthly payroll drafts for 2026-05
        $service = app(SalaryService::class);
        $result = $service->generateMonthlyDrafts($this->restaurant->id, '2026-05');

        $this->assertSame(2, $result['created']);

        // 6. Verify Cashier salary details
        $cashierSalary = Salary::where('employee_id', $cashier->id)->first();

        $this->assertNotNull($cashierSalary);
        $this->assertEquals('2026-05-01', $cashierSalary->pay_period_start->toDateString());
        $this->assertEquals(8000000, (float) $cashierSalary->base_salary);
        $this->assertEquals(150000, (float) $cashierSalary->deduction_amount);
        $this->assertEquals(7850000, (float) $cashierSalary->net_salary);

        $shortageAdj = SalaryAdjustment::where('salary_id', $cashierSalary->id)
            ->where('type', 'cash_shortage')
            ->first();
        $this->assertNotNull($shortageAdj);
        $this->assertEquals(150000, (float) $shortageAdj->amount);

        // 7. Verify Chef salary details
        $chefSalary = Salary::where('employee_id', $chef->id)->first();

        $this->assertNotNull($chefSalary);
        $this->assertEquals('2026-05-01', $chefSalary->pay_period_start->toDateString());
        $this->assertEquals(12000000, (float) $chefSalary->base_salary);
        // Total deductions: 50,000 (violation) + 300,000 (inventory loss) = 350,000
        $this->assertEquals(350000, (float) $chefSalary->deduction_amount);
        $this->assertEquals(11650000, (float) $chefSalary->net_salary);

        $violationAdj = SalaryAdjustment::where('salary_id', $chefSalary->id)
            ->where('type', 'violation')
            ->first();
        $this->assertNotNull($violationAdj);
        $this->assertEquals(50000, (float) $violationAdj->amount);

        $lossAdj = SalaryAdjustment::where('salary_id', $chefSalary->id)
            ->where('type', 'inventory_loss')
            ->first();
        $this->assertNotNull($lossAdj);
        $this->assertEquals(300000, (float) $lossAdj->amount);
    }
}
