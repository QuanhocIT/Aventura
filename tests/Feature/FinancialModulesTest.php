<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\FinancialBankAccount;
use App\Models\FinancialBudget;
use App\Models\FinancialBudgetLine;
use App\Models\FinancialJournalEntry;
use App\Models\FixedAsset;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\OperatingExpense;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\Restaurant;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinancialBudgetService;
use App\Services\FinancialPostingService;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_statement_import_is_idempotent_and_can_match_a_journal_entry(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');
        $account = FinancialBankAccount::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Tài khoản vận hành',
            'bank_name' => 'Ngân hàng kiểm thử',
            'account_type' => 'bank',
            'financial_account_code' => '1121',
        ]);
        $entry = app(FinancialPostingService::class)->post([
            'restaurant_id' => $restaurant->id,
            'entry_date' => today(),
            'source_type' => 'bank-reconciliation-test',
            'source_id' => 1,
            'idempotency_key' => 'bank-reconciliation-test:1',
            'lines' => [
                ['account' => '1121', 'debit' => 250000, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => 250000],
            ],
        ]);
        $payload = [
            'financial_bank_account_id' => $account->id,
            'lines' => [[
                'transaction_date' => today()->toDateString(),
                'external_reference' => 'BANK-001',
                'description' => 'Tiền bán hàng',
                'amount_in' => 250000,
                'amount_out' => 0,
            ]],
        ];

        $this->actingAs($owner)->post(route('bank-reconciliation.import'), $payload)->assertRedirect();
        $csv = UploadedFile::fake()->createWithContent(
            'statement.csv',
            "transaction_date,external_reference,description,amount_in,amount_out,balance\n"
                .today()->toDateString().",BANK-001,Tiền bán hàng,250000,0,250000\n",
        );
        $this->actingAs($owner)->post(route('bank-reconciliation.import'), [
            'financial_bank_account_id' => $account->id,
            'file' => $csv,
        ])->assertRedirect();

        $this->assertDatabaseCount('bank_statement_lines', 1);
        $line = BankStatementLine::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('bank-reconciliation.lines.match', $line), [
                'matched_type' => 'financial_journal_entry',
                'matched_id' => $entry->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_statement_lines', [
            'id' => $line->id,
            'status' => 'matched',
            'matched_type' => FinancialJournalEntry::class,
            'matched_id' => $entry->id,
        ]);
    }

    public function test_finance_page_seeds_the_default_chart_and_allows_a_custom_account(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)->get(route('finance.index'))->assertOk();
        $this->assertDatabaseHas('financial_accounts', [
            'restaurant_id' => $restaurant->id,
            'code' => '1111',
            'is_system' => 1,
        ]);

        $this->actingAs($owner)
            ->post(route('finance.accounts.store'), [
                'code' => '6111',
                'name' => 'Chi phí thử nghiệm',
                'type' => 'expense',
                'normal_balance' => 'debit',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('financial_accounts', [
            'restaurant_id' => $restaurant->id,
            'code' => '6111',
            'is_system' => 0,
        ]);
    }

    public function test_financial_budget_reports_actuals_against_a_budget_line(): void
    {
        $restaurant = Restaurant::factory()->create();
        $expense = OperatingExpense::create([
            'restaurant_id' => $restaurant->id,
            'amount' => 175000,
            'expense_date' => today()->toDateString(),
            'status' => 'approved',
            'description' => 'Chi phí kiểm thử ngân sách',
        ]);
        $budget = FinancialBudget::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ngân sách vận hành tháng này',
            'period_start' => today()->startOfMonth()->toDateString(),
            'period_end' => today()->endOfMonth()->toDateString(),
            'status' => 'approved',
            'total_amount' => 300000,
        ]);
        $line = FinancialBudgetLine::create([
            'restaurant_id' => $restaurant->id,
            'financial_budget_id' => $budget->id,
            'period_month' => today()->startOfMonth()->toDateString(),
            'account_code' => '6271',
            'budget_amount' => 300000,
        ]);

        $serialized = app(FinancialBudgetService::class)->serialize($budget);

        $this->assertSame($expense->restaurant_id, $line->restaurant_id);
        $this->assertEquals(175000, $serialized['lines'][0]['actual_amount']);
        $this->assertEquals(125000, $serialized['lines'][0]['variance_amount']);
    }

    public function test_financial_budget_supports_demo_material_account_alias_and_explains_its_actual_basis(): void
    {
        $restaurant = Restaurant::factory()->create();
        $unit = Unit::factory()->create(['restaurant_id' => $restaurant->id]);
        $ingredient = Ingredient::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'unit_id' => $unit->id,
        ]);
        InventoryTransaction::create([
            'restaurant_id' => $restaurant->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'usage',
            'direction' => 'out',
            'quantity' => 2,
            'unit_cost' => 225000,
            'total_cost' => 450000,
            'occurred_at' => today()->startOfMonth()->addDays(3)->setTime(10, 0),
        ]);
        $budget = FinancialBudget::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Ngân sách nguyên liệu tháng này',
            'period_start' => today()->startOfMonth()->toDateString(),
            'period_end' => today()->endOfMonth()->toDateString(),
            'status' => 'approved',
            'total_amount' => 500000,
        ]);
        FinancialBudgetLine::create([
            'restaurant_id' => $restaurant->id,
            'financial_budget_id' => $budget->id,
            'period_month' => today()->startOfMonth()->toDateString(),
            'account_code' => '6321',
            'budget_amount' => 500000,
        ]);

        $serialized = app(FinancialBudgetService::class)->serialize($budget);

        $this->assertEquals(450000, $serialized['lines'][0]['actual_amount']);
        $this->assertSame('Chi phí nguyên liệu trực tiếp', $serialized['lines'][0]['account_name']);
        $this->assertStringContainsString('không phải tiền mua nhập kho', $serialized['lines'][0]['actual_basis']);
    }

    public function test_fixed_asset_depreciation_is_idempotent_and_posts_the_correct_entry(): void
    {
        $restaurant = Restaurant::factory()->create();
        $asset = FixedAsset::create([
            'restaurant_id' => $restaurant->id,
            'asset_code' => 'FA-TEST-001',
            'name' => 'Thiết bị kiểm thử',
            'purchase_date' => today()->startOfMonth()->toDateString(),
            'in_service_date' => today()->startOfMonth()->toDateString(),
            'cost' => 12000000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'status' => 'active',
        ]);

        $service = app(FixedAssetService::class);
        $period = Carbon::today()->format('Y-m');
        $first = $service->depreciate($asset, $period);
        $second = $service->depreciate($asset, $period);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('fixed_asset_depreciations', 1);
        $this->assertDatabaseHas('fixed_asset_depreciations', [
            'id' => $first->id,
            'amount' => 1000000,
        ]);
        $this->assertDatabaseHas('financial_journal_lines', [
            'financial_account_id' => $this->accountId($restaurant->id, '6272'),
            'debit' => 1000000,
        ]);
        $this->assertEquals(1000000, (float) $asset->refresh()->accumulated_depreciation);
    }

    public function test_marking_an_approved_salary_paid_creates_a_payment_audit_and_bank_posting(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'user_id' => null,
        ]);
        $salary = Salary::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => null,
            'employee_id' => $employee->id,
            'pay_period_start' => today()->startOfMonth()->toDateString(),
            'pay_period_end' => today()->endOfMonth()->toDateString(),
            'base_salary' => 8000000,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_salary' => 8000000,
            'status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->patch(route('salaries.paid', $salary), [
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'BANK-PAYROLL-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('salaries', ['id' => $salary->id, 'status' => 'paid']);
        $this->assertDatabaseHas('salary_payments', [
            'salary_id' => $salary->id,
            'amount' => 8000000,
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BANK-PAYROLL-001',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'source_id' => $salary->id,
            'total_debit' => 8000000,
            'total_credit' => 8000000,
        ]);
    }

    public function test_cash_reversal_creates_a_matching_cash_and_journal_reversal(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');
        $register = CashRegister::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'closing_date' => today(),
            'opening_balance' => 0,
            'expected_closing_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $original = app(\App\Services\CashPostingService::class)->record([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'cash_register_id' => $register->id,
            'type' => 'out',
            'amount' => 50000,
            'source' => 'expense',
            'idempotency_key' => 'cash-reversal-test:original',
            'debit_account' => '6271',
            'credit_account' => '1111',
            'notes' => 'Chi kiểm thử',
        ]);

        $this->actingAs($owner)
            ->post(route('cash-flow.transactions.reversal', $original), [
                'reason' => 'Ghi nhầm khoản chi kiểm thử và cần hoàn tác.',
            ])
            ->assertRedirect();

        $reversal = CashTransaction::withoutGlobalScopes()
            ->where('reversal_of_id', $original->id)
            ->firstOrFail();
        $this->assertSame('in', $reversal->type);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseCount('financial_journal_entries', 2);
    }

    public function test_operating_expense_requires_approval_before_cash_payment(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = \App\Models\RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');
        CashRegister::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'closing_date' => today(),
            'opening_balance' => 0,
            'expected_closing_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $expense = OperatingExpense::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'amount' => 90000,
            'expense_date' => today()->toDateString(),
            'status' => 'draft',
            'description' => 'Chi phí cần duyệt',
        ]);

        $this->actingAs($owner)->patch(route('expenses.approve', $expense), [])->assertRedirect();
        $this->actingAs($owner)->patch(route('expenses.pay', $expense), ['payment_method' => 'cash'])->assertRedirect();

        $this->assertDatabaseHas('operating_expenses', ['id' => $expense->id, 'status' => 'paid']);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'out', 'amount' => 90000, 'source' => 'expense']);
        $this->assertDatabaseHas('financial_journal_entries', ['source_id' => $expense->id, 'total_debit' => 90000]);
    }

    private function accountId(int $restaurantId, string $code): int
    {
        return (int) \App\Models\FinancialAccount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('code', $code)
            ->value('id');
    }
}
