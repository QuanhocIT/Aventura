<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    // ── Reservation Reminder ─────────────────────────────────────────────────

    /**
     * Gửi email nhắc nhở đặt bàn 2 giờ trước giờ hẹn.
     */
    public function sendReservationReminder(array $data): bool
    {
        return $this->post('/send/reservation-reminder', $data);
    }

    /**
     * Gửi email xác nhận đặt bàn thành công.
     */
    public function sendReservationConfirmation(array $data): bool
    {
        return $this->post('/send/reservation-confirmation', $data);
    }

    /**
     * Gửi email thông báo hủy đặt bàn.
     */
    public function sendReservationCancellation(array $data): bool
    {
        return $this->post('/send/reservation-cancellation', $data);
    }

    // ── CRM ──────────────────────────────────────────────────────────────────

    /**
     * Gửi email chúc mừng sinh nhật kèm voucher.
     */
    public function sendBirthdayVoucher(array $data): bool
    {
        return $this->post('/send/birthday-voucher', $data);
    }

    /**
     * Gửi email mời đánh giá sau khi khách thanh toán.
     */
    public function sendReviewRequest(array $data): bool
    {
        return $this->post('/send/review-request', $data);
    }

    // ── Operations ───────────────────────────────────────────────────────────

    /**
     * Gửi email cảnh báo tồn kho thấp cho Owner/Manager.
     */
    public function sendLowStockAlert(array $data): bool
    {
        return $this->post('/send/low-stock-alert', $data);
    }

    /**
     * Gửi báo cáo tuần (Weekly Digest) cho Owner.
     */
    public function sendWeeklyDigest(array $data): bool
    {
        return $this->post('/send/weekly-digest', $data);
    }

    /**
     * Gửi cảnh báo nhân viên vắng không phép cho Manager.
     */
    public function sendAbsenceAlert(array $data): bool
    {
        return $this->post('/send/absence-alert', $data);
    }

    /**
     * Gửi cảnh báo doanh thu bất thường cho Owner.
     */
    public function sendRevenueAnomalyAlert(array $data): bool
    {
        return $this->post('/send/revenue-anomaly', $data);
    }

    private function post(string $endpoint, array $payload): bool
    {
        if (empty($this->baseUrl) || app(\App\Services\ServiceMonitorService::class)->isMaintenance('email_service')) {
            return $this->sendViaBrevoDirectly($endpoint, $payload);
        }

        return app(CircuitBreaker::class)->for('email_service')->attempt(
            function () use ($endpoint, $payload) {
                $response = Http::timeout(3)->post($this->baseUrl.$endpoint, $payload);

                if (!$response->successful()) {
                    throw new \RuntimeException("Email microservice trả lỗi HTTP {$response->status()}");
                }

                Log::info('EmailMicroserviceClient: gửi email thành công qua Python service', [
                    'endpoint' => $endpoint,
                ]);

                return true;
            },
            function () use ($endpoint, $payload) {
                Log::warning('EmailMicroserviceClient: Python service không khả dụng, chuyển sang Brevo trực tiếp', [
                    'endpoint' => $endpoint,
                ]);

                return $this->sendViaBrevoDirectly($endpoint, $payload);
            }
        );
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

            Log::warning('EmailMicroserviceClient: Brevo trả lỗi, chuyển sang SMTP', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('EmailMicroserviceClient: lỗi kết nối Brevo, chuyển sang SMTP', ['error' => $e->getMessage()]);
        }

        return $this->sendViaSmtp($endpoint, $payload);
    }

    private function sendViaSmtp(string $endpoint, array $payload): bool
    {
        [$subject, $html] = $this->buildBrevoContent($endpoint, $payload);

        if (!$subject || !$html) {
            Log::error('EmailMicroserviceClient: không thể render email cho SMTP fallback', ['endpoint' => $endpoint]);
            return false;
        }

        $to = $payload['recipient_email'] ?? $payload['email'] ?? '';
        if (empty($to)) {
            return false;
        }

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info('EmailMicroserviceClient: gửi email thành công qua SMTP fallback', ['endpoint' => $endpoint]);
            return true;
        } catch (\Throwable $e) {
            Log::error('EmailMicroserviceClient: SMTP fallback thất bại', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function buildBrevoContent(string $endpoint, array $payload): array
    {
        $name = $payload['recipient_name'] ?? $payload['name'] ?? $payload['guest_name'] ?? 'Bạn';
        $restaurantName = $payload['restaurant_name'] ?? 'Nhà hàng';

        $subject = match ($endpoint) {
            '/send/otp' => 'Mã xác thực đăng nhập Aventura',
            '/send/verification' => 'Xác thực địa chỉ email Aventura',
            '/send/campaign' => $payload['title'] ?? 'Thông báo hệ thống Aventura',
            '/send/daily-report' => "📊 Báo cáo doanh thu ngày " . ($payload['report_date'] ?? '') . " · {$restaurantName}",
            '/send/reservation-reminder' => "⏰ Nhắc nhở: Bàn của bạn tại {$restaurantName} sắp đến giờ",
            '/send/reservation-confirmation' => "✅ Xác nhận đặt bàn tại {$restaurantName}",
            '/send/reservation-cancellation' => "❌ Thông báo hủy đặt bàn tại {$restaurantName}",
            '/send/birthday-voucher' => "🎂 Chúc mừng sinh nhật từ {$restaurantName}!",
            '/send/review-request' => "⭐ Cảm ơn bạn đã ghé thăm {$restaurantName}! Chia sẻ trải nghiệm của bạn",
            '/send/low-stock-alert' => "🚨 [{$restaurantName}] Cảnh báo: " . count($payload['items'] ?? []) . " nguyên liệu sắp hết",
            '/send/weekly-digest' => "📊 Báo cáo tuần " . ($payload['week_label'] ?? '') . " · {$restaurantName}",
            '/send/absence-alert' => "⚠️ [{$restaurantName}] Nhân viên vắng không phép lúc " . now()->format('H:i'),
            '/send/revenue-anomaly' => "📉 [{$restaurantName}] Doanh thu hôm nay thấp bất thường — cần chú ý",
            default => null,
        };

        if (!$subject) {
            return [null, null];
        }

        $viewName = match ($endpoint) {
            '/send/otp' => 'emails.otp',
            '/send/verification' => 'emails.verification',
            '/send/campaign' => 'emails.campaign',
            '/send/daily-report' => 'emails.reports.daily',
            '/send/reservation-reminder' => 'emails.reservation_reminder',
            '/send/reservation-confirmation' => 'emails.reservation_confirmation',
            '/send/reservation-cancellation' => 'emails.reservation_cancellation',
            '/send/birthday-voucher' => 'emails.birthday_voucher',
            '/send/review-request' => 'emails.review_request',
            '/send/low-stock-alert' => 'emails.low_stock_alert',
            '/send/weekly-digest' => 'emails.weekly_digest',
            '/send/absence-alert' => 'emails.absence_alert',
            '/send/revenue-anomaly' => 'emails.revenue_anomaly',
            default => null,
        };

        if (!$viewName) {
            return [null, null];
        }

        try {
            $html = view($viewName, $payload)->render();
            return [$subject, $html];
        } catch (\Throwable $e) {
            Log::error('EmailMicroserviceClient: lỗi render email view', [
                'view' => $viewName,
                'error' => $e->getMessage()
            ]);
            return [null, null];
        }
    }
}

