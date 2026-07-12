<?php

namespace App\Services\Integrations;

use App\Models\RestaurantIntegration;

/**
 * Danh mục tất cả integration bên thứ 3 mà Aventura hỗ trợ.
 * Là nguồn sự thật duy nhất cho trang Settings → Tích hợp:
 * key, tên hiển thị, nhóm, và các trường credential cần nhập.
 */
class IntegrationRegistry
{
    /**
     * @return array<string, array{name: string, category: string, description: string, fields: array<array{key: string, label: string, secret: bool}>}>
     */
    public static function providers(): array
    {
        return [
            'grabfood' => [
                'name' => 'Grab Food',
                'category' => 'Giao hàng',
                'description' => 'Nhận đơn GrabFood trực tiếp vào màn hình đơn hàng, tự động đồng bộ trạng thái.',
                'guide' => 'Đăng ký đối tác tại GrabMerchant → mục Developer → tạo ứng dụng lấy Partner ID + Webhook Secret. Sau khi bật, dán URL webhook (mục bên dưới) vào cấu hình GrabFood.',
                'fields' => [
                    ['key' => 'partner_id', 'label' => 'Partner ID', 'secret' => false],
                    ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'secret' => true],
                ],
            ],
            'shopeefood' => [
                'name' => 'ShopeeFood',
                'category' => 'Giao hàng',
                'description' => 'Nhận đơn ShopeeFood trực tiếp vào màn hình đơn hàng, tự động đồng bộ trạng thái.',
                'guide' => 'Liên hệ ShopeeFood Merchant Support xin quyền Open API → nhận Store ID + Webhook Secret → dán URL webhook (mục bên dưới) vào hệ thống Shopee.',
                'fields' => [
                    ['key' => 'store_id', 'label' => 'Store ID', 'secret' => false],
                    ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'secret' => true],
                ],
            ],
            'google_analytics' => [
                'name' => 'Google Analytics 4',
                'category' => 'Analytics',
                'description' => 'Theo dõi lượt truy cập và doanh thu cửa hàng online bằng GA4.',
                'guide' => 'Vào analytics.google.com → Quản trị → Luồng dữ liệu web → copy Measurement ID (G-XXXX). API Secret tạo ở mục "Measurement Protocol API secrets" (dùng cho tracking server-side).',
                'fields' => [
                    ['key' => 'measurement_id', 'label' => 'Measurement ID (G-XXXX)', 'secret' => false],
                    ['key' => 'api_secret', 'label' => 'Measurement Protocol API Secret', 'secret' => true],
                ],
            ],
            'facebook_pixel' => [
                'name' => 'Facebook Pixel',
                'category' => 'Marketing',
                'description' => 'Gắn Pixel vào cửa hàng online để chạy quảng cáo retargeting trên Facebook.',
                'guide' => 'Vào business.facebook.com → Trình quản lý sự kiện → tạo Pixel → copy Pixel ID. Token Conversions API tạo trong phần Cài đặt Pixel (tùy chọn, để track server-side).',
                'fields' => [
                    ['key' => 'pixel_id', 'label' => 'Pixel ID', 'secret' => false],
                    ['key' => 'access_token', 'label' => 'Conversions API Token (tùy chọn)', 'secret' => true],
                ],
            ],
            'zalo_oa' => [
                'name' => 'Zalo Official Account',
                'category' => 'CRM',
                'description' => 'Gửi tin nhắn xác nhận đơn hàng, chăm sóc khách hàng qua Zalo OA.',
                'guide' => 'Tạo Official Account tại oa.zalo.me → developers.zalo.me tạo ứng dụng liên kết OA → lấy Access Token. Dùng nút ✈ để kiểm tra kết nối sau khi lưu.',
                'fields' => [
                    ['key' => 'oa_id', 'label' => 'OA ID', 'secret' => false],
                    ['key' => 'access_token', 'label' => 'Access Token', 'secret' => true],
                ],
            ],
            'misa' => [
                'name' => 'MISA',
                'category' => 'Kế toán',
                'description' => 'Xuất chứng từ bán hàng chuẩn MISA SME / AMIS để nhập liệu kế toán.',
                'guide' => 'Xuất file CSV (mục "Xuất chứng từ MISA" cuối trang) dùng được NGAY không cần key — nhập trực tiếp vào MISA SME. App ID/Access Key chỉ cần khi đồng bộ tự động qua AMIS Platform (đăng ký tại actapp.misa.vn).',
                'fields' => [
                    ['key' => 'app_id', 'label' => 'AMIS App ID (tùy chọn)', 'secret' => false],
                    ['key' => 'access_key', 'label' => 'AMIS Access Key (tùy chọn)', 'secret' => true],
                ],
            ],
        ];
    }

    /** Trạng thái cấu hình của mọi provider cho một nhà hàng — dữ liệu cho trang settings. */
    public static function statusFor(int $restaurantId): array
    {
        $existing = RestaurantIntegration::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->get()
            ->keyBy('provider');

        $result = [];

        foreach (static::providers() as $key => $meta) {
            $integration = $existing->get($key);

            $result[] = [
                'provider' => $key,
                'name' => $meta['name'],
                'category' => $meta['category'],
                'description' => $meta['description'],
                'guide' => $meta['guide'] ?? null,
                'fields' => $meta['fields'],
                'is_enabled' => (bool) $integration?->is_enabled,
                'is_configured' => $integration !== null && ! empty($integration->credentials),
                // Chỉ trả về field không nhạy cảm để hiển thị lại trên form
                'values' => $integration ? static::publicValues($key, $integration) : (object) [],
                'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    private static function publicValues(string $provider, RestaurantIntegration $integration): array
    {
        $fields = static::providers()[$provider]['fields'] ?? [];
        $values = [];

        foreach ($fields as $field) {
            if ($field['secret']) {
                // Không lộ secret — chỉ báo đã nhập hay chưa
                $values[$field['key']] = $integration->credential($field['key']) ? '••••••••' : '';
            } else {
                $values[$field['key']] = $integration->credential($field['key']) ?? '';
            }
        }

        return $values;
    }
}
