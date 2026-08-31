<?php

namespace App\Http\Controllers;

use App\Services\GeoAnalyticsService;
use App\Services\QuotaService;
use App\Support\MaterializedViews\Builders\GeoAnalyticsBuilder;
use App\Support\MaterializedViews\MaterializedViewReader;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeoAnalyticsController extends Controller
{
    public function __construct(
        private GeoAnalyticsService $geo,
        private MaterializedViewReader $mvReader,
        private TenantContext $tenantContext,
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
        $branchId = $this->tenantContext->activeBranchId();
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
            'heatmap' => Inertia::defer(fn () => $this->geo->getOrderHeatmap($restaurantId, $days, $branchId)),
            // zoneStats/topAreas/channels chỉ đọc rollup khi đúng khung mặc định
            // (days=30) — GeoAnalyticsBuilder chỉ materialize cho khung đó, các
            // lựa chọn "days" khác vẫn tính live y hệt hành vi cũ.
            'zoneStats' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId, $branchId)['zone_stats']
                : $this->geo->getDeliveryZoneStats($restaurantId, $days, $branchId)),
            'topAreas' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId, $branchId)['top_areas']
                : $this->geo->getTopAreas($restaurantId, $days, $branchId)),
            'channels' => Inertia::defer(fn () => $days === GeoAnalyticsBuilder::MATERIALIZED_DAYS
                ? $this->mvReader->read('geo_analytics', $restaurantId, $branchId)['channel_breakdown']
                : $this->geo->getChannelBreakdown($restaurantId, $days, $branchId)),
            // branch_suggestions không phụ thuộc $days (cửa sổ 90 ngày cố định
            // trong GeoAnalyticsService::getBranchSuggestions()) nhưng vẫn theo scope.
            'branchSuggestions' => Inertia::defer(fn () => $this->mvReader->read('geo_analytics', $restaurantId, $branchId)['branch_suggestions']),
            'days' => $days,
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }

    public function apiHeatmap(Request $request): JsonResponse
    {
        abort_unless($request->user()->canViewAnalytics(), 403, 'Bạn không có quyền xem dữ liệu phân tích địa lý.');

        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json($this->geo->getOrderHeatmap($request->user()->restaurant_id, $days, $this->tenantContext->activeBranchId()));
    }
}
