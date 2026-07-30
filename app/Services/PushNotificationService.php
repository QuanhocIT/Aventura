<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function send(User $user, string $title, string $body, array $data = []): bool
    {
        if (empty($user->device_token)) {
            return false;
        }

        $driver = config('services.push.driver', 'log');

        return match ($driver) {
            'fcm' => $this->sendViaFcm($user->device_token, $title, $body, $data),
            default => $this->sendViaLog($user, $title, $body),
        };
    }

    public function sendToMany(array $tokens, string $title, string $body, array $data = []): int
    {
        $sent = 0;
        foreach (array_chunk($tokens, 500) as $chunk) {
            $driver = config('services.push.driver', 'log');
            if ($driver === 'fcm') {
                $sent += $this->sendBatchFcm($chunk, $title, $body, $data);
            } else {
                Log::info('[PUSH:LOG] Batch', ['count' => count($chunk), 'title' => $title]);
                $sent += count($chunk);
            }
        }

        return $sent;
    }

    public function sendOrderUpdate(User $user, string $orderNumber, string $status): bool
    {
        $labels = [
            'confirmed' => 'Đơn đã xác nhận',
            'preparing' => 'Đang chuẩn bị món',
            'picked_up' => 'Shipper đã lấy hàng',
            'delivered' => 'Đã giao thành công',
            'cancelled' => 'Đơn bị hủy',
        ];

        return $this->send($user, $labels[$status] ?? $status, "Đơn #{$orderNumber}", ['order' => $orderNumber, 'status' => $status]);
    }

    private function sendViaLog(User $user, string $title, string $body): bool
    {
        Log::channel('daily')->info('[PUSH:LOG]', ['user_id' => $user->id, 'title' => $title, 'body' => $body]);

        return true;
    }

    private function sendViaFcm(string $token, string $title, string $body, array $data): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.config('services.push.fcm.server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
                'data' => $data,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('[PUSH:FCM] Failed', ['token' => substr($token, 0, 20).'...', 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendBatchFcm(array $tokens, string $title, string $body, array $data): int
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.config('services.push.fcm.server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => ['title' => $title, 'body' => $body, 'sound' => 'default'],
                'data' => $data,
            ]);

            return $response->successful() ? count($tokens) : 0;
        } catch (\Throwable $e) {
            Log::error('[PUSH:FCM] Batch failed', ['count' => count($tokens), 'error' => $e->getMessage()]);

            return 0;
        }
    }
}
