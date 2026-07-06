<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $driver = config('services.sms.driver', 'log');

        return match ($driver) {
            'twilio' => $this->sendViaTwilio($phone, $message),
            'vonage' => $this->sendViaVonage($phone, $message),
            default => $this->sendViaLog($phone, $message),
        };
    }

    public function sendOrderStatus(string $phone, string $orderNumber, string $status): bool
    {
        $statusLabels = [
            'confirmed' => 'đã được xác nhận',
            'preparing' => 'đang được chuẩn bị',
            'picked_up' => 'shipper đã lấy hàng',
            'delivered' => 'đã giao thành công',
            'cancelled' => 'đã bị hủy',
        ];

        $label = $statusLabels[$status] ?? $status;
        $message = "Aventura: Đơn #{$orderNumber} {$label}. Theo dõi: " . url("/order/track/{$orderNumber}");

        return $this->send($phone, $message);
    }

    public function sendOtp(string $phone, string $code): bool
    {
        return $this->send($phone, "Aventura: Mã xác thực của bạn là {$code}. Có hiệu lực 5 phút.");
    }

    private function sendViaLog(string $phone, string $message): bool
    {
        Log::channel('daily')->info('[SMS:LOG]', compact('phone', 'message'));
        return true;
    }

    private function sendViaTwilio(string $phone, string $message): bool
    {
        try {
            $sid = config('services.sms.twilio.sid');
            $token = config('services.sms.twilio.token');
            $from = config('services.sms.twilio.from');

            Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $phone,
                    'From' => $from,
                    'Body' => $message,
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS:TWILIO] Failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendViaVonage(string $phone, string $message): bool
    {
        try {
            Http::post('https://rest.nexmo.com/sms/json', [
                'api_key' => config('services.sms.vonage.key'),
                'api_secret' => config('services.sms.vonage.secret'),
                'to' => $phone,
                'from' => config('services.sms.vonage.from', 'Aventura'),
                'text' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS:VONAGE] Failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
