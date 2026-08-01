<?php

namespace Tests\Feature;

use App\Services\EmailMicroserviceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyReportEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buộc đi qua nhánh fallback Brevo trực tiếp (Python email_service coi như chưa cấu hình).
        config()->set('services.email_microservice.url', '');
        config()->set('services.brevo.api_key', 'fake-brevo-key-for-test');
    }

    public function test_daily_report_email_renders_and_sends_via_brevo_fallback(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => 'fake-id'], 201),
        ]);

        // Shape này khớp với output thật của DailyReportService::generateForRestaurant().
        $data = [
            'owner_email' => 'owner@example.com',
            'restaurant_name' => 'Phở Test',
            'report_date_vn' => '06/07/2026',
            'gross_revenue' => 5000000,
            'net_revenue' => 4800000,
            'discount_total' => 200000,
            'order_count' => 42,
            'completed_count' => 40,
            'cancelled_count' => 2,
            'average_order_value' => 120000,
            'cash_revenue' => 2000000,
            'bank_transfer_revenue' => 2000000,
            'card_revenue' => 500000,
            'ewallet_revenue' => 300000,
            'top_products' => [['name' => 'Phở bò', 'qty' => 20, 'revenue' => 2000000]],
            'shift_summary' => [['shift_name' => 'Ca sáng', 'status' => 'confirmed', 'cash_difference' => 0]],
            'has_unconfirmed_shifts' => false,
            'comparison' => ['yesterday_date' => '05/07/2026', 'revenue_delta' => 100000, 'revenue_delta_pct' => 2.1, 'order_delta' => 3],
            'peak_hour' => ['label' => '12:00 - 12:59', 'order_count' => 10, 'revenue' => 1000000],
        ];

        $result = app(EmailMicroserviceClient::class)->sendDailyReport($data);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.brevo.com')) {
                return false;
            }

            $body = $request->data();

            return str_contains($body['subject'], 'Báo cáo doanh thu ngày 06/07/2026')
                && str_contains($body['htmlContent'], 'Phở Test')
                && str_contains($body['htmlContent'], 'Doanh thu thuần')
                && $body['to'][0]['email'] === 'owner@example.com';
        });
    }
}
