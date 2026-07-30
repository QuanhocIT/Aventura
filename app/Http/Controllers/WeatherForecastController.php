<?php

namespace App\Http\Controllers;

use App\Services\WeatherForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherForecastController extends Controller
{
    public function __construct(
        private readonly WeatherForecastService $forecastService
    ) {}

    /**
     * Lấy dự báo thời tiết và khuyến nghị món ăn.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->restaurant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Người dùng không thuộc về bất kỳ nhà hàng nào.',
            ], 403);
        }

        $result = $this->forecastService->getForecast((int) $user->restaurant_id);

        return response()->json($result);
    }
}
