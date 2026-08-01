<?php

namespace App\Http\Controllers;

use App\Services\PromotionAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionAnalyticsController extends Controller
{
    public function __construct(
        private PromotionAnalyticsService $analytics
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $metrics = $this->analytics->getDashboardMetrics(
            $user->restaurant_id,
            $startDate,
            $endDate
        );

        return Inertia::render('promotions/Analytics', [
            'metrics' => $metrics,
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }
}
