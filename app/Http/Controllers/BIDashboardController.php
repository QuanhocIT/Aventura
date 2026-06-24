<?php

namespace App\Http\Controllers;

use App\Services\BusinessIntelligenceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BIDashboardController extends Controller
{
    public function __construct(private BusinessIntelligenceService $bi) {}

    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return Inertia::render('bi-dashboard/Index', [
            'revenueTrend' => $this->bi->getRevenueTrend($restaurantId, 12),
            'unitEconomics' => $this->bi->getUnitEconomics($restaurantId, $days),
            'cohorts' => $this->bi->getCohortAnalysis($restaurantId),
            'breakEven' => $this->bi->getBreakEvenAnalysis($restaurantId, $days),
            'benchmarks' => $this->bi->getBenchmark($restaurantId, $days),
            'days' => $days,
        ]);
    }
}
