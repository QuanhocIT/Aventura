<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\BankStatementLine;
use App\Models\Customer;
use App\Models\FinancialBankAccount;
use App\Models\FixedAsset;
use App\Models\OperatingExpense;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkflowCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_close_is_checked_and_reopen_is_audited(): void
    {
        [$restaurant, $branch, $owner] = $this->tenant();
        $period = AccountingPeriod::create([
            'restaurant_id' => $restaurant->id,
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'status' => 'open',
        ]);
        $expense = OperatingExpense::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'amount' => 100000,
            'expense_date' => today(),
            'status' => 'draft',
            'description' => 'Chứng từ chờ duyệt',
        ]);

        $this->actingAs($owner)
            ->patch(route('finance.periods.close', $period))
            ->assertSessionHasErrors('close_checklist');
        $this->assertDatabaseHas('accounting_periods', ['id' => $period->id, 'status' => 'open']);

        $expense->update(['status' => 'approved']);
        $this->actingAs($owner)
            ->patch(route('finance.periods.close', $period))
            ->assertRedirect();
        $this->assertDatabaseHas('accounting_periods', [
            'id' => $period->id,
            'status' => 'closed',
        ]);

        $this->actingAs($owner)
            ->patch(route('finance.periods.reopen', $period), ['reason' => 'Bổ sung chứng từ cuối kỳ'])
            ->assertRedirect();
        $this->assertDatabaseHas('accounting_periods', [
            'id' => $period->id,
            'status' => 'open',
            'reopened_by' => $owner->id,
            'reopen_reason' => 'Bổ sung chứng từ cuối kỳ',
        ]);
    }

    public function test_fixed_asset_credit_creates_payable_and_disposal_posts_the_full_entry(): void
    {
        [$restaurant, $branch, $owner] = $this->tenant();

        $this->actingAs($owner)
            ->post(route('fixed-assets.store'), [
                'asset_code' => 'FA-WORKFLOW-001',
                'name' => 'Thiết bị thanh lý kiểm thử',
                'branch_id' => $branch->id,
                'purchase_date' => today()->toDateString(),
                'cost' => 12000000,
                'payment_method' => 'credit',
                'useful_life_months' => 12,
            ])
            ->assertRedirect();

        $asset = FixedAsset::withoutGlobalScopes()->where('asset_code', 'FA-WORKFLOW-001')->firstOrFail();
        $this->assertDatabaseHas('account_payables', [
            'fixed_asset_id' => $asset->id,
            'amount' => 12000000,
            'status' => 'unpaid',
        ]);

        $this->actingAs($owner)
            ->post(route('fixed-assets.dispose', $asset), [
                'disposed_at' => today()->toDateString(),
                'disposal_proceeds' => 1500000,
                'payment_method' => 'bank_transfer',
                'reason' => 'Thiết bị hỏng, thay mới',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixed_assets', [
            'id' => $asset->id,
            'status' => 'disposed',
            'disposal_proceeds' => 1500000,
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'source_id' => $asset->id,
            'total_debit' => 12000000,
            'total_credit' => 12000000,
        ]);
    }

    public function test_bank_statement_adjustment_can_be_created_and_unmatched(): void
    {
        [$restaurant, $branch, $owner] = $this->tenant();
        $bank = FinancialBankAccount::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Tài khoản kiểm thử',
            'account_type' => 'bank',
            'financial_account_code' => '1121',
        ]);
        $line = BankStatementLine::create([
            'restaurant_id' => $restaurant->id,
            'financial_bank_account_id' => $bank->id,
            'transaction_date' => today(),
            'amount_in' => 250000,
            'amount_out' => 0,
            'idempotency_key' => 'workflow-bank-line-001',
            'status' => 'unmatched',
        ]);

        $this->actingAs($owner)
            ->post(route('bank-reconciliation.lines.adjustment', $line), [
                'offset_account' => '7111',
                'description' => 'Khoản thu khác từ sao kê',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('bank_statement_lines', ['id' => $line->id, 'status' => 'matched']);

        $this->actingAs($owner)
            ->patch(route('bank-reconciliation.lines.unmatch', $line), ['reason' => 'Cần phân loại lại'])
            ->assertRedirect();
        $this->assertDatabaseHas('bank_statement_lines', [
            'id' => $line->id,
            'status' => 'unmatched',
            'unmatched_reason' => 'Cần phân loại lại',
        ]);
    }

    public function test_owner_can_write_off_payable_and_receivable_with_journals(): void
    {
        [$restaurant, $branch, $owner] = $this->tenant();
        $supplier = Supplier::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        $customer = Customer::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'current_debt' => 300000,
        ]);
        $payable = AccountPayable::create([
            'restaurant_id' => $restaurant->id,
            'supplier_id' => $supplier->id,
            'amount' => 400000,
            'paid_amount' => 0,
            'due_date' => today(),
            'status' => 'unpaid',
        ]);
        $receivable = AccountReceivable::create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'amount' => 300000,
            'received_amount' => 0,
            'due_date' => today(),
            'status' => 'unpaid',
        ]);

        $this->actingAs($owner)
            ->post(route('debts.payables.write-off', $payable), ['reason' => 'Nhà cung cấp xác nhận miễn nợ'])
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('debts.receivables.write-off', $receivable), ['reason' => 'Khách hàng không còn khả năng thanh toán'])
            ->assertRedirect();

        $this->assertDatabaseHas('account_payables', ['id' => $payable->id, 'status' => 'written_off']);
        $this->assertDatabaseHas('account_receivables', ['id' => $receivable->id, 'status' => 'written_off']);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'current_debt' => 0]);
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2: User} */
    private function tenant(): array
    {
        $restaurant = Restaurant::factory()->create(['status' => 'active']);
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');

        return [$restaurant, $branch, $owner];
    }
}
