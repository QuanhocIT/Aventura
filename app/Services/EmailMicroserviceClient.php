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

    public function sendOtp(array $data): bool
    {
        return $this->post('/send/otp', $data);
    }

    public function sendVerification(array $data): bool
    {
        return $this->post('/send/verification', $data);
    }

    public function sendCampaignEmail(array $data): bool
    {
        return $this->post('/send/campaign', [
            'recipient_email' => $data['email'] ?? $data['recipient_email'] ?? '',
            'recipient_name'  => $data['name'] ?? $data['recipient_name'] ?? '',
            'title'            => $data['title'] ?? '',
            'content'          => $data['content'] ?? '',
        ]);
    }

    public function sendDailyReport(array $data): bool
    {
        return $this->post('/send/daily-report', [
            'recipient_email'        => $data['owner_email'],
            'restaurant_name'        => $data['restaurant_name'],
            'report_date'            => $data['report_date_vn'],
            'gross_revenue'          => $data['gross_revenue'],
            'net_revenue'            => $data['net_revenue'],
            'discount_total'         => $data['discount_total'],
            'order_count'            => $data['order_count'],
            'completed_count'        => $data['completed_count'],
            'cancelled_count'        => $data['cancelled_count'],
            'average_order_value'    => $data['average_order_value'],
            'cash_revenue'           => $data['cash_revenue'],
            'bank_transfer_revenue'  => $data['bank_transfer_revenue'],
            'card_revenue'           => $data['card_revenue'],
            'ewallet_revenue'        => $data['ewallet_revenue'],
            'top_products'           => $data['top_products'],
            'shift_summary'          => $data['shift_summary'],
            'has_unconfirmed_shifts' => $data['has_unconfirmed_shifts'],
            'comparison'             => $data['comparison'],
            'peak_hour'              => $data['peak_hour'],
        ]);
    }

    private function post(string $endpoint, array $payload): bool
    {
        // Fail fast if Python service is offline to bypass the 3-second timeout
        $isOffline = \Illuminate\Support\Facades\Cache::has('email_service_offline');

        // Thử Python service trước nếu URL được cấu hình và không ở chế độ bảo trì
        if (!$isOffline && !empty($this->baseUrl) && !app(\App\Services\ServiceMonitorService::class)->isMaintenance('email_service')) {
            try {
                $response = Http::timeout(3)->post($this->baseUrl.$endpoint, $payload);

                if ($response->successful()) {
                    Log::info('EmailMicroserviceClient: gửi email thành công qua Python service', [
                        'endpoint' => $endpoint,
                    ]);
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('EmailMicroserviceClient: Python service không khả dụng, chuyển sang Brevo trực tiếp', [
                    'endpoint' => $endpoint,
                ]);
                // Cache the offline status for 5 minutes
                \Illuminate\Support\Facades\Cache::put('email_service_offline', true, 300);
            }
        }

        // Fallback: gọi Brevo API trực tiếp từ Laravel
        return $this->sendViaBrevoDirectly($endpoint, $payload);
    }

    private function sendViaBrevoDirectly(string $endpoint, array $payload): bool
    {
        $apiKey = config('services.brevo.api_key');

        if (empty($apiKey)) {
            Log::error('EmailMicroserviceClient: BREVO_API_KEY chưa cấu hình.');
            return false;
        }

        $fromEmail = config('mail.from.address', 'no-reply@aventura.vn');
        $fromName  = config('mail.from.name', 'Aventura');

        // Map endpoint → subject + nội dung HTML
        [$subject, $html] = $this->buildBrevoContent($endpoint, $payload);

        if (!$subject) {
            Log::error('EmailMicroserviceClient: endpoint không hỗ trợ fallback Brevo', ['endpoint' => $endpoint]);
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['api-key' => $apiKey, 'Content-Type' => 'application/json'])
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender'     => ['name' => $fromName, 'email' => $fromEmail],
                    'to'         => [['email' => $payload['recipient_email'] ?? $payload['email'] ?? '']],
                    'subject'    => $subject,
                    'htmlContent'=> $html,
                ]);

            if ($response->successful()) {
                Log::info('EmailMicroserviceClient: gửi email thành công qua Brevo trực tiếp', ['endpoint' => $endpoint]);
                return true;
            }

            Log::error('EmailMicroserviceClient: Brevo trả lỗi', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('EmailMicroserviceClient: lỗi kết nối Brevo', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function buildBrevoContent(string $endpoint, array $payload): array
    {
        $name = $payload['recipient_name'] ?? $payload['name'] ?? 'Bạn';

        return match ($endpoint) {
            '/send/otp' => [
                'Mã xác thực đăng nhập Aventura',
                "<div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px;background:#fff;border-radius:12px'>
                    <h2 style='color:#4f46e5'>🔐 Mã xác thực 2 lớp</h2>
                    <p>Xin chào <strong>{$name}</strong>,</p>
                    <p>Mã OTP đăng nhập của bạn là:</p>
                    <div style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#4f46e5;text-align:center;padding:16px;background:#f0f0ff;border-radius:8px'>
                        {$payload['code']}
                    </div>
                    <p style='color:#888;font-size:13px;margin-top:16px'>Mã có hiệu lực trong <strong>{$payload['expires_in_minutes']} phút</strong>. Không chia sẻ mã này với ai.</p>
                </div>",
            ],
            '/send/verification' => [
                'Xác thực địa chỉ email Aventura',
                "<div style='font-family:sans-serif;max-width:480px;margin:auto;padding:32px'>
                    <h2 style='color:#4f46e5'>✉️ Xác thực email</h2>
                    <p>Xin chào <strong>{$name}</strong>, mã xác thực của bạn là:</p>
                    <div style='font-size:32px;font-weight:bold;text-align:center;color:#4f46e5;padding:16px;background:#f0f0ff;border-radius:8px'>
                        {$payload['code']}
                    </div>
                </div>",
            ],
            '/send/campaign' => [
                $payload['title'] ?? 'Thông báo hệ thống Aventura',
                "<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #e2e8f0'>
                    <h2 style='color:#4f46e5;margin-bottom:20px;font-size:20px;font-weight:bold;text-align:center'>📢 " . e($payload['title'] ?? 'Thông báo hệ thống') . "</h2>
                    <p>Xin chào <strong>" . e($name) . "</strong>,</p>
                    <div style='font-size:16px;line-height:1.6;color:#334155;margin-top:16px;white-space:pre-wrap'>
                        " . nl2br(e($payload['content'] ?? '')) . "
                    </div>
                    <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0' />
                    <p style='color:#64748b;font-size:12px;text-align:center'>Email này được gửi từ ban quản trị hệ thống SaaS Aventura.</p>
                </div>",
            ],
            default => [null, null],
        };
    }
}
