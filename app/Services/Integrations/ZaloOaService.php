<?php

namespace App\Services\Integrations;

use App\Models\Order;
use App\Models\RestaurantIntegration;
use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Gửi tin nhắn chăm sóc khách hàng qua Zalo Official Account (Open API v3).
 * Chưa có access token → ghi log (driver log) để demo luồng không cần OA thật.
 * Có CircuitBreaker: Zalo API lỗi liên tiếp thì ngừng gọi một thời gian.
 */
class ZaloOaService
{
    private const API_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';

    public function __construct(private CircuitBreaker $breaker) {}

    /** Gửi tin văn bản tới user Zalo (user_id do Zalo cấp khi khách follow OA). */
    public function sendMessage(int $restaurantId, string $zaloUserId, string $text): bool
    {
        $integration = RestaurantIntegration::findEnabled($restaurantId, 'zalo_oa');

        if (! $integration || ! $integration->credential('access_token')) {
            Log::channel('daily')->info('[ZALO-OA:LOG]', [
                'restaurant_id' => $restaurantId,
                'user_id' => $zaloUserId,
                'text' => $text,
            ]);

            return true;
        }

        return $this->breaker->for('zalo_oa')->attempt(
            function () use ($integration, $zaloUserId, $text) {
                $response = Http::timeout(10)
                    ->withHeaders(['access_token' => $integration->credential('access_token')])
                    ->post(self::API_URL, [
                        'recipient' => ['user_id' => $zaloUserId],
                        'message' => ['text' => $text],
                    ]);

                if (! $response->successful() || ($response->json('error') ?? 0) !== 0) {
                    throw new \RuntimeException('Zalo OA API error: '.$response->body());
                }

                return true;
            },
            function () use ($restaurantId) {
                Log::warning('[ZALO-OA] Circuit open — bỏ qua gửi tin', ['restaurant_id' => $restaurantId]);

                return false;
            }
        );
    }

    /** Soạn + gửi tin xác nhận đơn hàng cho khách (nếu khách có zalo_user_id). */
    public function sendOrderConfirmation(Order $order): bool
    {
        $order->loadMissing('customer');
        $zaloUserId = $order->customer?->zalo_user_id ?? null;

        $text = "Aventura - {$order->restaurant?->name}\n"
            ."Đơn hàng #{$order->order_number} đã được tiếp nhận.\n"
            .'Tổng tiền: '.number_format((float) $order->total_amount)."đ.\n"
            .'Cảm ơn quý khách!';

        if (! $zaloUserId) {
            // Khách chưa liên kết Zalo — ghi log để demo, không coi là lỗi
            Log::channel('daily')->info('[ZALO-OA:SKIP] Khách chưa liên kết Zalo', [
                'order' => $order->order_number,
            ]);

            return false;
        }

        $rateLimitKey = "zalo_oa_send:{$order->restaurant_id}:{$zaloUserId}";
        $sent = RateLimiter::attempt(
            $rateLimitKey,
            1, // Max attempts
            function () use ($order, $zaloUserId, $text) {
                return $this->sendMessage($order->restaurant_id, $zaloUserId, $text);
            },
            60 // Decay seconds
        );

        if (! $sent) {
            Log::channel('daily')->warning('[ZALO-OA:RATELIMIT] Throttled message to recipient', [
                'recipient' => $zaloUserId,
                'order' => $order->order_number,
            ]);

            return false;
        }

        return true;
    }

    /** Kiểm tra kết nối OA — dùng cho nút "Kiểm tra" trên trang Tích hợp. */
    public function testConnection(RestaurantIntegration $integration): array
    {
        $token = $integration->credential('access_token');

        if (! $token) {
            return [false, 'Chưa nhập Access Token.'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['access_token' => $token])
                ->get('https://openapi.zalo.me/v3.0/oa/getoa');

            if ($response->successful() && ($response->json('error') ?? -1) === 0) {
                $name = $response->json('data.name') ?? 'OA';

                return [true, "Kết nối thành công tới OA \"{$name}\"."];
            }

            return [false, 'Zalo từ chối token: '.($response->json('message') ?? 'không rõ lý do')];
        } catch (\Throwable $e) {
            return [false, 'Không gọi được Zalo API: '.$e->getMessage()];
        }
    }
}
