<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\FinancialBankAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Nửa "sao kê" của quy trình đối soát ngân hàng.
 *
 * Bốn thao tác thêm tài khoản / nhập sao kê / đồng bộ SePay / khớp dòng đã có
 * đủ ở backend từ đầu nhưng không có nút nào gọi tới, nên cả quy trình không
 * dùng được. Bộ test này khoá lại đường đi hoàn chỉnh sau khi nối giao diện.
 */
class BankStatementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->owner->assignRole('owner');
    }

    private function makeAccount(): FinancialBankAccount
    {
        return FinancialBankAccount::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'VCB kinh doanh',
            'bank_name' => 'Vietcombank',
            'account_number' => '0071000123456',
            'account_type' => 'bank',
            'financial_account_code' => '1121',
            'is_active' => true,
        ]);
    }

    private function makePaidPayment(float $amount): Payment
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        return Payment::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function test_the_page_now_carries_the_statement_side_of_the_workflow(): void
    {
        $this->makeAccount();

        $this->actingAs($this->owner)
            ->get(route('bank-reconciliation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bank-reconciliation/Index')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'VCB kinh doanh')
                // Số tài khoản phải được che bớt khi đẩy ra giao diện.
                ->where('accounts.0.account_number_masked', fn ($v) => $v !== '0071000123456')
                ->has('statementLines.lines')
                ->where('statementLines.unmatched_count', 0)
            );
    }

    public function test_owner_can_add_a_payment_account(): void
    {
        $this->actingAs($this->owner)
            ->post(route('bank-reconciliation.accounts.store'), [
                'name' => 'Ví MoMo cửa hàng',
                'account_type' => 'ewallet',
                'financial_account_code' => '1121',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('financial_bank_accounts', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ví MoMo cửa hàng',
            'account_type' => 'ewallet',
        ]);
    }

    public function test_owner_can_import_a_csv_statement(): void
    {
        $account = $this->makeAccount();

        $csv = "Ngày,Mô tả,Tiền vào,Tiền ra\n"
            ."2026-08-20,Thanh toan don ORD-1,250000,0\n"
            ."2026-08-21,Phi dich vu,0,11000\n";

        $this->actingAs($this->owner)
            ->post(route('bank-reconciliation.import'), [
                'financial_bank_account_id' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('sao-ke.csv', $csv),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, BankStatementLine::where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_importing_the_same_file_twice_does_not_duplicate_lines(): void
    {
        $account = $this->makeAccount();
        $csv = "Ngày,Mô tả,Tiền vào,Tiền ra\n2026-08-20,Thanh toan don ORD-1,250000,0\n";

        foreach (range(1, 2) as $_) {
            $this->actingAs($this->owner)
                ->post(route('bank-reconciliation.import'), [
                    'financial_bank_account_id' => $account->id,
                    'file' => UploadedFile::fake()->createWithContent('sao-ke.csv', $csv),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, BankStatementLine::where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_only_payments_with_the_exact_same_amount_are_offered_as_candidates(): void
    {
        $account = $this->makeAccount();
        $exact = $this->makePaidPayment(250000);
        $this->makePaidPayment(999000);        // lệch tiền -> không được đề xuất
        $reconciled = $this->makePaidPayment(250000);
        $reconciled->update(['reconciled_at' => now(), 'reconciled_by' => $this->owner->id]);

        BankStatementLine::create([
            'restaurant_id' => $this->restaurant->id,
            'financial_bank_account_id' => $account->id,
            'transaction_date' => '2026-08-20',
            'description' => 'Thanh toan don hang',
            'amount_in' => 250000,
            'amount_out' => 0,
            'status' => 'unmatched',
            'idempotency_key' => 'test-line-1',
        ]);

        $props = $this->actingAs($this->owner)
            ->get(route('bank-reconciliation.index'))
            ->viewData('page')['props'];

        $candidates = $props['statementLines']['lines'][0]['candidates'];

        // Đúng một ứng viên: khoản trùng tiền và chưa đối soát.
        $this->assertCount(1, $candidates);
        $this->assertSame($exact->id, $candidates[0]['id']);
    }

    public function test_matching_a_line_reconciles_the_payment(): void
    {
        $account = $this->makeAccount();
        $payment = $this->makePaidPayment(250000);

        $line = BankStatementLine::create([
            'restaurant_id' => $this->restaurant->id,
            'financial_bank_account_id' => $account->id,
            'transaction_date' => '2026-08-20',
            'description' => 'Thanh toan don hang',
            'amount_in' => 250000,
            'amount_out' => 0,
            'status' => 'unmatched',
            'idempotency_key' => 'test-line-2',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('bank-reconciliation.lines.match', $line), [
                'matched_type' => 'payment',
                'matched_id' => $payment->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('matched', $line->fresh()->status);
        $this->assertNotNull($payment->fresh()->reconciled_at);
    }

    public function test_an_outgoing_line_cannot_be_matched_to_an_order_payment(): void
    {
        $account = $this->makeAccount();
        $payment = $this->makePaidPayment(11000);

        $line = BankStatementLine::create([
            'restaurant_id' => $this->restaurant->id,
            'financial_bank_account_id' => $account->id,
            'transaction_date' => '2026-08-21',
            'description' => 'Phi dich vu',
            'amount_in' => 0,
            'amount_out' => 11000,
            'status' => 'unmatched',
            'idempotency_key' => 'test-line-3',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('bank-reconciliation.lines.match', $line), [
                'matched_type' => 'payment',
                'matched_id' => $payment->id,
            ])
            ->assertSessionHasErrors('matched_id');

        $this->assertSame('unmatched', $line->fresh()->status);
        // Giao diện cũng không đề xuất ứng viên cho dòng tiền ra.
        $props = $this->actingAs($this->owner)
            ->get(route('bank-reconciliation.index'))
            ->viewData('page')['props'];
        $this->assertSame([], $props['statementLines']['lines'][0]['candidates']);
    }
}
