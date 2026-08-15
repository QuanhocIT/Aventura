<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantRevenueSummary;
use App\Models\RestaurantTable;
use App\Models\ScheduleAssignment;
use App\Models\ViolationReport;
use App\Services\Dashboard\DashboardAlertService;
use App\Services\Dashboard\DashboardChartService;
use App\Services\Dashboard\DashboardHealthService;
use App\Services\Dashboard\DashboardSummaryService;
use App\Services\ForecastService;
use App\Services\OrderStatsCacheService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private ForecastService $forecast,
        private QuotaService $quotaService,
        private OrderStatsCacheService $orderStatsCache,
        private DashboardChartService $chartService,
        private DashboardHealthService $healthService,
        private DashboardSummaryService $summaryService,
        private DashboardAlertService $alertService,
        private TenantContext $tenantContext,
    ) {}

    private function deferProp(callable $callback, ?string $group = null)
    {
        if (app()->runningUnitTests()) {
            return $callback();
        }

        return $group ? Inertia::defer($callback, group: $group) : Inertia::defer($callback);
    }

    public function index(Request $request): mixed
    {
        $user = $request->user();
        $restaurant = $user?->restaurant;

        if ($user && $user->isSuperAdmin()) {
            return redirect('/super-admin/dashboard');
        }

        if ($user && $user->hasRole('supplier')) {
            return redirect()->route('supplier.dashboard');
        }

        if ($user && $user->hasRole('operations_inspector')) {
            return redirect()->route('operations.audit');
        }

        if ($user && $user->hasAnyRole(['warehouse_manager', 'warehouse_staff'])) {
            return redirect()->route('inventory.central-warehouse');
        }

        if ($user && $user->can('manage_kitchen') && ! $user->can('view_report')) {
            return redirect()->route('kitchen.index');
        }

        if ($user && $user->can('create_orders') && ! $user->can('view_report')) {
            return app(CashierDashboardController::class)->index($request);
        }

        // Nhân viên kho: đưa thẳng vào trang quản lý Tồn kho (giống pattern Kitchen/Cashier ở trên)
        // thay vì rơi vào dashboard đầy đủ kiểu Owner (báo cáo tài chính, so sánh chi nhánh...).
        if ($user && $user->hasRole('inventory_staff') && ! $user->can('view_report')) {
            return redirect()->route('inventory.index');
        }

        $hasAdvancedAnalytics = $restaurant && $this->quotaService->hasFeature($restaurant, 'advanced_analytics');
        $hasAiForecasting = $restaurant && $this->quotaService->hasFeature($restaurant, 'ai_forecasting');
        $hasHrTimekeeping = $restaurant && $this->quotaService->hasFeature($restaurant, 'hr_timekeeping');
        $hasInventoryBasic = $restaurant && $this->quotaService->hasFeature($restaurant, 'inventory_basic');

        // TenantContext is the single source of truth for the active scope.
        // Keep the query parameter as a backwards-compatible migration path
        // for old bookmarked dashboard links; the UI no longer owns this state.
        $branchId = $this->tenantContext->activeBranchId();

        if ($restaurant && $user->canViewAllBranches() && $request->has('branch_id')) {
            $requestedBranch = $request->query('branch_id');
            if ($requestedBranch === 'all' || $requestedBranch === '') {
                session()->forget('active_branch_id');
                session(['active_branch_scope' => TenantContext::SCOPE_ALL]);
                $this->tenantContext->setAllBranches();
                $branchId = null;
            } elseif (is_numeric($requestedBranch)) {
                $requestedBranchId = (int) $requestedBranch;
                $isValidBranch = $restaurant->branches()
                    ->whereKey($requestedBranchId)
                    ->where('status', 'active')
                    ->exists();

                abort_unless($isValidBranch, 403, 'Chi nhánh không thuộc nhà hàng của bạn.');
                session([
                    'active_branch_id' => $requestedBranchId,
                    'active_branch_scope' => TenantContext::SCOPE_BRANCH,
                ]);
                $this->tenantContext->setActiveBranchId($requestedBranchId);
                $branchId = $requestedBranchId;
            }
        }

        if (! $user->canViewAllBranches()) {
            $branchId = $this->tenantContext->activeBranchId();
        }

        $stats = null;
        $recentOrders = [];
        $alerts = [];
        $revenueChartData = [];
        $channelChartData = [];
        $topProductsChartData = [];
        $operationFeed = [];
        $tablesData = [];
        $lowStockInventory = [];
        $forecastData = null;
        $healthScore = null;
        $shiftRevenue = [];
        $ownerSummary = null;
        $branches = [];
        $branchComparisons = [];

        $rid = $restaurant?->id;
        if ($restaurant) {
            $branches = $user->canViewAllBranches()
                ? $restaurant->branches()->get(['id', 'name'])->toArray()
                : $restaurant->branches()
                    ->whereKey($branchId)
                    ->get(['id', 'name'])
                    ->toArray();

            // Today summary & Yesterday summary for active branch/consolidated
            $todaySummaryQuery = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('scope_key', TenantContext::summaryScopeKey($branchId))
                ->where('summary_date', today())
                ->where('summary_type', 'daily');

            $yesterdaySummaryQuery = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('scope_key', TenantContext::summaryScopeKey($branchId))
                ->where('summary_date', today()->subDay())
                ->where('summary_type', 'daily');

            $todaySummary = $todaySummaryQuery->first();
            $yesterdaySummary = $yesterdaySummaryQuery->first();

            // ── Live order counts (cached 5 phút, invalidate khi có đơn mới) ────────
            // Thay vì 3 COUNT query riêng lẻ mỗi lần load dashboard, dùng 1 query
            // tổng hợp được cache → giảm tải DB khi nhiều nhà hàng online đồng thời.
            $todayLiveStats = $this->orderStatsCache->getTodayStats($rid, $branchId);

            $totalToday = $todayLiveStats['total'];
            $completedToday = $todayLiveStats['completed'];
            $cancelledToday = $todayLiveStats['cancelled'];

            // ── Xu hướng so hôm qua ─────────────────────────────────────────
            $revenueToday = (float) ($todaySummary?->net_revenue ?? 0);
            $revenueYesterday = (float) ($yesterdaySummary?->net_revenue ?? 0);
            $revTrend = $revenueYesterday > 0
                ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1)
                : null;

            $ordersYesterday = (int) ($yesterdaySummary?->completed_order_count ?? 0);
            $orderTrend = $ordersYesterday > 0
                ? round(($completedToday - $ordersYesterday) / $ordersYesterday * 100, 1)
                : null;

            // ── Biên lợi nhuận hôm nay ──────────────────────────────────────
            $grossProfit = (float) ($todaySummary?->gross_profit ?? 0);
            $profitMargin = $revenueToday > 0
                ? round($grossProfit / $revenueToday * 100, 1)
                : 0.0;

            $completionRate = $totalToday > 0
                ? round($completedToday / $totalToday * 100, 1)
                : 0.0;

            // Cache resource counts per branch or consolidated
            $cacheSuffix = $branchId ? ":{$branchId}" : '';
            $resourceCounts = Cache::remember("dashboard_counts:{$rid}{$cacheSuffix}", 300, function () use ($restaurant, $branchId) {
                $productsQuery = $restaurant->products();
                if ($branchId !== null) {
                    $productsQuery->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    });
                }

                return [
                    // Products with NULL branch_id are chain-wide menu items
                    // and are visible from every concrete branch.
                    'products_count' => $productsQuery->count(),
                    'employees_count' => $restaurant->employees()->where('status', 'active')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
                    'branches_count' => $restaurant->branches()->count(),
                    'tables_count' => $restaurant->tables()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
                ];
            });

            $stats = array_merge($resourceCounts, [
                'orders_today' => $totalToday,
                'revenue_today' => $revenueToday,
                'orders_completed' => $completedToday,
                'orders_cancelled' => $cancelledToday,
                'revenue_trend' => $revTrend,
                'order_trend' => $orderTrend,
                'profit_margin_today' => $profitMargin,
                'completion_rate' => $completionRate,
            ]);

            // Alerts calculation moved to deferred method getDashboardAlerts below
            $alerts = [];

            // ── Recent orders ────────────────────────────────────────────────
            $recentOrders = Order::with(['table', 'branch:id,name'])
                ->where('restaurant_id', $rid)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'branch_id' => $o->branch_id,
                    'branch_name' => $o->branch?->name ?? ($branchId === null ? 'Chưa xác định' : '—'),
                    'table_name' => $o->table?->name ?? null,
                    'total_amount' => (float) $o->total_amount,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'channel' => $o->channel,
                    'created_at' => $o->created_at?->format('H:i'),
                ])->all();
        }

        $onboardingStatus = $user?->onboarding_status ?? [];
        $onboardingComplete = ! $user?->can('approve_requests') || (
            ! empty($onboardingStatus['day_1']['completed_at'])
            && ! empty($onboardingStatus['day_2']['completed_at'])
            && ! empty($onboardingStatus['day_3']['completed_at'])
        );

        return Inertia::render('Dashboard', [
            'branchId' => $branchId,
            'branches' => $branches,
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
            'stats' => $stats,
            'onboardingComplete' => $onboardingComplete,
            'recentOrders' => $recentOrders,
            'alerts' => $this->deferProp(fn () => $restaurant ? $this->alertService->getDashboardAlerts($restaurant, $branchId, $hasAiForecasting, $hasAdvancedAnalytics, $hasHrTimekeeping, $hasInventoryBasic) : [], 'quick_feed'),

            // Defer all heavy analytical & reporting data to improve initial page load performance
            'branchComparisons' => $this->deferProp(function () use ($rid, $branches, $hasAdvancedAnalytics, $branchId) {
                $branchComparisons = [];
                if (! $branchId && count($branches) > 1 && $hasAdvancedAnalytics) {
                    $branchIds = array_column($branches, 'id');

                    $revenuesByBranch = Order::where('restaurant_id', $rid)
                        ->whereIn('branch_id', $branchIds)
                        ->where('status', 'completed')
                        ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                        ->groupBy('branch_id')
                        ->selectRaw('branch_id, SUM(total_amount) as total_revenue')
                        ->pluck('total_revenue', 'branch_id');

                    $summariesByBranch = RestaurantRevenueSummary::where('restaurant_id', $rid)
                        ->whereIn('branch_id', $branchIds)
                        ->where('summary_date', today())
                        ->where('summary_type', 'daily')
                        ->get()
                        ->keyBy('branch_id');

                    $cogsByBranch = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->join('products', 'order_items.product_id', '=', 'products.id')
                        ->where('orders.restaurant_id', $rid)
                        ->whereIn('orders.branch_id', $branchIds)
                        ->where('orders.status', 'completed')
                        ->whereBetween('orders.created_at', [today()->startOfDay(), today()->endOfDay()])
                        ->groupBy('orders.branch_id')
                        ->selectRaw('orders.branch_id, SUM(order_items.quantity * products.cost_price) as total_cogs')
                        ->pluck('total_cogs', 'branch_id');

                    $violationsByBranch = ViolationReport::where('violation_reports.restaurant_id', $rid)
                        ->whereHas('employee', function ($q) use ($branchIds) {
                            $q->whereIn('branch_id', $branchIds);
                        })
                        ->where('violation_reports.occurred_at', '>=', now()->subDays(30))
                        ->join('employees', 'violation_reports.employee_id', '=', 'employees.id')
                        ->groupBy('employees.branch_id')
                        ->selectRaw('employees.branch_id, COUNT(*) as count')
                        ->pluck('count', 'branch_id');

                    $branchHealthScores = $this->healthService->getHealthScoresForBranches($rid, $branchIds);

                    foreach ($branches as $b) {
                        $bId = $b['id'];
                        $bRevToday = (float) ($revenuesByBranch[$bId] ?? 0.0);
                        $bTodaySummary = $summariesByBranch->get($bId);

                        $bGrossProfit = $bTodaySummary ? (float) $bTodaySummary->gross_profit : 0.0;
                        $bMargin = $bRevToday > 0 ? round(($bGrossProfit / $bRevToday) * 100, 1) : 0.0;

                        if (! $bTodaySummary && $bRevToday > 0) {
                            $totalCogs = (float) ($cogsByBranch[$bId] ?? 0.0);
                            $bGrossProfit = $bRevToday - $totalCogs;
                            $bMargin = round(($bGrossProfit / $bRevToday) * 100, 1);
                        }

                        $bHealth = $branchHealthScores[$bId] ?? 75;
                        $bViolations = (int) ($violationsByBranch[$bId] ?? 0);

                        $branchComparisons[] = [
                            'id' => $bId,
                            'name' => $b['name'],
                            'revenue' => $bRevToday,
                            'profit_margin' => $bMargin,
                            'health_score' => $bHealth,
                            'violations_count' => $bViolations,
                        ];
                    }
                }

                return $branchComparisons;
            }, 'analytics'),

            'operationFeed' => $this->deferProp(function () use ($rid, $branchId, $hasInventoryBasic, $hasHrTimekeeping) {
                $feedItems = [];
                $ordersForFeed = Order::with('table')
                    ->where('restaurant_id', $rid)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->latest()
                    ->take(8)
                    ->get();

                $channelLabels = [
                    'dine_in' => 'Tại bàn', 'takeaway' => 'Mang về',
                    'delivery' => 'Giao hàng', 'qr' => 'Mã QR',
                ];

                foreach ($ordersForFeed as $o) {
                    $tblStr = $o->table ? " tại Bàn {$o->table->name}" : '';
                    $chanStr = $channelLabels[$o->channel] ?? $o->channel;
                    $time = $o->updated_at ?? $o->created_at;
                    $statusMap = [
                        'pending' => ['Đơn mới chờ duyệt', 'order_pending', 'ShoppingCart', 'amber', '/orders?status=pending'],
                        'preparing' => ['Đang chuẩn bị món', 'order_preparing', 'Utensils', 'violet', '/orders'],
                        'completed' => ['Đơn hoàn thành', 'order_completed', 'CheckCircle2', 'emerald', '/orders'],
                        'cancelled' => ['Đơn bị hủy', 'order_cancelled', 'XCircle', 'rose', '/orders?status=cancelled'],
                    ];
                    if (isset($statusMap[$o->status])) {
                        [$title, $type, $icon, $color, $link] = $statusMap[$o->status];
                        $feedItems[] = [
                            'type' => $type, 'title' => $title, 'icon' => $icon,
                            'color' => $color, 'link' => $link,
                            'description' => "Đơn #{$o->order_number}{$tblStr} ({$chanStr} — ".number_format($o->total_amount).'đ)',
                            'amount' => (float) $o->total_amount,
                            'time' => $time->diffForHumans(),
                            'timestamp' => $time->timestamp,
                        ];
                    }
                }

                $lowStocksForFeed = [];
                if ($hasInventoryBasic) {
                    $lowStocksForFeed = Inventory::query()
                        ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                        ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                        ->where('inventories.restaurant_id', $rid)
                        ->when($branchId, fn ($q) => $q->where('inventories.branch_id', $branchId))
                        ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                        ->select('ingredients.name as ingredient_name', 'inventories.quantity_on_hand',
                            'ingredients.min_stock_level', 'units.name as unit_name', 'inventories.updated_at')
                        ->take(4)->get();
                }

                foreach ($lowStocksForFeed as $item) {
                    $time = $item->updated_at ?? now();
                    $feedItems[] = [
                        'type' => 'stock_warning', 'title' => 'Cảnh báo hết nguyên liệu',
                        'icon' => 'AlertTriangle', 'color' => 'rose', 'link' => '/inventory',
                        'description' => "\"{$item->ingredient_name}\" còn ".round($item->quantity_on_hand, 2).' '.($item->unit_name ?? 'đv'),
                        'amount' => null, 'time' => $time->diffForHumans(),
                        'timestamp' => Carbon::parse($time)->timestamp,
                    ];
                }

                $activeSchedulesForFeed = [];
                if ($hasHrTimekeeping) {
                    $activeSchedulesForFeed = ScheduleAssignment::with(['employee', 'shift'])
                        ->where('restaurant_id', $rid)->where('scheduled_date', today())
                        ->whereNotNull('check_in_at')
                        ->whereHas('employee', fn ($emp) => $emp->when($branchId, fn ($q) => $q->where('branch_id', $branchId)))
                        ->latest('check_in_at')
                        ->take(4)
                        ->get();
                }

                foreach ($activeSchedulesForFeed as $sa) {
                    $time = $sa->check_in_at;
                    $feedItems[] = [
                        'type' => 'shift_checkin', 'title' => 'Nhân sự vào ca',
                        'icon' => 'Users', 'color' => 'sky', 'link' => '/employees',
                        'description' => "{$sa->employee?->full_name} check-in ca ".($sa->shift?->name ?? '').' lúc '.$time->format('H:i'),
                        'amount' => null, 'time' => $time->diffForHumans(),
                        'timestamp' => $time->timestamp,
                    ];
                }

                usort($feedItems, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

                return array_slice($feedItems, 0, 8);
            }, 'quick_feed'),

            'tablesData' => $this->deferProp(fn () => RestaurantTable::with(['area', 'activeOrder'])
                ->where('restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('name')
                ->get()
                ->map(function ($t) {
                    $status = $t->status;
                    $activeOrder = $t->activeOrder;
                    if ($activeOrder) {
                        $status = 'occupied';
                    } elseif ($status === 'occupied') {
                        $status = 'available';
                    }

                    return [
                        'id' => $t->id, 'name' => $t->name,
                        'area' => $t->area?->name ?? 'Khu vực chung',
                        'capacity' => $t->capacity, 'status' => $status,
                    ];
                })->all(), 'quick_feed'),

            'lowStockInventory' => $this->deferProp(fn () => $hasInventoryBasic ? Inventory::query()
                ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                ->where('inventories.restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('inventories.branch_id', $branchId))
                ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                ->select('ingredients.id', 'ingredients.name as ingredient_name',
                    'inventories.quantity_on_hand', 'ingredients.min_stock_level',
                    'ingredients.reorder_level', 'units.name as unit_name')
                ->orderBy('inventories.quantity_on_hand')->take(8)->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_name' => $item->ingredient_name,
                    'quantity_on_hand' => (float) $item->quantity_on_hand,
                    'min_stock_level' => (float) $item->min_stock_level,
                    'reorder_level' => (float) $item->reorder_level,
                    'unit_name' => $item->unit_name ?? 'đv',
                ])->all() : [], 'quick_feed'),

            'revenueChartData' => $this->deferProp(fn () => $restaurant ? $this->chartService->getRevenueChartData($restaurant->id, $branchId, $hasAiForecasting) : [], 'charts'),
            'peakHoursChartData' => $this->deferProp(fn () => $restaurant ? $this->chartService->getPeakHoursChartData($restaurant->id, $branchId) : [], 'charts'),
            'channelChartData' => $this->deferProp(fn () => ($restaurant && ($hasAdvancedAnalytics || $hasInventoryBasic)) ? $this->chartService->getChannelChartData($restaurant->id, $branchId) : [], 'charts'),
            'topProductsChartData' => $this->deferProp(fn () => ($restaurant && $hasAdvancedAnalytics) ? $this->chartService->getTopProductsChartData($restaurant->id, $branchId) : [], 'charts'),
            'forecastData' => $this->deferProp(fn () => ($restaurant && $hasAiForecasting) ? $this->getForecastData($restaurant->id, $branchId) : null, 'analytics'),
            'healthScore' => $this->deferProp(fn () => ($restaurant && ($hasAdvancedAnalytics || $hasHrTimekeeping)) ? $this->healthService->getHealthScore($restaurant->id, $branchId) : null, 'analytics'),
            'shiftRevenue' => $this->deferProp(fn () => ($restaurant && $hasHrTimekeeping) ? $this->chartService->getShiftRevenue($restaurant->id, $branchId) : [], 'charts'),
            'ownerSummary' => $this->deferProp(fn () => ($restaurant && ($hasAdvancedAnalytics || $hasHrTimekeeping)) ? $this->summaryService->getOwnerSummary($restaurant->id, $branchId) : null, 'analytics'),
            'cashFlowSummary' => $this->deferProp(fn () => ($restaurant && ($hasAdvancedAnalytics || $hasInventoryBasic)) ? $this->summaryService->getCashFlowSummary($restaurant->id, $branchId) : null, 'analytics'),
        ]);
    }

    private function getForecastData(int $rid, ?int $branchId = null): ?array
    {
        $scopeKey = TenantContext::branchScopeKey($branchId);
        $key = "dashboard:forecast:v2:{$rid}:{$scopeKey}:".today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            return $this->forecast->forecastTomorrow($rid, $branchId);
        });
    }
}
