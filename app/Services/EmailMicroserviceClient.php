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
