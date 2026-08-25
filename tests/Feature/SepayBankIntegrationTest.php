<?php

namespace Tests\Feature;

use App\Models\FinancialBankAccount;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SepayBankIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sepay_api_v2_sync_is_idempotent(): void
    {
        config()->set('services.sepay.api_version', 'v2');
        config()->set('services.sepay.api_base_url', 'https://userapi.sepay.vn/v2');
        config()->set('services.sepay.api_token', 'test-api-token');
        config()->set('services.sepay.api_key', null);

        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $owner->assignRole('owner');
        $account = FinancialBankAccount::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Tài khoản SePay',
            'bank_name' => 'MBBank',
            'account_number' => '0123456789',
            'account_holder' => 'Aventura',
            'account_type' => 'bank',
            'financial_account_code' => '1121',
        ]);

        Http::fake([
            'https://userapi.sepay.vn/v2/transactions*' => Http::response([
                'status' => 'success',
                'data' => [[
                    'id' => 'tx-v2-001',
                    'transaction_date' => '2026-08-23 09:30:00',
                    'account_number' => '0123456789',
                    'amount_in' => 250000,
                    'amount_out' => 0,
                    'accumulated' => 1250000,
                    'transaction_content' => 'Thanh toan don hang DH001',
                    'reference_number' => 'FT-V2-001',
                    'bank_brand_name' => 'MBBank',
                ]],
                'meta' => ['pagination' => ['has_more' => false]],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->post(route('bank-reconciliation.sync-sepay'), [
                'financial_bank_account_id' => $account->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('bank-reconciliation.sync-sepay'), [
                'financial_bank_account_id' => $account->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('bank_statement_lines', 1);
        $this->assertDatabaseHas('bank_statement_lines', [
            'financial_bank_account_id' => $account->id,
            'external_reference' => 'FT-V2-001',
            'amount_in' => 250000,
            'idempotency_key' => 'sepay:tx-v2-001',
        ]);
    }

    public function test_sepay_webhook_is_authenticated_and_deduplicated(): void
    {
        config()->set('services.sepay.webhook_secret', 'test-webhook-secret');
        config()->set('services.sepay.webhook_api_key', null);

        $restaurant = Restaurant::factory()->create();
        $account = FinancialBankAccount::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Tài khoản nhận tiền',
            'bank_name' => 'MBBank',
            'account_number' => '0123456789',
            'account_type' => 'bank',
            'financial_account_code' => '1121',
        ]);
        $payload = [
            'id' => 92704,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-08-23 10:00:00',
            'accountNumber' => '0123456789',
            'content' => 'SEVN-001 chuyen tien',
            'transferType' => 'in',
            'transferAmount' => 500000,
            'accumulated' => 1750000,
            'referenceCode' => 'FT-WEBHOOK-001',
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, 'test-webhook-secret');

        $headers = [
            'X-SePay-Signature' => $signature,
            'X-SePay-Timestamp' => (string) $timestamp,
        ];

        $this->postJson(route('webhooks.sepay.bank'), $payload, $headers)->assertOk();
        $this->postJson(route('webhooks.sepay.bank'), $payload, $headers)->assertOk();

        $this->assertDatabaseCount('bank_statement_lines', 1);
        $this->assertDatabaseHas('bank_statement_lines', [
            'financial_bank_account_id' => $account->id,
            'external_reference' => 'FT-WEBHOOK-001',
            'amount_in' => 500000,
            'idempotency_key' => 'sepay:92704',
        ]);
    }
}
