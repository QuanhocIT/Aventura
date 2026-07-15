<?php

namespace App\Http\Controllers;

use App\Services\GeoAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeoAnalyticsController extends Controller
{
    public function __construct(private GeoAnalyticsService $geo) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
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
            'heatmap' => Inertia::defer(fn() => $this->geo->getOrderHeatmap($restaurantId, $days)),
            'zoneStats' => Inertia::defer(fn() => $this->geo->getDeliveryZoneStats($restaurantId, $days)),
            'topAreas' => Inertia::defer(fn() => $this->geo->getTopAreas($restaurantId, $days)),
            'channels' => Inertia::defer(fn() => $this->geo->getChannelBreakdown($restaurantId, $days)),
            'branchSuggestions' => Inertia::defer(fn() => $this->geo->getBranchSuggestions($restaurantId)),
            'days' => $days,
        ]);
    }

    public function apiHeatmap(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json($this->geo->getOrderHeatmap($request->user()->restaurant_id, $days));
    }
}
