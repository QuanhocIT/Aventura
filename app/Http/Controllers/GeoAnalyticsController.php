<?php

namespace App\Http\Controllers;

use App\Services\GeoAnalyticsService;
use App\Services\QuotaService;
use App\Support\MaterializedViews\Builders\GeoAnalyticsBuilder;
use App\Support\MaterializedViews\MaterializedViewReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeoAnalyticsController extends Controller
{
    public function __construct(
        private GeoAnalyticsService $geo,
        private MaterializedViewReader $mvReader,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->canViewAnalytics(), 403, 'Bạn không có quyền xem dữ liệu phân tích địa lý.');

        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Phân tích Địa lý',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        $restaurant = $request->user()->restaurant;
        $lat = (float) ($restaurant->latitude ?? 0.0);
        $lng = (float) ($restaurant->longitude ?? 0.0);
        if ($lat === 0.0 || $lng === 0.0) {
            $lat = 10.776889;
            $lng = 106.700806;
        }

        return Inertia::render('geo-analytics/Index', [
            'restaurant' => [
                'lat' => $lat,
                'lng' => $lng,
                'name' => $restaurant->name,
            ],
            'heatmap' => Inertia::defer(fn () => $this->geo->getOrderHeatmap($restaurantId, $days)),
            // zoneStats/topAreas/channels chỉ đọc rollup khi đúng khung mặc định
            // (days=30) — GeoAnalyticsBuilder chỉ materialize cho khung đó, các
            // lựa chọn "days" khác vẫn tính live y hệt hành vi cũ.
            'zoneStats' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId)['zone_stats']
                : $this->geo->getDeliveryZoneStats($restaurantId, $days)),
            'topAreas' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId)['top_areas']
                : $this->geo->getTopAreas($restaurantId, $days)),
            'channels' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId)['channel_breakdown']
                : $this->geo->getChannelBreakdown($restaurantId, $days)),
            // branch_suggestions không phụ thuộc $days (cửa sổ 90 ngày cố định
            // trong GeoAnalyticsService::getBranchSuggestions()) nên luôn đọc rollup.
            'branchSuggestions' => Inertia::defer(fn () => $this->mvReader->read('geo_analytics', $restaurantId)['branch_suggestions']),
            'days' => $days,
        ]);
    }

    public function apiHeatmap(Request $request): JsonResponse
    {
        abort_unless($request->user()->canViewAnalytics(), 403, 'Bạn không có quyền xem dữ liệu phân tích địa lý.');

        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json($this->geo->getOrderHeatmap($request->user()->restaurant_id, $days));
    }
}
