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
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurantId = $request->user()->restaurant_id;
        $days         = (int) $request->input('days', 30);

        return response()->json([
            'insights' => $this->service->getInsights($restaurantId, $days),
            'bcg'      => $this->service->getBcgData($restaurantId, $days),
            'margins'  => $this->service->getProductMargins($restaurantId, $days),
        ]);
    }
}
