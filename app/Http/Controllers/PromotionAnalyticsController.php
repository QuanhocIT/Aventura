<?php

namespace App\Http\Controllers;

use App\Services\PromotionAnalyticsService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PromotionAnalyticsController extends Controller
{
    public function __construct(
        private PromotionAnalyticsService $analytics,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('view_report') || $user->can('manage_orders'), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $user->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }

        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Phân tích hiệu quả khuyến mãi',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $metrics = $this->analytics->getDashboardMetrics(
            $user->restaurant_id,
            $startDate,
            $endDate,
            $this->tenantContext->activeBranchId(),
        );

        return Inertia::render('promotions/Analytics', [
            'metrics' => $metrics,
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $this->tenantContext->activeBranchId(),
            ],
        ]);
    }

    public function recalculate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can('view_report') || $user->can('manage_orders'), 403);

        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $this->analytics->generateSnapshotsForPeriod(
            $user->restaurant_id,
            $startDate,
            $endDate,
            $this->tenantContext->activeBranchId(),
        );

        return back()->with('success', 'Đã cập nhật & tính toán lại toàn bộ dữ liệu phân tích khuyến mãi.');
    }
}
