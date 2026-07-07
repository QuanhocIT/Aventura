<?php

namespace App\Services\DeliveryPlatforms;

use App\Models\RestaurantIntegration;
use Illuminate\Http\Request;

interface DeliveryPlatformDriver
{
    public function getDisplayName(): string;

    /** Xác thực chữ ký webhook từ nền tảng bằng secret trong cấu hình tích hợp. */
    public function verifyWebhook(Request $request, RestaurantIntegration $integration): bool;

    /**
     * Chuẩn hóa payload đơn hàng của nền tảng về cấu trúc chung:
     * ['platform_order_id', 'customer_name', 'customer_phone', 'note', 'items' => [['name','quantity','price','note']], 'total']
     */
    public function parseOrder(array $payload): array;

    /** Đẩy trạng thái đơn (accepted/preparing/ready/completed/cancelled) về nền tảng. */
    public function pushOrderStatus(RestaurantIntegration $integration, string $platformOrderId, string $status): bool;
}
