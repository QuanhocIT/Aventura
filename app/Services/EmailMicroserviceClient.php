<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailMicroserviceClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.email_microservice.url', ''), '/');
    }

    public function sendWelcome(array $data): bool
    {
        return $this->post('/send/welcome', $data);
    }

    public function sendInvoice(array $data): bool
    {
        return $this->post('/send/invoice', $data);
    }

    public function sendDailyReport(array $data): bool
    {
        return $this->post('/send/daily-report', [
            'recipient_email' => $data['owner_email'],
            'restaurant_name' => $data['restaurant_name'],
            'report_date' => $data['report_date_vn'],
            'gross_revenue' => $data['gross_revenue'],
            'net_revenue' => $data['net_revenue'],
            'discount_total' => $data['discount_total'],
            'order_count' => $data['order_count'],
            'completed_count' => $data['completed_count'],
            'cancelled_count' => $data['cancelled_count'],
            'average_order_value' => $data['average_order_value'],
            'cash_revenue' => $data['cash_revenue'],
            'bank_transfer_revenue' => $data['bank_transfer_revenue'],
            'card_revenue' => $data['card_revenue'],
            'ewallet_revenue' => $data['ewallet_revenue'],
            'top_products' => $data['top_products'],
            'shift_summary' => $data['shift_summary'],
            'has_unconfirmed_shifts' => $data['has_unconfirmed_shifts'],
            'comparison' => $data['comparison'],
            'peak_hour' => $data['peak_hour'],
        ]);
    }

    private function post(string $endpoint, array $payload): bool
    {
        if (empty($this->baseUrl)) {
            Log::warning('EmailMicroserviceClient: EMAIL_SERVICE_URL chưa được cấu hình, bỏ qua gửi email.');

            return false;
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 500)
                ->post($this->baseUrl.$endpoint, $payload);

            if ($response->successful()) {
                Log::info('EmailMicroserviceClient: gửi email thành công', [
                    'endpoint' => $endpoint,
                    'message_id' => $response->json('message_id'),
                ]);

                return true;
            }

            Log::error('EmailMicroserviceClient: phản hồi lỗi từ Python service', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('EmailMicroserviceClient: không kết nối được Python service', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
