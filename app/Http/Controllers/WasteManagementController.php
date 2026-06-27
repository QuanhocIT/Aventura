<?php

namespace App\Http\Controllers;

use App\Services\WasteAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WasteManagementController extends Controller
{
    public function __construct(private WasteAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Hao hụt & Lãng phí',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        $dashboard = $this->analytics->getDashboard($restaurantId, $days);
        $trend = $this->analytics->getTrendData($restaurantId, 6);
        $suggestions = $this->analytics->getAiSuggestions($restaurantId);
        $expiring = $this->analytics->getExpiringItems($restaurantId, 3);

        return Inertia::render('waste-management/Index', [
            'dashboard' => $dashboard,
            'trend' => $trend,
            'suggestions' => $suggestions,
            'expiring' => $expiring,
            'days' => $days,
        ]);
    }

    public function apiDashboard(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json(
            $this->analytics->getDashboard($request->user()->restaurant_id, $days)
        );
    }

    public function apiTrend(Request $request): JsonResponse
    {
        $months = (int) ($request->months ?? 6);

        return response()->json(
            $this->analytics->getTrendData($request->user()->restaurant_id, $months)
        );
    }

    public function apiSuggestions(Request $request): JsonResponse
    {
        return response()->json(
            $this->analytics->getAiSuggestions($request->user()->restaurant_id)
        );
    }

    public function apiExpiring(Request $request): JsonResponse
    {
        $days = (int) ($request->days ?? 3);

        return response()->json(
            $this->analytics->getExpiringItems($request->user()->restaurant_id, $days)
        );
    }
}
