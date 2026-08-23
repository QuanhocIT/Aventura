<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
        '3331' => ['name' => 'Thuế phải nộp', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
        '3341' => ['name' => 'Phải trả người lao động', 'type' => 'liability', 'normal_balance' => 'credit', 'is_system' => true],
        '4111' => ['name' => 'Vốn chủ sở hữu', 'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true],
        '5111' => ['name' => 'Doanh thu bán hàng', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
        '5112' => ['name' => 'Phí dịch vụ thu được', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true],
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

            $locked->update([
                'status' => 'closed',
                'closed_by' => $actor?->id,
                'closed_at' => now(),
                'notes' => $notes ?: $locked->notes,
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

        $definition = self::DEFAULT_ACCOUNTS[$code] ?? [
            'name' => 'Tài khoản '.$code,
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_system' => false,
        ];

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
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
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
