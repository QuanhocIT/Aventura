<?php

namespace App\Http\Controllers;

use App\Models\BusinessGoal;
use App\Models\CashRegister;
use App\Models\Inventory;
use App\Models\OperationalInfringementReport;
use App\Models\Order;
use App\Models\RestaurantBranch;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnterpriseCommandCenterController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canViewChainCommandCenter(), 403, 'Chỉ Chủ doanh nghiệp, Giám sát viên hoặc Kế toán mới có quyền truy cập Trung tâm điều hành chuỗi.');

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $user->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Trung tâm điều hành chuỗi (Command Center)',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)->get();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $branchScorecards = $branches->map(function ($branch) use ($restaurantId, $startOfMonth, $endOfMonth) {
            // Doanh thu tháng
            $revenue = (float) Order::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branch->id)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total_amount');

            // Mục tiêu doanh thu
            $targetGoal = BusinessGoal::where('restaurant_id', $restaurantId)
                ->where('metric', 'revenue')
                ->where('status', 'active')
                ->first();
            $targetRevenue = $targetGoal ? (float) $targetGoal->target_value : 0.0;

            // Chênh lệch két tiền
            $cashDiscrepancy = (float) CashRegister::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branch->id)
                ->whereBetween('opened_at', [$startOfMonth, $endOfMonth])
                ->sum('difference');

            // Tồn kho sắp hết hàng (dưới ngưỡng min_stock_level của nguyên liệu)
            $lowStockCount = Inventory::where('inventories.branch_id', $branch->id)
                ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->whereColumn('inventories.quantity_on_hand', '<=', 'ingredients.min_stock_level')
                ->count();

            // Vi phạm đang mở
            $openInfringements = OperationalInfringementReport::where('restaurant_id', $restaurantId)
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['closed', 'passed', 'rejected'])
                ->count();

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'revenue' => $revenue,
                'target_revenue' => $targetRevenue,
                'target_completion_percent' => $targetRevenue > 0 ? round(($revenue / $targetRevenue) * 100, 1) : 0,
                'cash_discrepancy' => $cashDiscrepancy,
                'low_stock_count' => $lowStockCount,
                'open_infringements' => $openInfringements,
                'status' => $openInfringements > 3 || abs($cashDiscrepancy) > 500000 ? 'warning' : 'healthy',
            ];
        });

        $totalRevenue = $branchScorecards->sum('revenue');
        $totalOpenInfringements = $branchScorecards->sum('open_infringements');
        $totalLowStock = $branchScorecards->sum('low_stock_count');

        return Inertia::render('enterprise/CommandCenter', [
            'scorecards' => $branchScorecards,
            'summary' => [
                'total_branches' => $branches->count(),
                'total_revenue' => $totalRevenue,
                'total_open_infringements' => $totalOpenInfringements,
                'total_low_stock' => $totalLowStock,
            ],
        ]);
    }
}
