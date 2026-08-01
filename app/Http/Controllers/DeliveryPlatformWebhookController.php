<?php

namespace App\Http\Controllers;

use App\Models\RestaurantIntegration;
use App\Services\DeliveryPlatforms\DeliveryPlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook công khai nhận đơn hàng mới từ GrabFood / ShopeeFood.
 * URL cấp cho nền tảng: POST /api/webhooks/delivery/{provider}/{restaurantId}
 */
class DeliveryPlatformWebhookController extends Controller
{
    public function __construct(private DeliveryPlatformService $service) {}

    public function handle(Request $request, string $provider, int $restaurantId): JsonResponse
    {
        if (! in_array($provider, $this->service->supportedProviders(), true)) {
            return response()->json(['message' => 'Provider không hỗ trợ.'], 404);
        }

        $integration = RestaurantIntegration::findEnabled($restaurantId, $provider);

        if (! $integration) {
            return response()->json(['message' => 'Tích hợp chưa được bật cho nhà hàng này.'], 403);
        }

        if (! $this->service->resolveDriver($provider)->verifyWebhook($request, $integration)) {
            Log::warning('[DELIVERY-WEBHOOK] Sai chữ ký', [
                'provider' => $provider,
                'restaurant_id' => $restaurantId,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Chữ ký không hợp lệ.'], 401);
        }

        try {
            $platformOrder = $this->service->ingestOrder($restaurantId, $provider, $request->all());

            return response()->json([
                'success' => true,
                'platform_order_id' => $platformOrder->platform_order_id,
                'order_id' => $platformOrder->order_id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[DELIVERY-WEBHOOK] Lỗi xử lý đơn', [
                'provider' => $provider,
                'restaurant_id' => $restaurantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Lỗi hệ thống khi xử lý đơn.'], 500);
        }
    }
}
