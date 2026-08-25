<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RestaurantBranch;
use App\Support\Tenant\TenantContext;
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
    public function getActiveMenu(int $restaurantId, ?int $branchId = null): array
    {
        $key = $this->buildKey($restaurantId, $branchId);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($restaurantId, $branchId) {
            return Product::where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->where('is_active', true)
                ->sellableMenu()
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
    public function invalidate(int $restaurantId, ?int $branchId = null): void
    {
        Cache::forget($this->buildKey($restaurantId));
        Cache::forget("menu_catalog:restaurant:{$restaurantId}:scope:none");

        if ($branchId !== null) {
            Cache::forget($this->buildKey($restaurantId, $branchId));

            return;
        }

        RestaurantBranch::where('restaurant_id', $restaurantId)
            ->pluck('id')
            ->each(fn ($id) => Cache::forget($this->buildKey($restaurantId, (int) $id)));
    }

    private function buildKey(int $restaurantId, ?int $branchId = null): string
    {
        return "menu_catalog:restaurant:{$restaurantId}:scope:".TenantContext::branchScopeKey($branchId);
    }
}
