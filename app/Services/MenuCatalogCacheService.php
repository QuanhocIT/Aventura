<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Cache layer cho Thực đơn (Menu Catalog) & Sơ đồ bàn để giảm 90% read queries
 * vào MySQL trong giờ cao điểm khi thu ngân và khách quét QR liên tục.
 */
class MenuCatalogCacheService
{
    public const TTL_SECONDS = 3600; // 1 giờ

    /**
     * Lấy danh sách sản phẩm đang kinh doanh theo nhà hàng (có cache Redis).
     */
    public function getActiveMenu(int $restaurantId): array
    {
        $key = $this->buildKey($restaurantId);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($restaurantId) {
            return Product::where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->with(['category:id,name'])
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'category_id' => $p->category_id,
                    'category_name' => $p->category?->name ?? 'Khác',
                    'is_paused' => (bool) $p->is_paused,
                    'is_out_of_stock' => (bool) $p->is_out_of_stock,
                    'paused_until' => $p->paused_until?->toIso8601String(),
                    'out_of_stock_until' => $p->out_of_stock_until?->toIso8601String(),
                ])
                ->toArray();
        });
    }

    /**
     * Invalidate cache menu khi sản phẩm hoặc danh mục có thay đổi.
     */
    public function invalidate(int $restaurantId): void
    {
        Cache::forget($this->buildKey($restaurantId));
    }

    private function buildKey(int $restaurantId): string
    {
        return "menu_catalog:restaurant:{$restaurantId}";
    }
}
