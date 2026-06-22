<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use App\Models\ViolationReport;
use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private ForecastService $forecast) {}

    public function index(Request $request): mixed
    {
        $user       = $request->user();
        $restaurant = $user?->restaurant;

        if ($user && $user->can('manage_kitchen') && !$user->can('view_report')) {
            return redirect()->route('kitchen.index');
        }

        if ($user && $user->can('create_orders') && !$user->can('view_report')) {
            return app(CashierDashboardController::class)->index();
        }

        $branchId = $request->query('branch_id');
        if ($branchId === 'all' || $branchId === '') {
            $branchId = null;
        } elseif ($branchId !== null) {
            $branchId = (int) $branchId;
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

            $ordersToday = Order::where('restaurant_id', $rid)
                ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

            $totalToday     = (clone $ordersToday)->count();
            $completedToday = (clone $ordersToday)->where('status', 'completed')->count();
            $cancelledToday = (clone $ordersToday)->where('status', 'cancelled')->count();

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
            $healthScore = $this->calculateBranchHealthScore($rid, $branchId);
            Cache::put("health_score:{$rid}{$cacheSuffix}", $healthScore, 60);

            // ── Dự báo doanh thu ngày mai ────────────────────────────────────
            $forecastData = $this->forecast->forecastTomorrow($rid, $branchId);

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

            // Alert 5 (AI): Nhân viên chưa check-in dù đã qua giờ ca
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

            // Alert 6 (Fraud): Đơn bị tách chưa đối soát
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

            $lowStocksForFeed = \App\Models\Inventory::query()
                ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                ->where('inventories.restaurant_id', $rid)
                ->when($branchId, fn($q) => $q->where('inventories.branch_id', $branchId))
                ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                ->select('ingredients.name as ingredient_name', 'inventories.quantity_on_hand',
                    'ingredients.min_stock_level', 'units.name as unit_name', 'inventories.updated_at')
                ->take(4)->get();

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

            $activeSchedulesForFeed = \App\Models\ScheduleAssignment::with(['employee', 'shift'])
                ->where('restaurant_id', $rid)->where('scheduled_date', today())
                ->whereNotNull('check_in_at')
                ->whereHas('employee', fn($emp) => $emp->when($branchId, fn($q) => $q->where('branch_id', $branchId)))
                ->latest('check_in_at')
                ->take(4)
                ->get();

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
            if (!$branchId && count($branches) > 1) {
                foreach ($branches as $b) {
                    $bRevToday = (float) Order::where('restaurant_id', $rid)
                        ->where('branch_id', $b['id'])
                        ->where('status', 'completed')
                        ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
                        ->sum('total_amount');

                    $bTodaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
                        ->where('branch_id', $b['id'])
                        ->where('summary_date', today())
                        ->first();

                    $bGrossProfit = $bTodaySummary ? (float) $bTodaySummary->gross_profit : 0.0;
                    $bMargin = $bRevToday > 0 ? round(($bGrossProfit / $bRevToday) * 100, 1) : 0.0;

                    if (!$bTodaySummary && $bRevToday > 0) {
                        $totalCogs = (float) \App\Models\OrderItem::whereHas('order', function ($q) use ($b) {
                            $q->where('branch_id', $b['id'])
                                ->where('status', 'completed')
                                ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
                        })->join('products', 'order_items.product_id', '=', 'products.id')
                        ->sum(\Illuminate\Support\Facades\DB::raw('order_items.quantity * products.cost_price'));

                        $bGrossProfit = $bRevToday - $totalCogs;
                        $bMargin = round(($bGrossProfit / $bRevToday) * 100, 1);
                    }

                    $bHealth = $this->calculateBranchHealthScore($rid, $b['id']);

                    $bViolations = ViolationReport::where('restaurant_id', $rid)
                        ->whereHas('employee', function ($q) use ($b) {
                            $q->where('branch_id', $b['id']);
                        })
                        ->where('occurred_at', '>=', now()->subDays(30))
                        ->count();

                    $branchComparisons[] = [
                        'id'               => $b['id'],
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
            'revenueChartData'     => $restaurant ? Inertia::defer(fn () => $this->getRevenueChartData($restaurant->id, $branchId)) : [],
            'channelChartData'     => $restaurant ? Inertia::defer(fn () => $this->getChannelChartData($restaurant->id, $branchId)) : [],
            'topProductsChartData' => $restaurant ? Inertia::defer(fn () => $this->getTopProductsChartData($restaurant->id, $branchId)) : [],
            'forecastData'         => $restaurant ? Inertia::defer(fn () => $this->getForecastData($restaurant->id, $branchId)) : null,
            'healthScore'          => $restaurant ? Inertia::defer(fn () => $this->getHealthScore($restaurant->id, $branchId)) : null,
            'shiftRevenue'         => $restaurant ? Inertia::defer(fn () => $this->getShiftRevenue($restaurant->id, $branchId)) : [],
            'ownerSummary'         => $restaurant ? Inertia::defer(fn () => $this->getOwnerSummary($restaurant->id, $branchId)) : null,
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
                ->whereColumn('check_in_at', '<=', \Illuminate\Support\Facades\DB::raw('CONCAT(scheduled_date, " ", (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.work_shift_id))'))
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

    private function getRevenueChartData(int $rid, ?int $branchId = null): array
    {
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

        $forecast7 = $this->forecast->forecast7Days($rid, $branchId);
        foreach ($forecast7 as $f) {
            $revenueChartData[] = $f;
        }

        return $revenueChartData;
    }

    private function getChannelChartData(int $rid, ?int $branchId = null): array
    {
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
    }

    private function getTopProductsChartData(int $rid, ?int $branchId = null): array
    {
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
    }

    private function getForecastData(int $rid, ?int $branchId = null): ?array
    {
        return $this->forecast->forecastTomorrow($rid, $branchId);
    }

    private function getHealthScore(int $rid, ?int $branchId = null): int
    {
        $cached = Cache::get("health_score:{$rid}" . ($branchId ? ":{$branchId}" : ""));
        if ($cached !== null) {
            return (int) $cached;
        }

        $score = $this->calculateBranchHealthScore($rid, $branchId);
        Cache::put("health_score:{$rid}" . ($branchId ? ":{$branchId}" : ""), $score, 60);

        return $score;
    }

    private function getShiftRevenue(int $rid, ?int $branchId = null): array
    {
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
    }

    private function getOwnerSummary(int $rid, ?int $branchId = null): array
    {
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
    }
}
