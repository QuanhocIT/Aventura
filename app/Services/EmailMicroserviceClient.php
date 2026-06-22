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
        $name = $payload['recipient_name'] ?? $payload['name'] ?? $payload['guest_name'] ?? 'Bạn';
        $restaurantName = $payload['restaurant_name'] ?? 'Nhà hàng';

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

            // ── Reservation ──────────────────────────────────────────────────
            '/send/reservation-reminder' => [
                "⏰ Nhắc nhở: Bàn của bạn tại {$restaurantName} sắp đến giờ",
                "<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:0'>
                    <div style='background:linear-gradient(135deg,#f59e0b,#d97706);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
                        <div style='font-size:48px'>🍽️</div>
                        <h1 style='color:#fff;margin:8px 0;font-size:24px'>Nhắc nhở đặt bàn</h1>
                    </div>
                    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #fde68a'>
                        <p style='color:#374151'>Xin chào <strong>" . e($name) . "</strong>,</p>
                        <p style='color:#374151'>Chúng tôi nhắc bạn rằng bạn có đặt bàn tại <strong>" . e($restaurantName) . "</strong> sắp đến:</p>
                        <div style='background:#fef3c7;border-radius:8px;padding:20px;margin:20px 0;border-left:4px solid #f59e0b'>
                            <div style='color:#92400e;font-size:14px'>📅 Ngày: <strong>" . e($payload['reservation_date'] ?? '') . "</strong></div>
                            <div style='color:#92400e;font-size:14px;margin-top:8px'>⏰ Giờ: <strong>" . e($payload['reservation_time'] ?? '') . "</strong></div>
                            <div style='color:#92400e;font-size:14px;margin-top:8px'>👥 Số người: <strong>" . e($payload['party_size'] ?? '') . " khách</strong></div>
                            " . ($payload['table_name'] ? "<div style='color:#92400e;font-size:14px;margin-top:8px'>🪑 Bàn: <strong>" . e($payload['table_name']) . "</strong></div>" : '') . "
                        </div>
                        " . ($payload['special_requests'] ? "<p style='color:#6b7280;font-size:13px'>✨ Yêu cầu đặc biệt: " . e($payload['special_requests']) . "</p>" : '') . "
                        <p style='color:#374151;margin-top:16px'>Nếu bạn cần thay đổi hoặc hủy bàn, vui lòng liên hệ nhà hàng trước ít nhất 1 giờ.</p>
                        <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0'>
                        <p style='color:#9ca3af;font-size:12px;text-align:center'>Email tự động từ hệ thống Aventura · " . e($restaurantName) . "</p>
                    </div>
                </div>",
            ],
            '/send/reservation-confirmation' => [
                "✅ Xác nhận đặt bàn tại {$restaurantName}",
                "<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:0'>
                    <div style='background:linear-gradient(135deg,#10b981,#059669);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
                        <div style='font-size:48px'>✅</div>
                        <h1 style='color:#fff;margin:8px 0;font-size:24px'>Đặt bàn đã được xác nhận!</h1>
                    </div>
                    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #d1fae5'>
                        <p>Xin chào <strong>" . e($name) . "</strong>,</p>
                        <p>Nhà hàng <strong>" . e($restaurantName) . "</strong> đã xác nhận đặt bàn của bạn:</p>
                        <div style='background:#f0fdf4;border-radius:8px;padding:20px;margin:20px 0;border-left:4px solid #10b981'>
                            <div style='color:#065f46;font-size:14px'>📅 " . e($payload['reservation_date'] ?? '') . " lúc " . e($payload['reservation_time'] ?? '') . "</div>
                            <div style='color:#065f46;font-size:14px;margin-top:8px'>👥 " . e($payload['party_size'] ?? '') . " khách</div>
                            " . ($payload['table_name'] ? "<div style='color:#065f46;font-size:14px;margin-top:8px'>🪑 Bàn: " . e($payload['table_name']) . "</div>" : '') . "
                        </div>
                        " . ($payload['internal_notes'] ? "<p style='color:#6b7280;font-size:13px;font-style:italic'>📝 Ghi chú: " . e($payload['internal_notes']) . "</p>" : '') . "
                        <p style='color:#9ca3af;font-size:12px;text-align:center;margin-top:24px'>Hẹn gặp bạn tại " . e($restaurantName) . " 🍽️</p>
                    </div>
                </div>",
            ],
            '/send/reservation-cancellation' => [
                "❌ Thông báo hủy đặt bàn tại {$restaurantName}",
                "<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #fecaca'>
                    <h2 style='color:#dc2626'>❌ Đặt bàn đã bị hủy</h2>
                    <p>Xin chào <strong>" . e($name) . "</strong>,</p>
                    <p>Rất tiếc, đặt bàn của bạn tại <strong>" . e($restaurantName) . "</strong> vào ngày <strong>" . e($payload['reservation_date'] ?? '') . "</strong> lúc <strong>" . e($payload['reservation_time'] ?? '') . "</strong> đã bị hủy.</p>
                    " . ($payload['reason'] ? "<div style='background:#fef2f2;padding:16px;border-radius:8px;border-left:4px solid #dc2626;margin:16px 0'><p style='color:#991b1b;margin:0'>Lý do: " . e($payload['reason']) . "</p></div>" : '') . "
                    <p style='color:#6b7280;margin-top:16px'>Để đặt bàn lại, vui lòng liên hệ nhà hàng hoặc quét mã QR trên bàn.</p>
                    <p style='color:#9ca3af;font-size:12px;margin-top:24px'>Xin lỗi về sự bất tiện này. Chúng tôi hy vọng được phục vụ bạn lần tới.</p>
                </div>",
            ],

            // ── CRM ──────────────────────────────────────────────────────────
            '/send/birthday-voucher' => [
                "🎂 Chúc mừng sinh nhật từ {$restaurantName}!",
                "<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:0'>
                    <div style='background:linear-gradient(135deg,#ec4899,#8b5cf6);padding:40px;border-radius:12px 12px 0 0;text-align:center'>
                        <div style='font-size:56px'>🎂</div>
                        <h1 style='color:#fff;margin:12px 0;font-size:26px'>Chúc mừng sinh nhật!</h1>
                        <p style='color:#fce7f3;font-size:16px'>Chúng tôi yêu quý bạn ❤️</p>
                    </div>
                    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #f9a8d4'>
                        <p style='color:#374151;font-size:16px'>Xin chào <strong>" . e($name) . "</strong>,</p>
                        <p style='color:#374151'>Nhân dịp sinh nhật của bạn, <strong>" . e($restaurantName) . "</strong> xin tặng bạn một món quà đặc biệt:</p>
                        <div style='background:linear-gradient(135deg,#fdf2f8,#f5f3ff);border:2px dashed #ec4899;border-radius:12px;padding:24px;margin:24px 0;text-align:center'>
                            <div style='font-size:14px;color:#9ca3af;letter-spacing:2px;text-transform:uppercase'>Voucher sinh nhật</div>
                            <div style='font-size:48px;font-weight:900;color:#ec4899;margin:8px 0'>" . e($payload['discount'] ?? '20%') . "</div>
                            <div style='font-size:14px;color:#6b7280'>GIẢM GIÁ cho lần ghé thăm tiếp theo</div>
                            <div style='margin-top:16px;padding:12px 24px;background:#ec4899;color:#fff;border-radius:8px;font-weight:bold;font-size:18px;letter-spacing:4px;display:inline-block'>" . e($payload['voucher_code'] ?? 'BIRTHDAY') . "</div>
                            <div style='font-size:12px;color:#9ca3af;margin-top:8px'>Hiệu lực đến: " . e($payload['expires_at'] ?? '') . "</div>
                        </div>
                        <p style='color:#9ca3af;font-size:12px;text-align:center'>Chúc bạn một ngày sinh nhật thật vui vẻ và hạnh phúc! 🎉</p>
                    </div>
                </div>",
            ],
            '/send/review-request' => [
                "⭐ Cảm ơn bạn đã ghé thăm {$restaurantName}! Chia sẻ trải nghiệm của bạn",
                "<div style='font-family:sans-serif;max-width:560px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:1px solid #e5e7eb'>
                    <div style='text-align:center;margin-bottom:24px'>
                        <div style='font-size:48px'>⭐</div>
                        <h2 style='color:#1f2937;margin:8px 0'>Bạn thấy trải nghiệm hôm nay thế nào?</h2>
                        <p style='color:#6b7280'>Ý kiến của bạn giúp chúng tôi ngày càng hoàn thiện hơn</p>
                    </div>
                    <p style='color:#374151'>Xin chào <strong>" . e($name) . "</strong>,</p>
                    <p style='color:#374151'>Cảm ơn bạn đã ghé thăm <strong>" . e($restaurantName) . "</strong> vào hôm nay. Chúng tôi hy vọng bạn có một trải nghiệm tuyệt vời!</p>
                    <div style='text-align:center;margin:24px 0'>
                        <a href='" . e($payload['review_url'] ?? '#') . "' style='display:inline-block;background:#4f46e5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px'>
                            Đánh giá ngay →
                        </a>
                    </div>
                    <p style='color:#9ca3af;font-size:12px;text-align:center'>Chỉ mất 30 giây · Giúp ích rất nhiều cho chúng tôi</p>
                </div>",
            ],

            // ── Operations ───────────────────────────────────────────────────
            '/send/low-stock-alert' => [
                "🚨 [{$restaurantName}] Cảnh báo: " . count($payload['items'] ?? []) . " nguyên liệu sắp hết",
                "<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fca5a5'>
                    <h2 style='color:#dc2626'>🚨 Cảnh báo tồn kho thấp</h2>
                    <p>Nhà hàng <strong>" . e($restaurantName) . "</strong> có <strong>" . count($payload['items'] ?? []) . " nguyên liệu</strong> cần đặt hàng gấp:</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                        <thead>
                            <tr style='background:#fef2f2'>
                                <th style='padding:10px;text-align:left;border:1px solid #fecaca;color:#991b1b'>Nguyên liệu</th>
                                <th style='padding:10px;text-align:right;border:1px solid #fecaca;color:#991b1b'>Còn lại</th>
                                <th style='padding:10px;text-align:right;border:1px solid #fecaca;color:#991b1b'>Mức tối thiểu</th>
                            </tr>
                        </thead>
                        <tbody>
                            " . implode('', array_map(fn($item) =>
                                "<tr>
                                    <td style='padding:8px 10px;border:1px solid #fee2e2'>" . e($item['name'] ?? '') . "</td>
                                    <td style='padding:8px 10px;border:1px solid #fee2e2;text-align:right;color:#dc2626;font-weight:bold'>" . e($item['current'] ?? '') . " " . e($item['unit'] ?? '') . "</td>
                                    <td style='padding:8px 10px;border:1px solid #fee2e2;text-align:right;color:#6b7280'>" . e($item['min'] ?? '') . " " . e($item['unit'] ?? '') . "</td>
                                </tr>",
                            $payload['items'] ?? [])) . "
                        </tbody>
                    </table>
                    <a href='" . e($payload['inventory_url'] ?? '#') . "' style='display:inline-block;background:#dc2626;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:8px'>Vào quản lý kho →</a>
                    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động từ hệ thống Aventura · " . now()->format('d/m/Y H:i') . "</p>
                </div>",
            ],
            '/send/weekly-digest' => [
                "📊 Báo cáo tuần " . ($payload['week_label'] ?? '') . " · {$restaurantName}",
                "<div style='font-family:sans-serif;max-width:600px;margin:auto;padding:0'>
                    <div style='background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px;border-radius:12px 12px 0 0;text-align:center'>
                        <div style='font-size:40px'>📊</div>
                        <h1 style='color:#fff;margin:8px 0;font-size:22px'>Báo cáo tuần</h1>
                        <p style='color:#c4b5fd;font-size:14px'>" . e($payload['week_label'] ?? '') . " · " . e($restaurantName) . "</p>
                    </div>
                    <div style='background:#fff;padding:32px;border-radius:0 0 12px 12px;border:1px solid #e0e7ff'>
                        <div style='display:grid;gap:12px;margin-bottom:24px'>
                            <div style='background:#f5f3ff;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                                <span style='color:#6b7280'>💰 Doanh thu</span>
                                <strong style='color:#4f46e5'>" . number_format($payload['revenue'] ?? 0, 0, ',', '.') . "đ</strong>
                            </div>
                            <div style='background:#f0fdf4;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                                <span style='color:#6b7280'>📦 Tổng đơn</span>
                                <strong style='color:#059669'>" . ($payload['order_count'] ?? 0) . " đơn</strong>
                            </div>
                            <div style='background:#fff7ed;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                                <span style='color:#6b7280'>⭐ NPS trung bình</span>
                                <strong style='color:#ea580c'>" . ($payload['avg_rating'] ?? '—') . "/5.0</strong>
                            </div>
                            <div style='background:#fdf2f8;padding:16px;border-radius:8px;display:flex;justify-content:space-between'>
                                <span style='color:#6b7280'>📈 So tuần trước</span>
                                <strong style='color:" . (($payload['revenue_trend'] ?? 0) >= 0 ? '#059669' : '#dc2626') . "'>" . (($payload['revenue_trend'] ?? 0) >= 0 ? '↑' : '↓') . " " . abs($payload['revenue_trend'] ?? 0) . "%</strong>
                            </div>
                        </div>
                        " . (!empty($payload['top_products']) ? "
                        <h3 style='color:#374151;font-size:14px;margin-bottom:12px'>🏆 Top món bán chạy tuần này</h3>
                        <ol style='padding-left:16px;color:#4b5563;font-size:14px'>
                            " . implode('', array_map(fn($p) => "<li style='margin-bottom:4px'>" . e($p['name'] ?? '') . " <span style='color:#9ca3af'>(" . ($p['quantity'] ?? 0) . " phần)</span></li>", array_slice($payload['top_products'] ?? [], 0, 5))) . "
                        </ol>" : '') . "
                        <p style='color:#9ca3af;font-size:12px;text-align:center;margin-top:24px'>Báo cáo tự động mỗi thứ Hai · Aventura</p>
                    </div>
                </div>",
            ],
            '/send/absence-alert' => [
                "⚠️ [{$restaurantName}] Nhân viên vắng không phép lúc " . now()->format('H:i'),
                "<div style='font-family:sans-serif;max-width:540px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fde68a'>
                    <h2 style='color:#d97706'>⚠️ Cảnh báo vắng mặt không phép</h2>
                    <p>Nhà hàng <strong>" . e($restaurantName) . "</strong></p>
                    <p>Các nhân viên sau <strong>chưa check-in</strong> sau " . ($payload['minutes_late'] ?? 15) . " phút kể từ giờ bắt đầu ca:</p>
                    <ul style='background:#fffbeb;padding:16px 24px;border-radius:8px;border-left:4px solid #f59e0b'>
                        " . implode('', array_map(fn($e) => "<li style='margin-bottom:6px;color:#92400e'><strong>" . e($e['name'] ?? '') . "</strong> — Ca " . e($e['shift'] ?? '') . "</li>", $payload['employees'] ?? [])) . "
                    </ul>
                    <p style='color:#374151;margin-top:16px'>Vui lòng liên hệ nhân viên ngay hoặc phân công người thay thế.</p>
                    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động · " . now()->format('d/m/Y H:i') . "</p>
                </div>",
            ],
            '/send/revenue-anomaly' => [
                "📉 [{$restaurantName}] Doanh thu hôm nay thấp bất thường — cần chú ý",
                "<div style='font-family:sans-serif;max-width:540px;margin:auto;padding:32px;background:#fff;border-radius:12px;border:2px solid #fca5a5'>
                    <h2 style='color:#dc2626'>📉 Cảnh báo doanh thu bất thường</h2>
                    <p>Nhà hàng <strong>" . e($restaurantName) . "</strong></p>
                    <div style='background:#fef2f2;padding:20px;border-radius:8px;margin:16px 0'>
                        <p style='color:#991b1b;margin:0'>Doanh thu hiện tại: <strong>" . number_format($payload['current_revenue'] ?? 0, 0, ',', '.') . "đ</strong></p>
                        <p style='color:#991b1b;margin:8px 0 0'>Trung bình cùng ngày tuần trước: <strong>" . number_format($payload['expected_revenue'] ?? 0, 0, ',', '.') . "đ</strong></p>
                        <p style='color:#dc2626;font-size:18px;font-weight:bold;margin:8px 0 0'>Chênh lệch: ↓ " . ($payload['drop_percent'] ?? 0) . "%</p>
                    </div>
                    <p style='color:#374151'>Đây có thể do lý do đặc biệt (ngày lễ, thời tiết...) hoặc cần kiểm tra vận hành.</p>
                    <p style='color:#9ca3af;font-size:12px;margin-top:16px'>Cảnh báo tự động lúc 15:00 · Aventura</p>
                </div>",
            ],

            default => [null, null],
        };
    }
}
