<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\FinancialBankAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SepayBankService
{
    public function canSync(): bool
    {
        return filled(config('services.sepay.api_token'))
            || filled(config('services.sepay.api_key'));
    }

    public function hasWebhookCredentials(): bool
    {
        return filled(config('services.sepay.webhook_secret'))
            || filled(config('services.sepay.webhook_api_key'));
    }

    /**
     * Pull transactions from SePay and store them as idempotent statement lines.
     * API v2 is the default; the legacy v1 endpoint remains supported for existing keys.
     *
     * @return array{received:int,created:int,account_id:int}
     */
    public function sync(FinancialBankAccount $account, ?string $fromDate = null, ?string $toDate = null, ?User $importedBy = null): array
    {
        if (! $account->account_number) {
            throw ValidationException::withMessages([
                'sepay' => 'Tài khoản đối soát chưa có số tài khoản để đối chiếu với SePay.',
            ]);
        }

        if (! $this->canSync()) {
            throw ValidationException::withMessages([
                'sepay' => 'Chưa cấu hình SEPAY_API_TOKEN hoặc SEPAY_API_KEY để đồng bộ lịch sử giao dịch.',
            ]);
        }

        $rows = $this->usesLegacyApi()
            ? $this->fetchLegacyTransactions($account, $fromDate, $toDate)
            : $this->fetchV2Transactions($account, $fromDate, $toDate);

        return $this->persistRows($account, $rows, $importedBy);
    }

    /**
     * Validate and persist one SePay webhook payload.
     * Webhooks are acknowledged only after authentication and persistence succeed.
     *
     * @return array{created:bool,account_id:int}
     */
    public function ingestWebhook(array $payload): array
    {
        $row = $this->normalizeRow($payload);
        $account = $this->resolveAccount($row['account_number']);

        if (! $account) {
            throw ValidationException::withMessages([
                'account_number' => 'Không tìm thấy tài khoản đối soát đang hoạt động tương ứng với giao dịch SePay.',
            ]);
        }

        $result = $this->persistRows($account, [$row]);

        return [
            'created' => $result['created'] > 0,
            'account_id' => $account->id,
        ];
    }

    public function isValidWebhookRequest(Request $request): bool
    {
        $apiKey = (string) config('services.sepay.webhook_api_key', '');
        $authorization = (string) $request->header('Authorization', '');

        if ($apiKey !== '' && str_starts_with($authorization, 'Apikey ')) {
            $provided = substr($authorization, 7);
            if ($provided !== '' && hash_equals($apiKey, $provided)) {
                return true;
            }
        }

        $secret = (string) config('services.sepay.webhook_secret', '');
        $signature = (string) $request->header('X-SePay-Signature', '');
        if ($secret === '' || $signature === '') {
            return false;
        }

        $timestamp = (int) $request->header('X-SePay-Timestamp', 0);
        if ($timestamp > 0 && abs(now()->timestamp - $timestamp) <= 300) {
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        // Compatibility with the existing billing webhook signature format.
        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    private function usesLegacyApi(): bool
    {
        return strtolower((string) config('services.sepay.api_version', 'v2')) === 'v1'
            || (! filled(config('services.sepay.api_token')) && filled(config('services.sepay.api_key')));
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchV2Transactions(FinancialBankAccount $account, ?string $fromDate, ?string $toDate): array
    {
        $baseUrl = rtrim((string) config('services.sepay.api_base_url'), '/');
        $token = (string) config('services.sepay.api_token');
        $rows = [];
        $page = 1;

        do {
            $query = [
                'page' => $page,
                'per_page' => 100,
                'timestamp_format' => 'iso8601',
            ];
            if ($fromDate) {
                $query['transaction_date_from'] = $fromDate.' 00:00:00';
            }
            if ($toDate) {
                $query['transaction_date_to'] = $toDate.' 23:59:59';
            }

            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(20)
                ->get($baseUrl.'/transactions', $query);

            if ($response->failed()) {
                throw new RuntimeException('SePay API v2 trả về HTTP '.$response->status().'.');
            }

            $body = $response->json();
            $batch = $body['data'] ?? [];
            if (! is_array($batch)) {
                throw new RuntimeException('Phản hồi SePay API v2 không đúng định dạng.');
            }

            $rows = [...$rows, ...$batch];
            $pagination = $body['meta']['pagination'] ?? [];
            $hasMore = (bool) ($pagination['has_more'] ?? false);
            $page++;
        } while ($hasMore && $page <= 50);

        return array_values(array_filter($rows, fn (array $row): bool => $this->sameAccount($row, $account)));
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchLegacyTransactions(FinancialBankAccount $account, ?string $fromDate, ?string $toDate): array
    {
        $baseUrl = rtrim((string) config('services.sepay.legacy_api_base_url'), '/');
        $apiKey = (string) config('services.sepay.api_key');
        $query = [
            'account_number' => $account->account_number,
            'limit' => 5000,
        ];
        if ($fromDate) {
            $query['transaction_date_min'] = $fromDate;
        }
        if ($toDate) {
            $query['transaction_date_max'] = $toDate;
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'Apikey '.$apiKey])
            ->timeout(20)
            ->get($baseUrl.'/transactions/list', $query);

        if ($response->failed()) {
            throw new RuntimeException('SePay API v1 trả về HTTP '.$response->status().'.');
        }

        $body = $response->json();
        $rows = $body['transactions'] ?? [];
        if (! is_array($rows)) {
            throw new RuntimeException('Phản hồi SePay API v1 không đúng định dạng.');
        }

        return array_values(array_filter($rows, fn (array $row): bool => $this->sameAccount($row, $account)));
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function persistRows(FinancialBankAccount $account, array $rows, ?User $importedBy = null): array
    {
        $created = 0;

        DB::transaction(function () use ($account, $rows, $importedBy, &$created): void {
            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);
                if (! $this->sameAccount($normalized, $account)) {
                    continue;
                }

                $idempotencyKey = $this->idempotencyKey($normalized, $account);
                $line = BankStatementLine::withoutGlobalScopes()
                    ->where('restaurant_id', $account->restaurant_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                $isNew = ! $line;
                $line ??= new BankStatementLine();

                $line->forceFill([
                    'restaurant_id' => $account->restaurant_id,
                    'financial_bank_account_id' => $account->id,
                    'transaction_date' => $normalized['transaction_date'],
                    'value_date' => $normalized['transaction_date'],
                    'external_reference' => $normalized['external_reference'],
                    'description' => $normalized['description'],
                    'amount_in' => $normalized['amount_in'],
                    'amount_out' => $normalized['amount_out'],
                    'balance' => $normalized['balance'],
                    'fee_amount' => 0,
                    'idempotency_key' => $idempotencyKey,
                    'raw_payload' => $row,
                    'imported_by' => $importedBy?->id,
                    'imported_at' => now(),
                ]);
                if ($isNew) {
                    $line->status = 'unmatched';
                    $line->save();
                    $created++;
                } else {
                    // Never unmatch a line that a user has already reconciled.
                    $line->save();
                }
            }
        });

        return [
            'received' => count($rows),
            'created' => $created,
            'account_id' => $account->id,
        ];
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        $transactionDate = $row['transaction_date'] ?? $row['transactionDate'] ?? null;
        $accountNumber = (string) ($row['account_number'] ?? $row['accountNumber'] ?? '');
        $amountIn = $row['amount_in'] ?? null;
        $amountOut = $row['amount_out'] ?? null;

        if ($amountIn === null && $amountOut === null) {
            $amount = (float) ($row['transferAmount'] ?? $row['transfer_amount'] ?? 0);
            if (($row['transferType'] ?? $row['transfer_type'] ?? 'in') === 'out') {
                $amountOut = $amount;
                $amountIn = 0;
            } else {
                $amountIn = $amount;
                $amountOut = 0;
            }
        }

        if (! $transactionDate) {
            throw ValidationException::withMessages([
                'transaction' => 'Giao dịch SePay thiếu thời gian giao dịch.',
            ]);
        }

        return [
            'provider_id' => (string) ($row['provider_id'] ?? $row['id'] ?? ''),
            'account_number' => $accountNumber,
            'transaction_date' => CarbonImmutable::parse($transactionDate)->toDateTimeString(),
            'external_reference' => $row['external_reference'] ?? $row['reference_number'] ?? $row['referenceCode'] ?? null,
            'description' => $row['transaction_content'] ?? $row['content'] ?? $row['description'] ?? null,
            'amount_in' => round((float) ($amountIn ?? 0), 2),
            'amount_out' => round((float) ($amountOut ?? 0), 2),
            'balance' => array_key_exists('accumulated', $row) ? round((float) $row['accumulated'], 2) : null,
            'raw_payload' => $row,
        ];
    }

    /** @param array<string, mixed> $row */
    private function sameAccount(array $row, FinancialBankAccount $account): bool
    {
        $rowAccount = (string) ($row['account_number'] ?? $row['accountNumber'] ?? '');

        return $rowAccount === '' || $rowAccount === (string) $account->account_number;
    }

    /** @param array<string, mixed> $row */
    private function idempotencyKey(array $row, FinancialBankAccount $account): string
    {
        if ($row['provider_id'] !== '') {
            return 'sepay:'.$row['provider_id'];
        }

        return 'sepay:'.hash('sha256', implode('|', [
            $account->account_number,
            $row['transaction_date'],
            $row['external_reference'] ?? '',
            $row['amount_in'],
            $row['amount_out'],
        ]));
    }

    private function resolveAccount(string $accountNumber): ?FinancialBankAccount
    {
        $accountNumber = trim($accountNumber ?: (string) config('services.sepay.account_number'));
        if ($accountNumber === '') {
            return null;
        }

        $query = FinancialBankAccount::withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->where('is_active', true);
        if ($restaurantId = config('services.sepay.restaurant_id')) {
            $query->where('restaurant_id', (int) $restaurantId);
        }

        $accounts = $query->get();
        if ($accounts->count() > 1) {
            throw ValidationException::withMessages([
                'account_number' => 'Số tài khoản SePay đang trùng ở nhiều nhà hàng, cần cấu hình SEPAY_RESTAURANT_ID.',
            ]);
        }

        return $accounts->first();
    }
}
