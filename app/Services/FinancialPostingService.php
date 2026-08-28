<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\BankStatementLine;
use App\Models\CashRegister;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FixedAsset;
use App\Models\OperatingExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for tenant financial postings.
 *
 * The service deliberately accepts account codes rather than raw account IDs
 * so business modules remain independent from a tenant's chart-of-accounts
 * row IDs. Every posted entry is balanced and is idempotent by source key.
 */
class FinancialPostingService
{
    /** @var array<string, array{name: string, type: string, normal_balance: string, is_system: bool}> */
    private const DEFAULT_ACCOUNTS = [
        '1111' => ['name' => 'Tiền mặt tại quỹ', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '1121' => ['name' => 'Tiền gửi ngân hàng', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '1122' => ['name' => 'Tiền chờ đối soát thẻ', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '1123' => ['name' => 'Tiền chờ đối soát ví điện tử', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '1311' => ['name' => 'Phải thu khách hàng', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '1521' => ['name' => 'Nguyên vật liệu tồn kho', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '2111' => ['name' => 'Tài sản cố định', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '2141' => ['name' => 'Hao mòn tài sản cố định', 'type' => 'asset', 'normal_balance' => 'credit', 'is_system' => true],
        '3311' => ['name' => 'Phải trả nhà cung cấp', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
        '1331' => ['name' => 'Thuế GTGT được khấu trừ', 'type' => 'asset', 'normal_balance' => 'debit', 'is_system' => true],
        '3331' => ['name' => 'Thuế phải nộp', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
        '3341' => ['name' => 'Phải trả người lao động', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
        '4111' => ['name' => 'Vốn chủ sở hữu', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],
        '5111' => ['name' => 'Doanh thu bán hàng', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
        '5112' => ['name' => 'Phí dịch vụ thu được', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
        '7111' => ['name' => 'Thu nhập khác', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
        '5211' => ['name' => 'Hàng bán bị trả lại/hoàn tiền', 'type' => 'revenue', 'normal_balance' => 'debit', 'is_system' => true],
        '6211' => ['name' => 'Giá vốn nguyên vật liệu', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
        '6221' => ['name' => 'Chi phí nhân sự', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
        '6271' => ['name' => 'Chi phí vận hành', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
        '6272' => ['name' => 'Chi phí khấu hao', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
        '6351' => ['name' => 'Phí ngân hàng/cổng thanh toán', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
        '8111' => ['name' => 'Chi phí khác', 'type' => 'expense', 'normal_balance' => 'debit', 'is_system' => true],
    ];

    public function post(array $payload): FinancialJournalEntry
    {
        $restaurantId = (int) ($payload['restaurant_id'] ?? 0);
        $entryDate = CarbonImmutable::parse($payload['entry_date'] ?? now())->toDateString();
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($restaurantId <= 0) {
            throw new RuntimeException('Financial posting requires a restaurant_id.');
        }

        return DB::transaction(function () use ($payload, $restaurantId, $entryDate, $idempotencyKey): FinancialJournalEntry {
            if ($idempotencyKey) {
                $existing = FinancialJournalEntry::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing->load('lines.account');
                }
            }

            $period = $this->ensurePeriod($restaurantId, $entryDate);
            if ($period->isClosed()) {
                throw new RuntimeException('Kỳ tài chính đã khóa, không thể ghi nhận giao dịch mới.');
            }

            $lines = $this->normalizeLines($payload['lines'] ?? []);
            $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

            if (count($lines) < 2 || abs($totalDebit - $totalCredit) > 0.01 || $totalDebit <= 0) {
                throw new RuntimeException('Bút toán tài chính phải có ít nhất hai dòng và tổng Nợ phải bằng tổng Có.');
            }

            $entry = FinancialJournalEntry::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId,
                'accounting_period_id' => $period->id,
                'branch_id' => $payload['branch_id'] ?? null,
                'entry_number' => $payload['entry_number'] ?? $this->generateEntryNumber(),
                'entry_date' => $entryDate,
                'status' => 'posted',
                'source_type' => $this->normalizeSourceType($payload['source_type'] ?? null),
                'source_id' => $payload['source_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'currency' => $payload['currency'] ?? 'VND',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'description' => $payload['description'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
                'posted_by' => $payload['posted_by'] ?? ($payload['created_by'] ?? null),
                'posted_at' => now(),
                'reversal_of_id' => $payload['reversal_of_id'] ?? null,
                'approval_request_id' => $payload['approval_request_id'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            foreach ($lines as $line) {
                $account = $this->accountFor($restaurantId, $line['account']);

                $entry->lines()->create([
                    'restaurant_id' => $restaurantId,
                    'financial_account_id' => $account->id,
                    'branch_id' => $line['branch_id'] ?? ($payload['branch_id'] ?? null),
                    'description' => $line['description'] ?? ($payload['description'] ?? null),
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'counterparty_type' => $this->normalizeSourceType($line['counterparty_type'] ?? null),
                    'counterparty_id' => $line['counterparty_id'] ?? null,
                    'cost_center' => $line['cost_center'] ?? null,
                    'metadata' => $line['metadata'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        }, 3);
    }

    public function ensureDefaultChart(int $restaurantId): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $code => $definition) {
            FinancialAccount::withoutGlobalScopes()->firstOrCreate(
                ['restaurant_id' => $restaurantId, 'code' => $code],
                $definition,
            );
        }
    }

    public function reverse(FinancialJournalEntry $entry, ?User $actor = null, ?string $reason = null): FinancialJournalEntry
    {
        $entry->loadMissing('lines');
        $reversalDate = CarbonImmutable::today();
        $currentPeriod = AccountingPeriod::withoutGlobalScopes()
            ->where('restaurant_id', $entry->restaurant_id)
            ->whereDate('period_start', $reversalDate->startOfMonth()->toDateString())
            ->whereDate('period_end', $reversalDate->endOfMonth()->toDateString())
            ->first();
        if ($currentPeriod?->isClosed()) {
            // A closed current month cannot receive a correction. Carry the
            // reversal into the next accounting month so the audit trail
            // remains append-only.
            $reversalDate = $reversalDate->addMonth()->startOfMonth();
        }

        return $this->post([
            'restaurant_id' => $entry->restaurant_id,
            'branch_id' => $entry->branch_id,
            'entry_date' => $reversalDate,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'idempotency_key' => 'journal:reversal:'.$entry->id,
            'description' => $reason ?: 'Đảo bút toán '.$entry->entry_number,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'reversal_of_id' => $entry->id,
            'metadata' => ['reversal_reason' => $reason],
            'lines' => $entry->lines->map(fn ($line): array => [
                'account' => $line->financial_account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'branch_id' => $line->branch_id,
                'description' => $reason ?: 'Đảo '.$line->description,
                'counterparty_type' => $line->counterparty_type,
                'counterparty_id' => $line->counterparty_id,
                'cost_center' => $line->cost_center,
                'metadata' => ['reversal_of_line_id' => $line->id],
            ])->all(),
        ]);
    }

    public function closePeriod(AccountingPeriod $period, ?User $actor = null, ?string $notes = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $actor, $notes): AccountingPeriod {
            $locked = AccountingPeriod::withoutGlobalScopes()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status === 'closed') {
                return $locked;
            }

            $checklist = $this->closeChecklist($locked);
            $blocking = collect($checklist)->filter(fn (array $item): bool => ($item['blocking'] ?? true) && ! ($item['ok'] ?? false));
            if ($blocking->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'close_checklist' => $blocking->map(fn (array $item): string => $item['message'])->values()->all(),
                ]);
            }

            $locked->update([
                'status' => 'closed',
                'closed_by' => $actor?->id,
                'closed_at' => now(),
                'notes' => $notes ?: $locked->notes,
                'close_checklist' => $checklist,
                'reopened_by' => null,
                'reopened_at' => null,
                'reopen_reason' => null,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Return the controls that must be green before a period can be locked.
     * The result is intentionally serializable so it can be shown in the UI
     * and stored alongside the close audit trail.
     *
     * @return array<string, array{ok: bool, blocking: bool, count: int, message: string}>
     */
    public function closeChecklist(AccountingPeriod $period): array
    {
        $restaurantId = (int) $period->restaurant_id;
        $start = $period->period_start->toDateString();
        $end = $period->period_end->toDateString();

        $pendingExpenses = OperatingExpense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('expense_date', [$start, $end])
            ->whereNotIn('status', ['approved', 'paid', 'rejected'])
            ->count();

        $unmatchedBankLines = BankStatementLine::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('transaction_date', [$start, $end])
            ->where('status', 'unmatched')
            ->count();

        $openRegisters = CashRegister::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'open')
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('closing_date', [$start, $end])
                    ->orWhereNull('closing_date');
            })
            ->count();

        $assetsInService = FixedAsset::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->whereDate('in_service_date', '<=', $period->period_end->toDateString())
            ->count();
        $assetsDepreciated = FixedAsset::withoutGlobalScopes()
            ->where('fixed_assets.restaurant_id', $restaurantId)
            ->where('fixed_assets.status', 'active')
            ->whereDate('fixed_assets.in_service_date', '<=', $period->period_end->toDateString())
            ->whereHas('depreciations', fn ($query) => $query->whereDate('period_month', $period->period_start->toDateString()))
            ->count();
        $pendingDepreciation = max(0, $assetsInService - $assetsDepreciated);

        $unbalancedEntries = FinancialJournalEntry::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('entry_date', [$start, $end])
            ->get(['total_debit', 'total_credit'])
            ->filter(fn (FinancialJournalEntry $entry): bool => abs((float) $entry->total_debit - (float) $entry->total_credit) > 0.01)
            ->count();

        return [
            'journal_balance' => [
                'ok' => $unbalancedEntries === 0,
                'blocking' => true,
                'count' => $unbalancedEntries,
                'message' => $unbalancedEntries === 0 ? 'Tất cả bút toán đều cân bằng.' : "Có {$unbalancedEntries} bút toán mất cân bằng.",
            ],
            'pending_expenses' => [
                'ok' => $pendingExpenses === 0,
                'blocking' => true,
                'count' => $pendingExpenses,
                'message' => $pendingExpenses === 0 ? 'Không còn chứng từ chi phí chờ xử lý.' : "Còn {$pendingExpenses} chứng từ chi phí chưa duyệt/thanh toán.",
            ],
            'unmatched_bank_lines' => [
                'ok' => $unmatchedBankLines === 0,
                'blocking' => false,
                'count' => $unmatchedBankLines,
                'message' => $unmatchedBankLines === 0 ? 'Đã xử lý toàn bộ dòng sao kê.' : "Còn {$unmatchedBankLines} dòng sao kê chưa đối soát.",
            ],
            'open_cash_registers' => [
                'ok' => $openRegisters === 0,
                'blocking' => true,
                'count' => $openRegisters,
                'message' => $openRegisters === 0 ? 'Không còn két mở trong kỳ.' : "Còn {$openRegisters} két chưa chốt.",
            ],
            'depreciation' => [
                'ok' => $pendingDepreciation === 0,
                'blocking' => true,
                'count' => $pendingDepreciation,
                'message' => $pendingDepreciation === 0 ? 'Đã ghi nhận khấu hao cho tài sản đang hoạt động.' : "Còn {$pendingDepreciation} tài sản chưa khấu hao trong kỳ.",
            ],
        ];
    }

    public function reopenPeriod(AccountingPeriod $period, User $actor, string $reason): AccountingPeriod
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Bắt buộc nêu lý do mở lại kỳ.']);
        }

        return DB::transaction(function () use ($period, $actor, $reason): AccountingPeriod {
            $locked = AccountingPeriod::withoutGlobalScopes()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== 'closed') {
                throw ValidationException::withMessages(['period' => 'Kỳ kế toán chưa bị khóa.']);
            }

            $locked->update([
                'status' => 'open',
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    public function accountFor(int $restaurantId, string|int $account): FinancialAccount
    {
        $code = (string) $account;
        $existingByCode = FinancialAccount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('code', $code)
            ->first();
        if ($existingByCode) {
            return $existingByCode;
        }

        if (is_numeric($account) && ! isset(self::DEFAULT_ACCOUNTS[$code])) {
            return FinancialAccount::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->findOrFail((int) $account);
        }

        if (! isset(self::DEFAULT_ACCOUNTS[$code])) {
            throw new RuntimeException("Mã tài khoản kế toán '{$code}' không hợp lệ hoặc chưa được định nghĩa trong hệ thống.");
        }

        $definition = self::DEFAULT_ACCOUNTS[$code];

        return FinancialAccount::withoutGlobalScopes()->firstOrCreate(
            ['restaurant_id' => $restaurantId, 'code' => $code],
            $definition,
        );
    }

    private function ensurePeriod(int $restaurantId, string $entryDate): AccountingPeriod
    {
        $date = CarbonImmutable::parse($entryDate);
        $start = $date->startOfMonth()->toDateString();
        $end = $date->endOfMonth()->toDateString();
        $period = AccountingPeriod::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->first();

        if ($period) {
            return $period;
        }

        try {
            return AccountingPeriod::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId,
                'period_start' => $start,
                'period_end' => $end,
                'status' => 'open',
            ]);
        } catch (UniqueConstraintViolationException) {
            return AccountingPeriod::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->firstOrFail();
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizeLines(array $lines): array
    {
        return array_values(array_map(function (array $line): array {
            // Chặn sớm payload sai tên khóa. Trước đây thiếu 'account' chỉ tạo ra
            // một PHP notice ở tận accountFor(), sau khi bút toán đã được ghi —
            // lỗi nổi lên dưới dạng HTTP 500 không rõ nguyên nhân.
            if (! array_key_exists('account', $line)) {
                throw new RuntimeException(sprintf(
                    'Dòng bút toán thiếu khóa bắt buộc "account" (nhận được: %s).',
                    implode(', ', array_keys($line)) ?: 'không có khóa nào',
                ));
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0) || ($debit === 0.0 && $credit === 0.0)) {
                throw new RuntimeException('Mỗi dòng bút toán chỉ được có Nợ hoặc Có và số tiền phải lớn hơn 0.');
            }

            return $line + ['debit' => $debit, 'credit' => $credit];
        }, $lines));
    }

    private function normalizeSourceType(mixed $sourceType): ?string
    {
        if ($sourceType === null || $sourceType === '') {
            return null;
        }

        return is_object($sourceType) ? $sourceType::class : (string) $sourceType;
    }

    private function generateEntryNumber(): string
    {
        return 'FI-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
    }
}
