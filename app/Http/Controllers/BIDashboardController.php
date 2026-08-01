<?php

namespace App\Http\Controllers;

use App\Services\BusinessIntelligenceService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BIDashboardController extends Controller
{
    public function __construct(private BusinessIntelligenceService $bi) {}

    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'BI Dashboard',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));
        $branchId = $tenantContext->activeBranchId();

        return Inertia::render('bi-dashboard/Index', [
            'revenueTrend' => Inertia::defer(fn () => $this->bi->getRevenueTrend($restaurantId, 12, $branchId)),
            'unitEconomics' => Inertia::defer(fn () => $this->bi->getUnitEconomics($restaurantId, $days, $branchId)),
            'cohorts' => Inertia::defer(fn () => $this->bi->getCohortAnalysis($restaurantId, $branchId)),
            'breakEven' => Inertia::defer(fn () => $this->bi->getBreakEvenAnalysis($restaurantId, $days, $branchId)),
            'benchmarks' => Inertia::defer(fn () => $this->bi->getBenchmark($restaurantId, $days, $branchId)),
            'days' => $days,
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }
}
