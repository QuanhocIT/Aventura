<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\PlatformOrder;
use App\Models\PosDevice;
use App\Models\RestaurantIntegration;
use App\Models\WebhookEndpoint;
use App\Services\DeliveryPlatforms\DeliveryPlatformService;
use App\Services\Integrations\IntegrationRegistry;
use App\Services\Integrations\MisaExportService;
use App\Services\Integrations\ZaloOaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IntegrationSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_unless($restaurantId, 403, 'Không tìm thấy nhà hàng.');

        $devices = PosDevice::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (PosDevice $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'status' => $d->status,
                'pairing_code' => $d->status === 'pending' ? $d->pairing_code : null,
                'is_online' => $d->isOnline(),
                'last_seen_at' => $d->last_seen_at?->diffForHumans(),
                'meta' => $d->meta ?? (object) [],
            ]);

        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->withCount([
                'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->with(['deliveries' => fn ($q) => $q->latest()->limit(10)])
            ->latest()
            ->get()
            ->map(fn (WebhookEndpoint $e) => [
                'id' => $e->id,
                'url' => $e->url,
                'events' => $e->events,
                'is_active' => $e->is_active,
                'description' => $e->description,
                'failed_count' => $e->failed_count,
                'recent_deliveries' => $e->deliveries->map(fn ($d) => [
                    'id' => $d->id,
                    'event' => $d->event,
                    'status' => $d->status,
                    'attempts' => $d->attempts,
                    'response_code' => $d->response_code,
                    'created_at' => $d->created_at->format('d/m H:i'),
                ]),
            ]);

        $platformOrders = PlatformOrder::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->with('order:id,order_number,status,total_amount')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PlatformOrder $po) => [
                'id' => $po->id,
                'provider' => $po->provider,
                'platform_order_id' => $po->platform_order_id,
                'order_number' => $po->order?->order_number,
                'order_status' => $po->order?->status,
                'total_amount' => (float) ($po->order?->total_amount ?? 0),
                'created_at' => $po->created_at->format('d/m H:i'),
            ]);

        $apiKeys = ApiKey::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->latest()
            ->get()
            ->map(fn (ApiKey $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'key_prefix' => $k->key_prefix,
                'is_active' => $k->is_active,
                'last_used_at' => $k->last_used_at?->diffForHumans(),
                'created_at' => $k->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('settings/Integrations', [
            'providers' => IntegrationRegistry::statusFor($restaurantId),
            'devices' => $devices,
            'webhookEndpoints' => $endpoints,
            'webhookEvents' => WebhookEndpoint::EVENTS,
            'apiKeys' => $apiKeys,
            'platformOrders' => $platformOrders,
            'deliveryWebhookUrls' => [
                'grabfood' => url("/api/webhooks/delivery/grabfood/{$restaurantId}"),
                'shopeefood' => url("/api/webhooks/delivery/shopeefood/{$restaurantId}"),
            ],
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $providers = IntegrationRegistry::providers();

        abort_unless(isset($providers[$provider]), 404);

        $restaurantId = $request->user()->restaurant_id;
        $fields = $providers[$provider]['fields'];

        $rules = [];
        foreach ($fields as $field) {
            $rules["credentials.{$field['key']}"] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);
        $incoming = $data['credentials'] ?? [];

        $integration = RestaurantIntegration::withoutGlobalScopes()
            ->firstOrNew(['restaurant_id' => $restaurantId, 'provider' => $provider]);

        $credentials = $integration->credentials ?? [];

        foreach ($fields as $field) {
            $value = $incoming[$field['key']] ?? null;

            // Field secret gửi lên dạng mask '••••' nghĩa là giữ nguyên giá trị cũ
            if ($field['secret'] && $value !== null && str_contains($value, '•')) {
                continue;
            }

            $credentials[$field['key']] = $value !== null ? trim($value) : null;
        }

        $integration->credentials = $credentials;
        $integration->save();

        return back()->with('success', "Đã lưu cấu hình {$providers[$provider]['name']}.");
    }

    /**
     * Kết nối thử nghiệm 1 click: điền credentials demo + bật luôn — dùng để
     * demo/dạy sử dụng khi chưa có tài khoản đối tác thật. Không ghi đè
     * credentials thật nếu đã cấu hình trước đó.
     */
    public function connectDemo(Request $request, string $provider): RedirectResponse
    {
        $providers = IntegrationRegistry::providers();

        abort_unless(isset($providers[$provider]), 404);

        $restaurantId = $request->user()->restaurant_id;

        $integration = RestaurantIntegration::withoutGlobalScopes()
            ->firstOrNew(['restaurant_id' => $restaurantId, 'provider' => $provider]);

        if (empty($integration->credentials)) {
            $demo = [];

            foreach ($providers[$provider]['fields'] as $field) {
                $demo[$field['key']] = 'demo_'.$provider.'_'.$field['key'];
            }

            $integration->credentials = $demo;
        }

        $integration->is_enabled = true;
        $integration->save();

        return back()->with('success', "Đã bật {$providers[$provider]['name']} ở chế độ thử nghiệm (demo). Thay bằng key thật trong Cấu hình khi triển khai chính thức.");
    }

    public function toggle(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(IntegrationRegistry::providers()[$provider]), 404);

        $restaurantId = $request->user()->restaurant_id;

        $integration = RestaurantIntegration::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('provider', $provider)
            ->first();

        if (! $integration || empty($integration->credentials)) {
            return back()->withErrors(['provider' => 'Nhập thông tin cấu hình trước khi bật tích hợp.']);
        }

        $integration->update(['is_enabled' => ! $integration->is_enabled]);

        return back()->with('success', $integration->is_enabled ? 'Đã bật tích hợp.' : 'Đã tắt tích hợp.');
    }

    /** Nút "Kiểm tra kết nối" cho Zalo OA. */
    public function testZalo(Request $request, ZaloOaService $zalo): RedirectResponse
    {
        $integration = RestaurantIntegration::withoutGlobalScopes()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->where('provider', 'zalo_oa')
            ->first();

        if (! $integration) {
            return back()->withErrors(['provider' => 'Chưa cấu hình Zalo OA.']);
        }

        [$ok, $message] = $zalo->testConnection($integration);

        return $ok ? back()->with('success', $message) : back()->withErrors(['provider' => $message]);
    }

    /** Nút "Gửi đơn thử nghiệm" — mô phỏng webhook GrabFood/ShopeeFood. */
    public function simulateOrder(Request $request, DeliveryPlatformService $service): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:grabfood,shopeefood'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        try {
            $payload = $service->buildSimulatedPayload($restaurantId, $data['provider']);
            $platformOrder = $service->ingestOrder($restaurantId, $data['provider'], $payload);

            return back()->with('success', "Đã tạo đơn thử nghiệm #{$platformOrder->platform_order_id} → đơn hàng {$platformOrder->order?->order_number}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['provider' => $e->getMessage()]);
        }
    }

    /** Tải file CSV chứng từ bán hàng chuẩn MISA. */
    public function misaExport(Request $request, MisaExportService $misa): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $restaurantId = $request->user()->restaurant_id;
        $csv = $misa->buildCsv($restaurantId, $data['from'], $data['to']);
        $filename = "misa-chung-tu-ban-hang-{$data['from']}-{$data['to']}.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
