<?php

namespace App\Support\MaterializedViews\Builders;

use App\Services\GeoAnalyticsService;
use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use Carbon\CarbonInterface;

/**
 * Materialize 3/5 hàm trong GeoAnalyticsService (zone stats/top areas/channel
 * breakdown/branch suggestions) — trước đây HOÀN TOÀN KHÔNG cache (chỉ riêng
 * getOrderHeatmap() đã có Cache::remember 300s sẵn trong service, không đụng tới).
 *
 * LƯU Ý phạm vi: GeoAnalyticsController cho phép người dùng chọn số ngày phân
 * tích tuỳ ý (?days=1..365) — dòng tổng hợp ở đây CHỈ đại diện cho days=30
 * (giá trị mặc định của trang). Controller tự phát hiện days=30 thì đọc rollup,
 * ngoài ra vẫn gọi trực tiếp GeoAnalyticsService (y hệt hành vi cũ, không đổi).
 * branch_suggestions không phụ thuộc $days (cửa sổ 90 ngày cố định trong chính
 * GeoAnalyticsService::getBranchSuggestions()) nên luôn lấy từ rollup.
 */
class GeoAnalyticsBuilder implements MaterializedViewBuilder
{
    public const MATERIALIZED_DAYS = 30;

    public function __construct(
        private readonly GeoAnalyticsService $geo,
    ) {}

    public function scopeKey(?int $branchId): string
    {
        return 'restaurant';
    }

    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array
    {
        return [
            'zone_stats' => $this->geo->getDeliveryZoneStats($restaurantId, self::MATERIALIZED_DAYS),
            'top_areas' => $this->geo->getTopAreas($restaurantId, self::MATERIALIZED_DAYS),
            'channel_breakdown' => $this->geo->getChannelBreakdown($restaurantId, self::MATERIALIZED_DAYS),
            'branch_suggestions' => $this->geo->getBranchSuggestions($restaurantId),
        ];
    }
}
