<?php

namespace App\Http\Controllers;

use App\Services\MenuInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuInsightController extends Controller
{
    public function __construct(private MenuInsightService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $user->restaurant;
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'ai_advisor')) {
            return response()->json(['error' => 'Tính năng AI Tư vấn yêu cầu gói Chuyên Nghiệp trở lên.', 'feature' => 'ai_advisor'], 403);
        }

        $restaurantId = $user->restaurant_id;
        $days         = (int) $request->input('days', 30);

        return response()->json([
            'insights' => $this->service->getInsights($restaurantId, $days),
            'bcg'      => $this->service->getBcgData($restaurantId, $days),
            'margins'  => $this->service->getProductMargins($restaurantId, $days),
        ]);
    }
}
