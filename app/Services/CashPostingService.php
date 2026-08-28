<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $register = null;
            if (! empty($data['cash_register_id'])) {
                $register = CashRegister::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->whereKey($data['cash_register_id'])
                    ->when(isset($data['branch_id']), fn ($query) => $query->where('branch_id', $data['branch_id']))
                    ->when(array_key_exists('area_id', $data), fn ($query) => is_null($data['area_id'])
                        ? $query->whereNull('area_id')
                        : $query->where('area_id', $data['area_id']))
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
            } else {
                $registerCandidates = CashRegister::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->when(isset($data['branch_id']), fn ($query) => $query->where('branch_id', $data['branch_id']))
                    ->when(array_key_exists('area_id', $data), fn ($query) => is_null($data['area_id'])
                        ? $query->whereNull('area_id')
                        : $query->where('area_id', $data['area_id']))
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->get();

                if (! array_key_exists('area_id', $data) && $registerCandidates->count() > 1) {
                    throw ValidationException::withMessages([
                        'cash_register_id' => 'Chi nhánh có nhiều két đang mở. Vui lòng chỉ rõ khu vực/két nhận giao dịch.',
                    ]);
                }

                $register = $registerCandidates->sortByDesc('id')->first();
            }

            // Khóa két trước khi kiểm tra idempotency để hai request đồng thời
            // cùng một mã không thể tạo hai dòng tiền.
            if ($idempotencyKey) {
                $existingQuery = CashTransaction::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('idempotency_key', $idempotencyKey);

                if ($register) {
                    $existingQuery->lockForUpdate();
                }

                $existing = $existingQuery->first();
                if ($existing) {
                    return $this->assertIdempotentReplay($existing, $data, $amount);
                }
            }

            if (! $register && ($data['auto_open_if_missing'] ?? false)) {
                $areaId = array_key_exists('area_id', $data) ? $data['area_id'] : null;
                $register = CashRegister::withoutGlobalScopes()->create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $data['branch_id'] ?? null,
                    'area_id' => $areaId,
                    'shift_id' => $data['shift_id'] ?? null,
                    'closing_date' => $data['closing_date'] ?? today(),
                    'opened_by' => $data['created_by'] ?? null,
                    'cashier_user_id' => $data['cashier_user_id'] ?? $data['created_by'] ?? null,
                    'opening_balance' => 0,
                    'expected_closing_balance' => 0,
                    'expense_budget' => 0,
                    'open_scope_key' => $this->openScopeKey(
                        $restaurantId,
                        (int) ($data['branch_id'] ?? 0),
                        $areaId,
                    ),
                    'auto_opened' => true,
                    'requires_opening_reconciliation' => true,
                    'status' => 'open',
                    'opened_at' => $data['occurred_at'] ?? now(),
                    'notes' => 'Tự động mở do phát sinh thanh toán tiền mặt khi nhân viên chưa mở ca. Chờ quản lý đối soát số dư đầu ca.',
                ]);

                AuditLog::log('cash_register_auto_opened', 'created', $register, null, [
                    'branch_id' => $register->branch_id,
                    'area_id' => $register->area_id,
                    'reason' => 'cash_payment_without_open_register',
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }

            if ($register?->requires_opening_reconciliation && ($data['type'] ?? null) === 'out') {
                throw ValidationException::withMessages([
                    'amount' => 'Két đang chờ quản lý đối soát số dư đầu ca; chưa được phép ghi nhận khoản chi tiền mặt.',
                ]);
            }

            if ($register && ($data['enforce_cash_balance'] ?? false)) {
                $currentCash = $this->calculateRegisterCash($register->id, (float) $register->opening_balance);

                if (($data['type'] ?? null) === 'out' && $currentCash + 0.01 < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            'Không thể chi %sđ: số dư két khả dụng chỉ còn %sđ.',
                            number_format($amount),
                            number_format(max(0, $currentCash)),
                        ),
                    ]);
                }

                $budget = (float) ($data['budget_limit'] ?? 0);
                if (($data['type'] ?? null) === 'out' && $budget > 0) {
                    $existingOut = (float) CashTransaction::withoutGlobalScopes()
                        ->where('cash_register_id', $register->id)
                        ->where('type', 'out')
                        ->sum('amount');

                    if ($existingOut + $amount > $budget && ! ($data['allow_budget_overrun'] ?? false)) {
                        throw ValidationException::withMessages([
                            'amount' => sprintf(
                                'Khoản chi vượt ngân sách ca: đã chi %sđ / tối đa %sđ.',
                                number_format($existingOut),
                                number_format($budget),
                            ),
                        ]);
                    }
                }

                if ($register->expected_closing_balance === null) {
                    $register->update(['expected_closing_balance' => $currentCash]);
                }
            }

            $journal = $this->financialPosting->post([
                'restaurant_id' => $restaurantId,
                'branch_id' => $data['branch_id'] ?? $register?->branch_id,
                'entry_date' => $data['occurred_at'] ?? today(),
                'source_type' => $data['journal_source_type'] ?? $data['reference_type'] ?? null,
                'source_id' => $data['journal_source_id'] ?? $data['reference_id'] ?? null,
                'approval_request_id' => $data['approval_request_id'] ?? null,
                'idempotency_key' => $data['journal_idempotency_key'] ?? $idempotencyKey,
                'description' => $data['journal_description'] ?? $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'posted_by' => $data['created_by'] ?? null,
                'metadata' => [
                    'cash_register_id' => $register?->id,
                    'cash_register_missing' => $register === null,
                    'cash_register_auto_opened' => (bool) ($register?->auto_opened),
                    'voucher_code' => $data['voucher_code'] ?? null,
                ],
                'lines' => $data['lines'] ?? [
                    ['account' => $data['debit_account'] ?? '1111', 'debit' => $amount, 'credit' => 0],
                    ['account' => $data['credit_account'] ?? '5111', 'debit' => 0, 'credit' => $amount],
                ],
            ]);

            if (! $register) {
                $branchId = $data['branch_id'] ?? null;
                if ($branchId) {
                    $register = CashRegister::withoutGlobalScopes()->create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => (int) $branchId,
                        'area_id' => $data['area_id'] ?? null,
                        'opened_by' => $data['created_by'] ?? ($data['cashier_user_id'] ?? null),
                        'register_name' => 'Két tiền mặt tự động - Chi nhánh #'.$branchId,
                        'opening_balance' => 0.0,
                        'auto_opened' => true,
                        'requires_opening_reconciliation' => true,
                        'status' => 'open',
                        'opened_at' => $data['occurred_at'] ?? now(),
                        'notes' => 'Tự động mở két để lưu vết giao dịch tiền mặt ca.',
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        'cash' => 'Giao dịch tiền mặt bắt buộc phải thuộc một chi nhánh có két tiền mặt.',
                    ]);
                }
            }

            $transaction = CashTransaction::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId,
                'branch_id' => $data['branch_id'] ?? $register->branch_id,
                'cash_register_id' => $register->id,
                'payment_id' => $data['payment_id'] ?? null,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'approval_request_id' => $data['approval_request_id'] ?? null,
                'type' => $data['type'],
                'amount' => $amount,
                'source' => $data['source'] ?? 'other',
                'status' => 'posted',
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'voucher_code' => $data['voucher_code'] ?? null,
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

    private function openScopeKey(int $restaurantId, int $branchId, ?int $areaId): string
    {
        return "{$restaurantId}:{$branchId}:".($areaId ?? 'default');
    }

    private function calculateRegisterCash(int $registerId, float $openingBalance): float
    {
        $in = (float) CashTransaction::withoutGlobalScopes()
            ->where('cash_register_id', $registerId)
            ->where('type', 'in')
            ->sum('amount');
        $out = (float) CashTransaction::withoutGlobalScopes()
            ->where('cash_register_id', $registerId)
            ->where('type', 'out')
            ->sum('amount');

        return round($openingBalance + $in - $out, 2);
    }

    private function assertIdempotentReplay(CashTransaction $existing, array $data, float $amount): CashTransaction
    {
        $sameRegister = empty($data['cash_register_id'])
            || (int) $existing->cash_register_id === (int) $data['cash_register_id'];
        $sameVoucher = ! array_key_exists('voucher_code', $data)
            || (string) ($existing->voucher_code ?? '') === (string) ($data['voucher_code'] ?? '');

        if (
            ($data['type'] ?? null) !== $existing->type
            || abs((float) $existing->amount - $amount) > 0.01
            || (! empty($data['source']) && $data['source'] !== $existing->source)
            || ! $sameRegister
            || ! $sameVoucher
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Mã gửi lặp đã được dùng cho một giao dịch khác.',
            ]);
        }

        return $existing;
    }
}
