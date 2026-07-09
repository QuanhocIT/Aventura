<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use App\Models\ViolationReport;
use App\Services\ForecastService;
use App\Services\OrderStatsCacheService;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private ForecastService $forecast,
        private QuotaService $quotaService,
        private OrderStatsCacheService $orderStatsCache,
    ) {}

    public function index(Request $request): mixed
    {
        $user       = $request->user();
        $restaurant = $user?->restaurant;

        if ($user && $user->isSuperAdmin()) {
            return redirect('/super-admin/dashboard');
        }

        if ($user && $user->can('manage_kitchen') && !$user->can('view_report')) {
            return redirect()->route('kitchen.index');
        }

        if ($user && $user->can('create_orders') && !$user->can('view_report')) {
            return app(CashierDashboardController::class)->index();
        }

        // Nhân viên kho: đưa thẳng vào trang quản lý Tồn kho (giống pattern Kitchen/Cashier ở trên)
        // thay vì rơi vào dashboard đầy đủ kiểu Owner (báo cáo tài chính, so sánh chi nhánh...).
        if ($user && $user->hasRole('inventory_staff') && !$user->can('view_report')) {
            return redirect()->route('inventory.index');
        }

        $hasAdvancedAnalytics = $restaurant && $this->quotaService->hasFeature($restaurant, 'advanced_analytics');
        $hasAiForecasting     = $restaurant && $this->quotaService->hasFeature($restaurant, 'ai_forecasting');
        $hasHrTimekeeping     = $restaurant && $this->quotaService->hasFeature($restaurant, 'hr_timekeeping');
        $hasInventoryBasic    = $restaurant && $this->quotaService->hasFeature($restaurant, 'inventory_basic');

        $branchId = $request->query('branch_id');
        if ($branchId === 'all' || $branchId === '') {
            $branchId = null;
        } elseif ($branchId !== null) {
            $branchId = (int) $branchId;
        }

        // Enforce branch limits: Free plan allows only 1 branch
        if ($restaurant && $this->quotaService->getLimit($restaurant, 'branches') <= 1) {
            $branchId = null;
        }

        // Restrict non-owners to their assigned branch if employee profile exists
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->branch_id && !$user->hasRole('owner')) {
            $branchId = $employee->branch_id;
        }

        $stats        = null;
        $recentOrders = [];
        $alerts       = [];
        $revenueChartData  = [];
        $channelChartData  = [];
        $topProductsChartData = [];
        $operationFeed = [];
        $tablesData    = [];
        $lowStockInventory = [];
        $forecastData  = null;
        $healthScore   = null;
        $shiftRevenue  = [];
        $ownerSummary  = null;
        $branches      = [];
        $branchComparisons = [];

        if ($restaurant) {
            $rid = $restaurant->id;

            $branches = $restaurant->branches()->get(['id', 'name'])->toArray();

            // Today summary & Yesterday summary for active branch/consolidated
            $todaySummaryQuery = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_date', today())
                ->where('summary_type', 'daily');

            $yesterdaySummaryQuery = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_date', today()->subDay())
                ->where('summary_type', 'daily');

            if ($branchId) {
                $todaySummaryQuery->where('branch_id', $branchId);
                $yesterdaySummaryQuery->where('branch_id', $branchId);
            } else {
                $todaySummaryQuery->whereNull('branch_id');
                $yesterdaySummaryQuery->whereNull('branch_id');
            }

            $todaySummary = $todaySummaryQuery->first();
            $yesterdaySummary = $yesterdaySummaryQuery->first();

            // ── Live order counts (cached 5 phút, invalidate khi có đơn mới) ────────
            // Thay vì 3 COUNT query riêng lẻ mỗi lần load dashboard, dùng 1 query
            // tổng hợp được cache → giảm tải DB khi nhiều nhà hàng online đồng thời.
            $todayLiveStats = $this->orderStatsCache->getTodayStats($rid, $branchId);

            $totalToday     = $todayLiveStats['total'];
            $completedToday = $todayLiveStats['completed'];
            $cancelledToday = $todayLiveStats['cancelled'];

            // ── Xu hướng so hôm qua ─────────────────────────────────────────
            $revenueToday     = (float) ($todaySummary?->net_revenue ?? 0);
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
            $cacheSuffix = $branchId ? ":{$branchId}" : "";
            $resourceCounts = Cache::remember("dashboard_counts:{$rid}{$cacheSuffix}", 300, function () use ($restaurant, $branchId) {
                return [
                    'products_count'  => $restaurant->products()->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
                    'employees_count' => $restaurant->employees()->where('status', 'active')->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
                    'branches_count'  => $restaurant->branches()->count(),
                    'tables_count'    => $restaurant->tables()->when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
                ];
            });

            $stats = array_merge($resourceCounts, [
                'orders_today'      => $totalToday,
                'revenue_today'     => $revenueToday,
                'orders_completed'  => $completedToday,
                'orders_cancelled'  => $cancelledToday,
                'revenue_trend'     => $revTrend,
                'order_trend'       => $orderTrend,
                'profit_margin_today' => $profitMargin,
                'completion_rate'   => $completionRate,
            ]);

            // Compute health score dynamically
            $healthScore = $this->getHealthScore($rid, $branchId);

            // ── Dự báo doanh thu ngày mai ────────────────────────────────────
            $forecastData = $hasAiForecasting ? $this->forecast->forecastTomorrow($rid, $branchId) : null;

            // ── AI Cảnh báo mới ──────────────────────────────────────────────
            // Alert 1: Pending > 30 phút
            $stuckPending = Order::where('restaurant_id', $rid)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
            if ($stuckPending > 0) {
                $alerts[] = [
                    'type'    => 'warning',
                    'message' => "{$stuckPending} đơn hàng đang chờ xử lý quá 30 phút",
                    'href'    => '/orders?status=pending',
                ];
            }

            // Alert 2: Tỉ lệ hủy cao
            if ($totalToday > 0 && ($cancelledToday / $totalToday) > 0.2) {
                $pct = round(($cancelledToday / $totalToday) * 100);
                $alerts[] = [
                    'type'    => 'danger',
                    'message' => "Tỉ lệ huỷ đơn hôm nay cao: {$pct}% ({$cancelledToday}/{$totalToday} đơn)",
                    'href'    => '/orders?status=cancelled',
                ];
            }

            // Alert 3: Đơn đang chế biến lâu
            $stuckProcessing = Order::where('restaurant_id', $rid)
                ->whereIn('status', ['confirmed', 'preparing'])
                ->where('updated_at', '<', now()->subHour())
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();
            if ($stuckProcessing > 0) {
                $alerts[] = [
                    'type'    => 'info',
                    'message' => "{$stuckProcessing} đơn đang chế biến chưa được cập nhật trạng thái",
                    'href'    => '/orders',
                ];
            }

            // Alert 4 (AI): Doanh thu hôm nay thấp hơn dự báo > 30%
            if ($hasAiForecasting && $this->quotaService->hasFeature($restaurant, 'ai_advisor') && $forecastData) {
                $forecast = $forecastData['amount'] ?? 0;
                if ($forecast > 0 && $revenueToday > 0 && now()->hour >= 14) {
                    $pctOfForecast = $revenueToday / $forecast * 100;
                    if ($pctOfForecast < 70) {
                        $gap = round(100 - $pctOfForecast);
                        $alerts[] = [
                            'type'    => 'warning',
                            'ai'      => true,
                            'message' => "⚡ Doanh thu hôm nay thấp hơn dự báo {$gap}% — hãy kích hoạt khuyến mãi flash",
                            'href'    => '/promotions',
                        ];
                    }
                }
            }

            // Alert 5 (AI): Nhân viên chưa check-in dù đã qua giờ ca
            if ($hasHrTimekeeping) {
                $missingCheckIns = \App\Models\ScheduleAssignment::where('restaurant_id', $rid)
                    ->where('scheduled_date', today())
                    ->where('status', 'scheduled')
                    ->whereHas('shift', fn ($q) => $q->where('start_time', '<=', now()->format('H:i:s')))
                    ->when($branchId, function ($q) use ($branchId) {
                        $q->whereHas('employee', fn($emp) => $emp->where('branch_id', $branchId));
                    })
                    ->count();
                if ($missingCheckIns > 0) {
                    $alerts[] = [
                        'type'    => 'warning',
                        'ai'      => true,
                        'message' => "⚡ {$missingCheckIns} nhân viên chưa check-in dù đã qua giờ bắt đầu ca",
                        'href'    => '/schedules',
                    ];
                }
            }

            // Alert 6 (Fraud): Đơn bị tách chưa đối soát
            if ($restaurant && $this->quotaService->hasFeature($restaurant, 'fraud_detection')) {
                $splitAlertsCount = Order::where('restaurant_id', $rid)
                    ->where('is_split', true)
                    ->where('is_red_flagged', true)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->count();
                if ($splitAlertsCount > 0) {
                    $alerts[] = [
                        'type'    => 'danger',
                        'message' => "⚠️ Phát hiện {$splitAlertsCount} đơn hàng bị tách chưa được đối soát (Có nguy cơ gian lận!)",
                        'href'    => '/orders',
                    ];
                }
            }

            // Alert 7 (Cash discrepancy > 2% of expected cash at close)
            if ($hasInventoryBasic || $hasAdvancedAnalytics) {
                $closedRegisters = \App\Models\CashRegister::where('restaurant_id', $rid)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'closed')
                    ->where('closed_at', '>=', now()->subDays(7))
                    ->where('expected_closing_balance', '>', 0)
                    ->get();

                $discrepancyRegistersCount = $closedRegisters->filter(function ($r) {
                    return (float) $r->expected_closing_balance > 0
                        && (abs((float) $r->difference) / (float) $r->expected_closing_balance) > 0.02;
                })->count();
                if ($discrepancyRegistersCount > 0) {
                    $alerts[] = [
                        'type'    => 'danger',
                        'message' => "⚠️ Phát hiện {$discrepancyRegistersCount} ca chốt két tiền mặt chênh lệch vượt quá 2% so với hệ thống trong 7 ngày qua!",
                        'href'    => '/cash-flow',
                    ];
                }

                // Alert 8 (Active registers expense budget overrun)
                $overrunRegistersCount = \App\Models\CashRegister::where('restaurant_id', $rid)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'open')
                    ->where('expense_budget', '>', 0)
                    ->withSum(['transactions as expense_total' => fn($q) => $q->where('type', 'out')], 'amount')
                    ->get()
                    ->filter(fn($r) => (float) ($r->expense_total ?? 0) > (float) $r->expense_budget)
                    ->count();
                if ($overrunRegistersCount > 0) {
                    $alerts[] = [
                        'type'    => 'warning',
                        'message' => "⚠️ Ca trực hiện tại đang có chi tiêu tiền mặt vượt quá ngân sách chi ngoài hệ thống!",
                        'href'    => '/cash-flow',
                    ];
                }
            }

            // ── Activity Feed ────────────────────────────────────────────────
            $feedItems = [];
            $ordersForFeed = Order::with('table')
                ->where('restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->latest()
                ->take(8)
                ->get();

            $channelLabels = [
                'dine_in' => 'Tại bàn', 'takeaway' => 'Mang về',
                'delivery' => 'Giao hàng', 'qr' => 'Mã QR',
            ];

            foreach ($ordersForFeed as $o) {
                $tblStr  = $o->table ? " tại Bàn {$o->table->name}" : "";
                $chanStr = $channelLabels[$o->channel] ?? $o->channel;
                $time    = $o->updated_at ?? $o->created_at;
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
                        'description' => "Đơn #{$o->order_number}{$tblStr} ({$chanStr} — " . number_format($o->total_amount) . "đ)",
                        'amount' => (float) $o->total_amount,
                        'time' => $time->diffForHumans(),
                        'timestamp' => $time->timestamp,
                    ];
                }
            }

            $lowStocksForFeed = [];
            if ($hasInventoryBasic) {
                $lowStocksForFeed = \App\Models\Inventory::query()
                    ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                    ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                    ->where('inventories.restaurant_id', $rid)
                    ->when($branchId, fn($q) => $q->where('inventories.branch_id', $branchId))
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
                    'description' => "\"{$item->ingredient_name}\" còn " . round($item->quantity_on_hand, 2) . " " . ($item->unit_name ?? 'đv'),
                    'amount' => null, 'time' => $time->diffForHumans(),
                    'timestamp' => \Carbon\Carbon::parse($time)->timestamp,
                ];
            }

            $activeSchedulesForFeed = [];
            if ($hasHrTimekeeping) {
                $activeSchedulesForFeed = \App\Models\ScheduleAssignment::with(['employee', 'shift'])
                    ->where('restaurant_id', $rid)->where('scheduled_date', today())
                    ->whereNotNull('check_in_at')
                    ->whereHas('employee', fn($emp) => $emp->when($branchId, fn($q) => $q->where('branch_id', $branchId)))
                    ->latest('check_in_at')
                    ->take(4)
                    ->get();
            }

            foreach ($activeSchedulesForFeed as $sa) {
                $time = $sa->check_in_at;
                $feedItems[] = [
                    'type' => 'shift_checkin', 'title' => 'Nhân sự vào ca',
                    'icon' => 'Users', 'color' => 'sky', 'link' => '/employees',
                    'description' => "{$sa->employee?->full_name} check-in ca " . ($sa->shift?->name ?? '') . " lúc " . $time->format('H:i'),
                    'amount' => null, 'time' => $time->diffForHumans(),
                    'timestamp' => $time->timestamp,
                ];
            }

            usort($feedItems, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);
            $operationFeed = array_slice($feedItems, 0, 8);

            // ── Table grid ───────────────────────────────────────────────────
            $tablesData = \App\Models\RestaurantTable::with(['area', 'activeOrder'])
                ->where('restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->orderBy('name')
                ->get()
                ->map(function ($t) {
                    $status = $t->status;
                    if ($status === 'occupied') {
                        $activeOrder = $t->activeOrder;
                        if (!$activeOrder) {
                            $status = 'available';
                            $t->update(['status' => 'available']);
                        }
                    }
                    return [
                        'id' => $t->id, 'name' => $t->name,
                        'area' => $t->area?->name ?? 'Khu vực chung',
                        'capacity' => $t->capacity, 'status' => $status,
                    ];
                })->all();

            // ── Low stock ────────────────────────────────────────────────────
            $lowStockInventory = [];
            if ($hasInventoryBasic) {
                $lowStockInventory = \App\Models\Inventory::query()
                    ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                    ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                    ->where('inventories.restaurant_id', $rid)
                    ->when($branchId, fn($q) => $q->where('inventories.branch_id', $branchId))
                    ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                    ->select('ingredients.id', 'ingredients.name as ingredient_name',
                        'inventories.quantity_on_hand', 'ingredients.min_stock_level',
                        'ingredients.reorder_level', 'units.name as unit_name')
                    ->orderBy('inventories.quantity_on_hand')->take(8)->get()
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'ingredient_name' => $item->ingredient_name,
                        'quantity_on_hand' => (float) $item->quantity_on_hand,
                        'min_stock_level'  => (float) $item->min_stock_level,
                        'reorder_level'    => (float) $item->reorder_level,
                        'unit_name'        => $item->unit_name ?? 'đv',
                    ])->all();
            }

            // ── Recent orders ────────────────────────────────────────────────
            $recentOrders = Order::with('table')
                ->where('restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Order $o) => [
                    'id'             => $o->id,
                    'order_number'   => $o->order_number,
                    'table_name'     => $o->table?->name ?? null,
                    'total_amount'   => (float) $o->total_amount,
                    'status'         => $o->status,
                    'payment_status' => $o->payment_status,
                    'channel'        => $o->channel,
                    'created_at'     => $o->created_at?->format('H:i'),
                ])->all();

            // ── Multi-branch Consolidated comparison ──────────────────────────
            if (!$branchId && count($branches) > 1 && $hasAdvancedAnalytics) {
                $branchIds = array_column($branches, 'id');

                // 1. Pre-query completed revenue for all branches today
                $revenuesByBranch = Order::where('restaurant_id', $rid)
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                    ->groupBy('branch_id')
                    ->selectRaw('branch_id, SUM(total_amount) as total_revenue')
                    ->pluck('total_revenue', 'branch_id');

                // 2. Pre-query revenue summary for all branches today
                $summariesByBranch = RestaurantRevenueSummary::where('restaurant_id', $rid)
                    ->whereIn('branch_id', $branchIds)
                    ->where('summary_date', today())
                    ->where('summary_type', 'daily')
                    ->get()
                    ->keyBy('branch_id');

                // 3. Pre-query COGS for branches that might not have a summary today
                $cogsByBranch = \App\Models\OrderItem::whereHas('order', function ($q) use ($rid, $branchIds) {
                        $q->where('restaurant_id', $rid)
                            ->whereIn('branch_id', $branchIds)
                            ->where('status', 'completed')
                            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
                    })
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->groupBy('orders.branch_id')
                    ->selectRaw('orders.branch_id, SUM(order_items.quantity * products.cost_price) as total_cogs')
                    ->pluck('total_cogs', 'branch_id');

                // 4. Pre-query Violations for all branches over the last 30 days
                $violationsByBranch = ViolationReport::where('violation_reports.restaurant_id', $rid)
                    ->whereHas('employee', function ($q) use ($branchIds) {
                        $q->whereIn('branch_id', $branchIds);
                    })
                    ->where('violation_reports.occurred_at', '>=', now()->subDays(30))
                    ->join('employees', 'violation_reports.employee_id', '=', 'employees.id')
                    ->groupBy('employees.branch_id')
                    ->selectRaw('employees.branch_id, COUNT(*) as count')
                    ->pluck('count', 'branch_id');

                // Pre-fetch health scores in batch to eliminate N+1 queries in the loop
                $branchHealthScores = $this->getHealthScoresForBranches($rid, $branchIds);

                foreach ($branches as $b) {
                    $bId = $b['id'];
                    $bRevToday = (float) ($revenuesByBranch[$bId] ?? 0.0);
                    $bTodaySummary = $summariesByBranch->get($bId);

                    $bGrossProfit = $bTodaySummary ? (float) $bTodaySummary->gross_profit : 0.0;
                    $bMargin = $bRevToday > 0 ? round(($bGrossProfit / $bRevToday) * 100, 1) : 0.0;

                    if (!$bTodaySummary && $bRevToday > 0) {
                        $totalCogs = (float) ($cogsByBranch[$bId] ?? 0.0);
                        $bGrossProfit = $bRevToday - $totalCogs;
                        $bMargin = round(($bGrossProfit / $bRevToday) * 100, 1);
                    }

                    $bHealth = $branchHealthScores[$bId] ?? 75;
                    $bViolations = (int) ($violationsByBranch[$bId] ?? 0);

                    $branchComparisons[] = [
                        'id'               => $bId,
                        'name'             => $b['name'],
                        'revenue'          => $bRevToday,
                        'profit_margin'    => $bMargin,
                        'health_score'     => $bHealth,
                        'violations_count' => $bViolations,
                    ];
                }
            }
        }

        $onboardingStatus   = $user?->onboarding_status ?? [];
        $onboardingComplete = !$user?->can('approve_requests') || (
            !empty($onboardingStatus['day_1']['completed_at'])
            && !empty($onboardingStatus['day_2']['completed_at'])
            && !empty($onboardingStatus['day_3']['completed_at'])
        );

        return Inertia::render('Dashboard', [
            'branchId'             => $branchId,
            'branches'             => $branches,
            'branchComparisons'    => $branchComparisons,
            'operationFeed'        => $operationFeed,
            'tablesData'           => $tablesData,
            'lowStockInventory'    => $lowStockInventory,
            'stats'                => $stats,
            'onboardingComplete'   => $onboardingComplete,
            'recentOrders'         => $recentOrders,
            'alerts'               => $alerts,
            'revenueChartData'     => $restaurant ? $this->getRevenueChartData($restaurant->id, $branchId, $hasAiForecasting) : [],
            'peakHoursChartData'   => $restaurant ? $this->getPeakHoursChartData($restaurant->id, $branchId) : [],
            'channelChartData'     => ($restaurant && ($hasAdvancedAnalytics || $hasInventoryBasic)) ? $this->getChannelChartData($restaurant->id, $branchId) : [],
            'topProductsChartData' => ($restaurant && $hasAdvancedAnalytics) ? $this->getTopProductsChartData($restaurant->id, $branchId) : [],
            'forecastData'         => ($restaurant && $hasAiForecasting) ? $this->getForecastData($restaurant->id, $branchId) : null,
            'healthScore'          => ($restaurant && ($hasAdvancedAnalytics || $hasHrTimekeeping)) ? $this->getHealthScore($restaurant->id, $branchId) : null,
            'shiftRevenue'         => ($restaurant && $hasHrTimekeeping) ? $this->getShiftRevenue($restaurant->id, $branchId) : [],
            'ownerSummary'         => ($restaurant && ($hasAdvancedAnalytics || $hasHrTimekeeping)) ? $this->getOwnerSummary($restaurant->id, $branchId) : null,
            'cashFlowSummary'      => ($restaurant && ($hasAdvancedAnalytics || $hasInventoryBasic)) ? $this->getCashFlowSummary($restaurant->id, $branchId) : null,
        ]);
    }

    private function calculateBranchHealthScore(int $rid, ?int $branchId = null): int
    {
        $todaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today())
            ->where('summary_type', 'daily');
        if ($branchId) {
            $todaySummary->where('branch_id', $branchId);
        } else {
            $todaySummary->whereNull('branch_id');
        }
        $todaySummary = $todaySummary->first();

        $yesterdaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today()->subDay())
            ->where('summary_type', 'daily');
        if ($branchId) {
            $yesterdaySummary->where('branch_id', $branchId);
        } else {
            $yesterdaySummary->whereNull('branch_id');
        }
        $yesterdaySummary = $yesterdaySummary->first();

        $ordersToday = Order::where('restaurant_id', $rid)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $totalToday     = (clone $ordersToday)->count();
        $completedToday = (clone $ordersToday)->where('status', 'completed')->count();
        $cancelledToday = (clone $ordersToday)->where('status', 'cancelled')->count();

        $revenueToday     = (float) ($todaySummary?->net_revenue ?? 0);
        $revenueYesterday = (float) ($yesterdaySummary?->net_revenue ?? 0);
        $revTrend = $revenueYesterday > 0
            ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1)
            : null;

        $grossProfit  = (float) ($todaySummary?->gross_profit ?? 0);
        $profitMargin = $revenueToday > 0
            ? round($grossProfit / $revenueToday * 100, 1)
            : 0.0;

        $completionRate   = $totalToday > 0 ? round($completedToday / $totalToday * 100, 1) : 0.0;
        $cancellationRate = $totalToday > 0 ? ($cancelledToday / $totalToday) * 100 : 0;
        $revenueGrowthScore = $revTrend !== null ? min(100, max(0, 50 + $revTrend)) : 50;

        // Punctuality
        $scheduledToday = \App\Models\ScheduleAssignment::where('restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('employee', fn($emp) => $emp->where('branch_id', $branchId));
            })->count();
        $checkedInOnTime = $scheduledToday > 0
            ? \App\Models\ScheduleAssignment::where('restaurant_id', $rid)
                ->where('scheduled_date', today())
                ->when($branchId, function ($q) use ($branchId) {
                    $q->whereHas('employee', fn($emp) => $emp->where('branch_id', $branchId));
                })
                ->whereNotNull('check_in_at')
                ->whereColumn('check_in_at', '<=', \Illuminate\Support\Facades\DB::raw(
                    \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
                        ? 'scheduled_date || " " || (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.shift_id)'
                        : 'CONCAT(scheduled_date, " ", (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.shift_id))'
                ))
                ->count()
            : null;
        $punctualityScore = $scheduledToday > 0 && $checkedInOnTime !== null
            ? min(100, ($checkedInOnTime / $scheduledToday) * 100)
            : 75;

        // Inventory
        $totalIngredients = \App\Models\Inventory::where('restaurant_id', $rid)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();
        $safeIngredients  = $totalIngredients > 0
            ? \App\Models\Inventory::join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->where('inventories.restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('inventories.branch_id', $branchId))
                ->whereRaw('inventories.quantity_on_hand > ingredients.min_stock_level')
                ->count()
            : null;
        $inventoryScore = $totalIngredients > 0 && $safeIngredients !== null
            ? ($safeIngredients / $totalIngredients) * 100
            : 80;

        // NPS
        $npsData = \App\Models\CustomerFeedback::where('restaurant_id', $rid)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();
        $npsScore = $npsData && $npsData->cnt > 0
            ? min(100, max(0, (($npsData->avg_rating - 1) / 4) * 100))
            : 70;

        return (int) round(
            min(100, max(0,
                ($completionRate    * 0.25) +
                (max(0, 100 - $cancellationRate * 4) * 0.15) +
                ($revenueGrowthScore * 0.20) +
                (min(100, $profitMargin * 1.5) * 0.15) +
                ($punctualityScore  * 0.10) +
                ($inventoryScore    * 0.10) +
                ($npsScore          * 0.05)
            ))
        );
    }

    private function getRevenueChartData(int $rid, ?int $branchId = null, bool $hasAiForecasting = false): array
    {
        $key = "dashboard:revenue_chart:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString() . ":" . ($hasAiForecasting ? '1' : '0');

        return Cache::remember($key, 300, function () use ($rid, $branchId, $hasAiForecasting) {
            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $dailyStats = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('created_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw("DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as count")
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $revenueChartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $targetDate = now()->subDays($i);
                $dateStr    = $targetDate->format('Y-m-d');
                $label      = $targetDate->format('d/m');
                $dayStat    = $dailyStats->get($dateStr);
                $revenueChartData[] = [
                    'date'        => $label,
                    'revenue'     => $dayStat ? (float) $dayStat->revenue : 0,
                    'orders'      => $dayStat ? (int) $dayStat->count : 0,
                    'is_forecast' => false,
                ];
            }

            if ($hasAiForecasting) {
                $forecast7 = $this->forecast->forecast7Days($rid, $branchId);
                foreach ($forecast7 as $f) {
                    $revenueChartData[] = $f;
                }
            }

            return $revenueChartData;
        });
    }

    private function getChannelChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:channel_chart:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $channelStats = Order::where('restaurant_id', $rid)
                ->where('created_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw("channel, COUNT(*) as count")
                ->groupBy('channel')
                ->get();

            $channelNames = [
                'dine_in'  => 'Tại bàn',
                'takeaway' => 'Mang về',
                'delivery' => 'Giao hàng',
                'qr'       => 'Mã QR',
            ];

            $channelChartData = [];
            $totalChannelsCount = $channelStats->sum('count');
            foreach ($channelStats as $cs) {
                $channelChartData[] = [
                    'channel'    => $cs->channel,
                    'label'      => $channelNames[$cs->channel] ?? $cs->channel,
                    'count'      => (int) $cs->count,
                    'percentage' => $totalChannelsCount > 0
                        ? round(($cs->count / $totalChannelsCount) * 100, 1) : 0,
                ];
            }

            return $channelChartData;
        });
    }

    private function getTopProductsChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:top_products:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            return \App\Models\OrderItem::query()
                ->join('orders',   'order_items.order_id',   '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('orders.branch_id', $branchId))
                ->where('orders.status', 'completed')
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->selectRaw('products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.quantity * order_items.unit_price) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->take(5)
                ->get()
                ->map(fn ($item) => [
                    'name'     => $item->name,
                    'quantity' => (int)   $item->total_qty,
                    'revenue'  => (float) $item->total_revenue,
                ])
                ->all();
        });
    }

    private function getPeakHoursChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:peak_hours:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $thirtyDaysAgo = now()->subDays(30)->startOfDay();
            $hourlyStats = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw(\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER) as hour, COUNT(*) as count" : "HOUR(created_at) as hour, COUNT(*) as count")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->keyBy('hour');

            $peakHoursData = [];
            for ($h = 6; $h <= 23; $h++) {
                $stat = $hourlyStats->get($h);
                $peakHoursData[] = [
                    'hour'  => $h,
                    'label' => sprintf('%02dh', $h),
                    'count' => $stat ? (int) $stat->count : 0,
                ];
            }

            return $peakHoursData;
        });
    }

    private function getForecastData(int $rid, ?int $branchId = null): ?array
    {
        $key = "dashboard:forecast:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            return $this->forecast->forecastTomorrow($rid, $branchId);
        });
    }

    private function getHealthScore(int $rid, ?int $branchId = null): int
    {
        $cached = Cache::get("health_score:{$rid}" . ($branchId ? ":{$branchId}" : ""));
        if ($cached !== null) {
            return (int) $cached;
        }

        $score = $this->calculateBranchHealthScore($rid, $branchId);
        Cache::put("health_score:{$rid}" . ($branchId ? ":{$branchId}" : ""), $score, 300);

        return $score;
    }

    private function getShiftRevenue(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:shift_revenue:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $shifts = \App\Models\WorkShift::where('restaurant_id', $rid)
                ->where('status', 'active')
                ->orderBy('start_time')
                ->get();

            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $orders = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->select('total_amount', 'completed_at')
                ->get();

            $shiftRevenue = [];
            foreach ($shifts as $shift) {
                $row = ['shift_name' => $shift->name, 'days' => []];
                for ($d = 6; $d >= 0; $d--) {
                    $day   = now()->subDays($d);
                    $start = $day->copy()->setTimeFromTimeString($shift->start_time);
                    $end   = $shift->is_overnight
                        ? $day->copy()->addDay()->setTimeFromTimeString($shift->end_time)
                        : $day->copy()->setTimeFromTimeString($shift->end_time);

                    $rev = $orders->filter(function ($order) use ($start, $end) {
                        return $order->completed_at >= $start && $order->completed_at <= $end;
                    })->sum('total_amount');

                    $row['days'][] = [
                        'date'    => $day->format('d/m'),
                        'revenue' => (float) $rev,
                    ];
                }
                $shiftRevenue[] = $row;
            }

            return $shiftRevenue;
        });
    }

    /**
     * Batch pre-fetch health scores for multiple branches to prevent N+1 query bottlenecks.
     */
    private function getHealthScoresForBranches(int $rid, array $branchIds): array
    {
        $scores = [];
        $uncachedIds = [];
        foreach ($branchIds as $id) {
            $cached = Cache::get("health_score:{$rid}" . ($id ? ":{$id}" : ""));
            if ($cached !== null) {
                $scores[$id] = (int) $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        if (empty($uncachedIds)) {
            return $scores;
        }

        // 1. Pre-query summaries
        $summariesToday = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->where('summary_date', today())
            ->where('summary_type', 'daily')
            ->get()
            ->keyBy('branch_id');

        $summariesYesterday = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->where('summary_date', today()->subDay())
            ->where('summary_type', 'daily')
            ->get()
            ->keyBy('branch_id');

        // 2. Pre-query orders today
        $ordersToday = Order::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('branch_id, status, COUNT(*) as cnt')
            ->groupBy('branch_id', 'status')
            ->get()
            ->groupBy('branch_id');

        // 3. Pre-query scheduled work assignments today
        $scheduledToday = \App\Models\ScheduleAssignment::where('schedule_assignments.restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->whereHas('employee', fn($q) => $q->whereIn('branch_id', $uncachedIds))
            ->join('employees', 'schedule_assignments.employee_id', '=', 'employees.id')
            ->selectRaw('employees.branch_id, COUNT(*) as cnt')
            ->groupBy('employees.branch_id')
            ->pluck('cnt', 'employees.branch_id');

        // Checked in on time
        $checkedInOnTime = \App\Models\ScheduleAssignment::where('schedule_assignments.restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->whereHas('employee', fn($q) => $q->whereIn('branch_id', $uncachedIds))
            ->whereNotNull('check_in_at')
            ->whereColumn('check_in_at', '<=', \Illuminate\Support\Facades\DB::raw(
                \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
                    ? 'scheduled_date || " " || (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.shift_id)'
                    : 'CONCAT(scheduled_date, " ", (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.shift_id))'
            ))
            ->join('employees', 'schedule_assignments.employee_id', '=', 'employees.id')
            ->selectRaw('employees.branch_id, COUNT(*) as cnt')
            ->groupBy('employees.branch_id')
            ->pluck('cnt', 'employees.branch_id');

        // 4. Pre-query inventories
        $totalIngredients = \App\Models\Inventory::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->selectRaw('branch_id, COUNT(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt', 'branch_id');

        $safeIngredients = \App\Models\Inventory::join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->where('inventories.restaurant_id', $rid)
            ->whereIn('inventories.branch_id', $uncachedIds)
            ->whereRaw('inventories.quantity_on_hand > ingredients.min_stock_level')
            ->selectRaw('inventories.branch_id, COUNT(*) as cnt')
            ->groupBy('inventories.branch_id')
            ->pluck('cnt', 'inventories.branch_id');

        // 5. Customer feedback NPS (restaurant-wide, constant across branches)
        $npsData = \App\Models\CustomerFeedback::where('restaurant_id', $rid)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();
        $npsScore = $npsData && $npsData->cnt > 0
            ? min(100, max(0, (($npsData->avg_rating - 1) / 4) * 100))
            : 70;

        foreach ($uncachedIds as $bId) {
            $todaySum = $summariesToday->get($bId);
            $yesterdaySum = $summariesYesterday->get($bId);

            $branchOrders = $ordersToday->get($bId) ?? collect();
            $totalTodayVal = $branchOrders->sum('cnt');
            $completedTodayVal = $branchOrders->where('status', 'completed')->sum('cnt');
            $cancelledTodayVal = $branchOrders->where('status', 'cancelled')->sum('cnt');

            $revenueTodayVal = (float) ($todaySum?->net_revenue ?? 0);
            $revenueYesterdayVal = (float) ($yesterdaySum?->net_revenue ?? 0);
            $revTrendVal = $revenueYesterdayVal > 0
                ? round(($revenueTodayVal - $revenueYesterdayVal) / $revenueYesterdayVal * 100, 1)
                : null;

            $grossProfitVal = (float) ($todaySum?->gross_profit ?? 0);
            $profitMarginVal = $revenueTodayVal > 0
                ? round($grossProfitVal / $revenueTodayVal * 100, 1)
                : 0.0;

            $completionRateVal = $totalTodayVal > 0 ? round($completedTodayVal / $totalTodayVal * 100, 1) : 0.0;
            $cancellationRateVal = $totalTodayVal > 0 ? ($cancelledTodayVal / $totalTodayVal) * 100 : 0;
            $revenueGrowthScoreVal = $revTrendVal !== null ? min(100, max(0, 50 + $revTrendVal)) : 50;

            // Punctuality
            $bScheduled = $scheduledToday->get($bId, 0);
            $bCheckedIn = $checkedInOnTime->get($bId, null);
            $punctualityScoreVal = $bScheduled > 0 && $bCheckedIn !== null
                ? min(100, ($bCheckedIn / $bScheduled) * 100)
                : 75;

            // Inventory
            $bTotalIng = $totalIngredients->get($bId, 0);
            $bSafeIng = $safeIngredients->get($bId, null);
            $inventoryScoreVal = $bTotalIng > 0 && $bSafeIng !== null
                ? ($bSafeIng / $bTotalIng) * 100
                : 80;

            $score = (int) round(
                min(100, max(0,
                    ($completionRateVal    * 0.25) +
                    (max(0, 100 - $cancellationRateVal * 4) * 0.15) +
                    ($revenueGrowthScoreVal * 0.20) +
                    (min(100, $profitMarginVal * 1.5) * 0.15) +
                    ($punctualityScoreVal  * 0.10) +
                    ($inventoryScoreVal    * 0.10) +
                    ($npsScore             * 0.05)
                ))
            );

            Cache::put("health_score:{$rid}" . ($bId ? ":{$bId}" : ""), $score, 300);
            $scores[$bId] = $score;
        }

        return $scores;
    }

    private function getOwnerSummary(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:owner_summary:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $topTodayProducts = \App\Models\OrderItem::query()
                ->join('orders',   'order_items.order_id',   '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('orders.branch_id', $branchId))
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [today()->startOfDay(), today()->endOfDay()])
                ->selectRaw('products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.line_total) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->take(3)
                ->get()
                ->map(fn ($r) => [
                    'name'    => $r->name,
                    'qty'     => (int)   $r->total_qty,
                    'revenue' => (float) $r->total_revenue,
                ])
                ->all();

            $activeShifts = \App\Models\ScheduleAssignment::with(['employee', 'shift'])
                ->where('restaurant_id', $rid)
                ->where('scheduled_date', today())
                ->whereIn('status', ['checked_in', 'scheduled'])
                ->when($branchId, function ($q) use ($branchId) {
                    $q->whereHas('employee', fn($emp) => $emp->where('branch_id', $branchId));
                })
                ->get()
                ->map(fn ($a) => [
                    'name'   => $a->employee?->full_name ?? '—',
                    'shift'  => $a->shift?->name ?? '—',
                    'status' => $a->status,
                ])
                ->all();

            $pendingOrders = Order::where('restaurant_id', $rid)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(20))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->count();

            return [
                'top_products_today' => $topTodayProducts,
                'active_shifts'      => $activeShifts,
                'pending_over_20min' => $pendingOrders,
                'revenue_this_week'  => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                    ->where('summary_type', 'daily')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->when(!$branchId, fn($q) => $q->whereNull('branch_id'))
                    ->whereBetween('summary_date', [today()->startOfWeek(), today()])
                    ->sum('net_revenue'),
                'revenue_last_week'  => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                    ->where('summary_type', 'daily')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->when(!$branchId, fn($q) => $q->whereNull('branch_id'))
                    ->whereBetween('summary_date', [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()])
                    ->sum('net_revenue'),
            ];
        });
    }

    private function getCashFlowSummary(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:cash_flow:{$rid}" . ($branchId ? ":{$branchId}" : "") . ":" . today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $activeRegister = \App\Models\CashRegister::where('restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->where('status', 'open')
                ->first();

            $currentCash = 0.0;
            if ($activeRegister) {
                $in = (float) \App\Models\CashTransaction::where('cash_register_id', $activeRegister->id)->where('type', 'in')->sum('amount');
                $out = (float) \App\Models\CashTransaction::where('cash_register_id', $activeRegister->id)->where('type', 'out')->sum('amount');
                $currentCash = (float) $activeRegister->opening_balance + $in - $out;
            }

            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $recentTransactions = \App\Models\CashTransaction::where('restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->where('occurred_at', '>=', $sevenDaysAgo)
                ->selectRaw("DATE(occurred_at) as date, type, SUM(amount) as total")
                ->groupBy('date', 'type')
                ->get();

            $chart = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dateStr = $date->toDateString();
                $label = $date->format('d/m');
                $in = (float) $recentTransactions->where('date', $dateStr)->where('type', 'in')->sum('total');
                $out = (float) $recentTransactions->where('date', $dateStr)->where('type', 'out')->sum('total');
                $chart[] = [
                    'date' => $label,
                    'in' => $in,
                    'out' => $out
                ];
            }

            return [
                'active_register_status' => $activeRegister ? 'open' : 'closed',
                'current_cash' => $currentCash,
                'seven_days_in' => (float) $recentTransactions->where('type', 'in')->sum('total'),
                'seven_days_out' => (float) $recentTransactions->where('type', 'out')->sum('total'),
                'chart' => $chart,
            ];
        });
    }
}
