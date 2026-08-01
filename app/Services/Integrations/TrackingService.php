<?php

namespace App\Services\Integrations;

use App\Models\Order;
use App\Models\RestaurantIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analytics server-side: gửi sự kiện purchase tới GA4 (Measurement Protocol)
 * và Facebook Conversions API khi đơn online được thanh toán. Client-side
 * (gtag/fbq trên storefront) do useTracking composable đảm nhiệm.
 */
class TrackingService
{
    /** ID cấu hình tracking công khai cho storefront của một nhà hàng. */
    public function storefrontConfig(int $restaurantId): array
    {
        $ga = RestaurantIntegration::findEnabled($restaurantId, 'google_analytics');
        $pixel = RestaurantIntegration::findEnabled($restaurantId, 'facebook_pixel');

        return [
            'ga_measurement_id' => $ga?->credential('measurement_id'),
            'fb_pixel_id' => $pixel?->credential('pixel_id'),
        ];
    }

    /** Gửi sự kiện purchase server-side sau khi đơn được thanh toán thành công. */
    public function trackPurchase(Order $order): void
    {
        $this->sendGa4Purchase($order);
        $this->sendFacebookPurchase($order);
    }

    private function sendGa4Purchase(Order $order): void
    {
        $integration = RestaurantIntegration::findEnabled($order->restaurant_id, 'google_analytics');
        $measurementId = $integration?->credential('measurement_id');
        $apiSecret = $integration?->credential('api_secret');

        if (! $measurementId || ! $apiSecret) {
            return;
        }

        try {
            Http::timeout(5)->post(
                "https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}",
                [
                    'client_id' => 'srv.'.$order->id,
                    'events' => [[
                        'name' => 'purchase',
                        'params' => [
                            'transaction_id' => $order->order_number,
                            'currency' => 'VND',
                            'value' => (float) $order->total_amount,
                        ],
                    ]],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[GA4] Gửi purchase thất bại', ['error' => $e->getMessage()]);
        }
    }

    private function sendFacebookPurchase(Order $order): void
    {
        $integration = RestaurantIntegration::findEnabled($order->restaurant_id, 'facebook_pixel');
        $pixelId = $integration?->credential('pixel_id');
        $token = $integration?->credential('access_token');

        if (! $pixelId || ! $token) {
            return;
        }

        try {
            Http::timeout(5)->post(
                "https://graph.facebook.com/v18.0/{$pixelId}/events",
                [
                    'access_token' => $token,
                    'data' => [[
                        'event_name' => 'Purchase',
                        'event_time' => now()->timestamp,
                        'action_source' => 'website',
                        'custom_data' => [
                            'currency' => 'VND',
                            'value' => (float) $order->total_amount,
                            'order_id' => $order->order_number,
                        ],
                    ]],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[FB-CAPI] Gửi purchase thất bại', ['error' => $e->getMessage()]);
        }
    }
}
