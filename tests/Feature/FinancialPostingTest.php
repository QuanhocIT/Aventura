<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Services\FinancialPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class FinancialPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_is_balanced_and_idempotent(): void
    {
        $restaurant = Restaurant::factory()->create();
        $service = app(FinancialPostingService::class);

        $first = $service->post([
            'restaurant_id' => $restaurant->id,
            'entry_date' => today(),
            'source_type' => 'test',
            'source_id' => 10,
            'idempotency_key' => 'test:balanced:10',
            'description' => 'Test bút toán',
            'lines' => [
                ['account' => '1111', 'debit' => 100000, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $second = $service->post([
            'restaurant_id' => $restaurant->id,
            'entry_date' => today(),
            'source_type' => 'test',
            'source_id' => 10,
            'idempotency_key' => 'test:balanced:10',
            'description' => 'Should not duplicate',
            'lines' => [
                ['account' => '1111', 'debit' => 100000, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('financial_journal_entries', 1);
        $this->assertDatabaseCount('financial_journal_lines', 2);
        $this->assertEquals(100000, (float) $first->total_debit);
        $this->assertEquals(100000, (float) $first->total_credit);
    }

    public function test_closed_period_rejects_new_posting(): void
    {
        $restaurant = Restaurant::factory()->create();
        $service = app(FinancialPostingService::class);
        $period = AccountingPeriod::create([
            'restaurant_id' => $restaurant->id,
            'period_start' => Carbon::now()->startOfMonth(),
            'period_end' => Carbon::now()->endOfMonth(),
            'status' => 'open',
        ]);

        $service->closePeriod($period);

        $this->expectException(RuntimeException::class);
        $service->post([
            'restaurant_id' => $restaurant->id,
            'entry_date' => today(),
            'idempotency_key' => 'test:closed-period',
            'lines' => [
                ['account' => '1111', 'debit' => 1, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => 1],
            ],
        ]);
    }

    public function test_cash_payment_and_refund_are_each_posted_once(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        CashRegister::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'closing_date' => today(),
            'opening_balance' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 150000,
        ]);

        $paid = Payment::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'status' => 'paid',
            'amount' => 150000,
            'paid_at' => now(),
        ]);

        // Re-saving the same paid payment must not create a second posting.
        $paid->touch();

        Payment::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'status' => 'refunded',
            'amount' => 150000,
            'paid_at' => now(),
        ]);

        $this->assertSame(2, FinancialJournalEntry::withoutGlobalScopes()->count());
        $this->assertSame(2, CashTransaction::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('cash_transactions', ['payment_id' => $paid->id, 'type' => 'in']);
        $this->assertDatabaseHas('cash_transactions', ['type' => 'out', 'source' => 'refund']);
    }

    public function test_reversal_moves_to_the_next_open_period_when_current_period_is_closed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $service = app(FinancialPostingService::class);
        $entry = $service->post([
            'restaurant_id' => $restaurant->id,
            'entry_date' => today(),
            'idempotency_key' => 'test:reversal:1',
            'description' => 'Bút toán cần điều chỉnh',
            'lines' => [
                ['account' => '1111', 'debit' => 50000, 'credit' => 0],
                ['account' => '5111', 'debit' => 0, 'credit' => 50000],
            ],
        ]);
        $period = AccountingPeriod::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->whereDate('period_start', today()->startOfMonth()->toDateString())
            ->firstOrFail();
        $service->closePeriod($period);

        $reversal = $service->reverse($entry, null, 'Điều chỉnh kiểm thử');

        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertTrue($reversal->entry_date->isSameMonth(today()->addMonth()));
        $this->assertDatabaseCount('financial_journal_entries', 2);
    }

    /**
     * Chốt chặn cho lỗi từng khiến 9 call site ghi bút toán hỏng ngầm: các nơi
     * gọi truyền 'account_id' thay vì 'account', và service chỉ vỡ ở tận
     * accountFor() dưới dạng PHP notice → HTTP 500 không rõ nguyên nhân.
     */
    public function test_line_without_account_key_is_rejected_before_anything_is_written(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('account_id');

        try {
            app(FinancialPostingService::class)->post([
                'restaurant_id' => $restaurant->id,
                'entry_date' => today(),
                'source_type' => 'test',
                'source_id' => 99,
                'description' => 'Payload sai tên khóa',
                'lines' => [
                    ['account' => '1111', 'debit' => 100000, 'credit' => 0],
                    ['account_id' => '5111', 'debit' => 0, 'credit' => 100000],
                ],
            ]);
        } finally {
            // Không được để lại bút toán dở dang khi payload bị từ chối.
            $this->assertDatabaseCount('financial_journal_entries', 0);
            $this->assertDatabaseCount('financial_journal_lines', 0);
        }
    }
}
