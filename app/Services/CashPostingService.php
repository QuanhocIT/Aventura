<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Records a cash movement and its matching double-entry posting atomically.
 */
class CashPostingService
{
    public function __construct(private FinancialPostingService $financialPosting) {}

    public function record(array $data): ?CashTransaction
    {
        $restaurantId = (int) $data['restaurant_id'];
        $amount = round((float) $data['amount'], 2);
        $idempotencyKey = $data['idempotency_key'] ?? null;

        return DB::transaction(function () use ($data, $restaurantId, $amount, $idempotencyKey): ?CashTransaction {
            if ($idempotencyKey) {
                $existing = CashTransaction::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $register = null;
            if (! empty($data['cash_register_id'])) {
                $register = CashRegister::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->whereKey($data['cash_register_id'])
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
            } else {
                $register = CashRegister::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->when(isset($data['branch_id']), fn ($query) => $query->where('branch_id', $data['branch_id']))
                    ->where('status', 'open')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
            }

            $journal = $this->financialPosting->post([
                'restaurant_id' => $restaurantId,
                'branch_id' => $data['branch_id'] ?? $register?->branch_id,
                'entry_date' => $data['occurred_at'] ?? today(),
                'source_type' => $data['journal_source_type'] ?? $data['reference_type'] ?? null,
                'source_id' => $data['journal_source_id'] ?? $data['reference_id'] ?? null,
                'idempotency_key' => $data['journal_idempotency_key'] ?? $idempotencyKey,
                'description' => $data['journal_description'] ?? $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'posted_by' => $data['created_by'] ?? null,
                'metadata' => [
                    'cash_register_id' => $register?->id,
                    'cash_register_missing' => $register === null,
                ],
                'lines' => $data['lines'] ?? [
                    ['account' => $data['debit_account'] ?? '1111', 'debit' => $amount, 'credit' => 0],
                    ['account' => $data['credit_account'] ?? '5111', 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            if (! $register) {
                // The payment remains financially recorded, but the missing
                // register is visible in journal metadata for reconciliation.
                return null;
            }

            $transaction = CashTransaction::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $data['branch_id'] ?? $register->branch_id,
                'cash_register_id' => $register->id,
                'payment_id' => $data['payment_id'] ?? null,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'type' => $data['type'],
                'amount' => $amount,
                'source' => $data['source'] ?? 'other',
                'status' => 'posted',
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            if ($register->expected_closing_balance !== null) {
                $register->increment(
                    'expected_closing_balance',
                    $data['type'] === 'out' ? -$amount : $amount,
                );
            }

            // Keep the local variable referenced so static analysis can ensure
            // the financial entry is created before the cash movement.
            $transaction->setRelation('journalEntry', $journal);

            return $transaction;
        }, 3);
    }
}
