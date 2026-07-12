<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SecurityAlertService
{
    /**
     * Send a security alert via Telegram Webhook when an IP is blocked.
     */
    public static function sendWafBlockAlert(string $ip, int $attempts, int $decay, int $blockMinutes): void
    {
        $botToken = env('TELEGRAM_BOT_TOKEN') ?: SystemSetting::get('telegram_bot_token');
        $chatId = env('TELEGRAM_CHAT_ID') ?: SystemSetting::get('telegram_chat_id');

        if (!$botToken || !$chatId) {
            return;
        }

        $time = now()->format('Y-m-d H:i:s');
        $message = "⚠️ *CẢNH BÁO BẢO MẬT WAF (AVENTURA)* ⚠️\n\n"
            . "• *Hành động*: Khóa kết nối IP tạm thời\n"
            . "• *IP bị chặn*: `{$ip}`\n"
            . "• *Lý do*: Đăng nhập thất bại liên tiếp ({$attempts} lần trong {$decay} giây)\n"
            . "• *Thời gian chặn*: `{$blockMinutes} phút`\n"
            . "• *Thời điểm*: `{$time}`\n\n"
            . "👉 Vui lòng kiểm tra nhật ký hệ thống (Audit Logs) hoặc giao diện quản lý Firewall để biết thêm chi tiết.";

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send Telegram security alert: " . $e->getMessage());
        }
    }
}
