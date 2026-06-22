<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use App\Services\ForecastService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private ForecastService $forecast) {}

    public function index(): mixed
    {
        $user       = auth()->user();
        $restaurant = $user?->restaurant;

        if ($user && $user->can('manage_kitchen') && !$user->can('view_report')) {
            return redirect()->route('kitchen.index');
        }

        if ($user && $user->can('create_orders') && !$user->can('view_report')) {
            return app(CashierDashboardController::class)->index();
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

        if ($restaurant) {
            $rid = $restaurant->id;

            $todaySummary    = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_date', today())->first();
            $yesterdaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_date', today()->subDay())->first();

            $ordersToday    = Order::where('restaurant_id', $rid)->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
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

            // Cache resource counts (thay đổi ít, không cần query mỗi request)
            $resourceCounts = Cache::remember("dashboard_counts:{$rid}", 300, function () use ($restaurant) {
                return [
                    'products_count'  => $restaurant->products()->count(),
                    'employees_count' => $restaurant->employees()->where('status', 'active')->count(),
                    'branches_count'  => $restaurant->branches()->count(),
                    'tables_count'    => $restaurant->tables()->count(),
                ];
            });

            $stats = array_merge($resourceCounts, [
                'orders_today'      => $totalToday,
                'revenue_today'     => $revenueToday,
                'orders_completed'  => $completedToday,
                'orders_cancelled'  => $cancelledToday,
                // Xu hướng + chỉ số bổ sung
                'revenue_trend'     => $revTrend,
                'order_trend'       => $orderTrend,
                'profit_margin_today' => $profitMargin,
                'completion_rate'   => $completionRate,
            ]);

            // ── Business Health Score nâng cao (0–100) ──────────────────────
            // 7 chiều đánh giá: tài chính + vận hành + nhân sự + kho + NPS
            $cancellationRate = $totalToday > 0 ? ($cancelledToday / $totalToday) * 100 : 0;
            $revenueGrowthScore = $revTrend !== null ? min(100, max(0, 50 + $revTrend)) : 50;

            // Điểm nhân sự: % nhân viên check-in đúng giờ hôm nay
            $scheduledToday = \App\Models\ScheduleAssignment::where('restaurant_id', $rid)
                ->where('scheduled_date', today())->count();
            $checkedInOnTime = $scheduledToday > 0
                ? \App\Models\ScheduleAssignment::where('restaurant_id', $rid)
                    ->where('scheduled_date', today())
                    ->whereNotNull('check_in_at')
                    ->whereColumn('check_in_at', '<=', \Illuminate\Support\Facades\DB::raw('CONCAT(scheduled_date, " ", (SELECT start_time FROM work_shifts WHERE id = schedule_assignments.work_shift_id))'))
                    ->count()
                : null;
            $punctualityScore = $scheduledToday > 0 && $checkedInOnTime !== null
                ? min(100, ($checkedInOnTime / $scheduledToday) * 100)
                : 75; // giá trị trung tính nếu chưa có ca hôm nay

            // Điểm kho: % nguyên liệu có tồn kho > mức min
            $totalIngredients = \App\Models\Inventory::where('restaurant_id', $rid)->count();
            $safeIngredients  = $totalIngredients > 0
                ? \App\Models\Inventory::join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                    ->where('inventories.restaurant_id', $rid)
                    ->whereRaw('inventories.quantity_on_hand > ingredients.min_stock_level')
                    ->count()
                : null;
            $inventoryScore = $totalIngredients > 0 && $safeIngredients !== null
                ? ($safeIngredients / $totalIngredients) * 100
                : 80; // giá trị trung tính nếu chưa có dữ liệu kho

            // Điểm NPS: lấy NPS 7 ngày gần nhất (scale 0–100 từ -100 đến +100)
            $npsData = \App\Models\CustomerFeedback::where('restaurant_id', $rid)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
                ->first();
            $npsScore = $npsData && $npsData->cnt > 0
                ? min(100, max(0, (($npsData->avg_rating - 1) / 4) * 100)) // scale 1–5 thành 0–100
                : 70; // giá trị trung tính

            $healthScore = (int) round(
                min(100, max(0,
                    ($completionRate    * 0.25) +   // Tỉ lệ hoàn thành đơn
                    (max(0, 100 - $cancellationRate * 4) * 0.15) + // Kiểm soát hủy đơn
                    ($revenueGrowthScore * 0.20) +  // Tăng trưởng doanh thu
                    (min(100, $profitMargin * 1.5) * 0.15) + // Biên lợi nhuận
                    ($punctualityScore  * 0.10) +   // Chấm công đúng giờ
                    ($inventoryScore    * 0.10) +   // Sức khỏe kho
                    ($npsScore          * 0.05)     // Đánh giá khách hàng
                ))
            );
            // Lưu cache để Inertia defer không tính lại từ đầu
            Cache::put("health_score:{$rid}", $healthScore, 60);

            // ── Dự báo doanh thu ngày mai ────────────────────────────────────
            $forecastData = $this->forecast->forecastTomorrow($rid);

            // Các dữ liệu biểu đồ và thống kê nặng đã được bóc tách và tải chậm (Inertia Defer)

            // ── AI Cảnh báo mới ──────────────────────────────────────────────
            // Alert 1: Pending > 30 phút
            $stuckPending = Order::where('restaurant_id', $rid)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))->count();
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
                ->where('updated_at', '<', now()->subHour())->count();
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
                ->where('restaurant_id', $rid)->latest()->take(8)->get();

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
                ->whereNotNull('check_in_at')->latest('check_in_at')->take(4)->get();

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
                ->where('restaurant_id', $rid)->orderBy('name')->get()
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
                ->where('restaurant_id', $rid)->latest()->take(5)->get()
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
        }

        $onboardingStatus   = $user?->onboarding_status ?? [];
        $onboardingComplete = !$user?->can('approve_requests') || (
            !empty($onboardingStatus['day_1']['completed_at'])
            && !empty($onboardingStatus['day_2']['completed_at'])
            && !empty($onboardingStatus['day_3']['completed_at'])
        );

        return Inertia::render('Dashboard', [
            'operationFeed'        => $operationFeed,
            'tablesData'           => $tablesData,
            'lowStockInventory'    => $lowStockInventory,
            'stats'                => $stats,
            'onboardingComplete'   => $onboardingComplete,
            'recentOrders'         => $recentOrders,
            'alerts'               => $alerts,
            'revenueChartData'     => $restaurant ? Inertia::defer(fn () => $this->getRevenueChartData($restaurant->id)) : [],
            'channelChartData'     => $restaurant ? Inertia::defer(fn () => $this->getChannelChartData($restaurant->id)) : [],
            'topProductsChartData' => $restaurant ? Inertia::defer(fn () => $this->getTopProductsChartData($restaurant->id)) : [],
            'forecastData'         => $restaurant ? Inertia::defer(fn () => $this->getForecastData($restaurant->id)) : null,
            // Health score đã được tính và cache trong index() — defer chỉ đọc cache, không query lại
            'healthScore'          => $restaurant ? Inertia::defer(fn () => $this->getHealthScore($restaurant->id)) : null,
            'shiftRevenue'         => $restaurant ? Inertia::defer(fn () => $this->getShiftRevenue($restaurant->id)) : [],
            'ownerSummary'         => $restaurant ? Inertia::defer(fn () => $this->getOwnerSummary($restaurant->id)) : null,
        ]);
    }

    private function getRevenueChartData(int $rid): array
    {
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $dailyStats = Order::where('restaurant_id', $rid)
            ->where('status', 'completed')
            ->where('created_at', '>=', $sevenDaysAgo)
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

        $forecast7 = $this->forecast->forecast7Days($rid);
        foreach ($forecast7 as $f) {
            $revenueChartData[] = $f;
        }

        return $revenueChartData;
    }

    private function getChannelChartData(int $rid): array
    {
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $channelStats = Order::where('restaurant_id', $rid)
            ->where('created_at', '>=', $sevenDaysAgo)
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

    private function getTopProductsChartData(int $rid): array
    {
        return \App\Models\OrderItem::query()
            ->join('orders',   'order_items.order_id',   '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $rid)
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

    private function getForecastData(int $rid): ?array
    {
        return $this->forecast->forecastTomorrow($rid);
    }

    private function getHealthScore(int $rid): int
    {
        // Đọc từ cache đã lưu trong index() — tránh tái query toàn bộ DB
        $cached = Cache::get("health_score:{$rid}");
        if ($cached !== null) {
            return (int) $cached;
        }

        // Fallback: tính lại nếu cache miss (ví dụ Defer chạy độc lập)
        $todaySummary     = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today())->first();
        $yesterdaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today()->subDay())->first();

        $ordersToday    = Order::where('restaurant_id', $rid)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()]);
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

        $score = (int) round(
            min(100, max(0,
                ($completionRate       * 0.35) +
                (max(0, 100 - $cancellationRate * 4) * 0.20) +
                ($revenueGrowthScore   * 0.30) +
                (min(100, $profitMargin * 1.5) * 0.15)
            ))
        );

        Cache::put("health_score:{$rid}", $score, 60);

        return $score;
    }

    private function getShiftRevenue(int $rid): array
    {
        $shifts = \App\Models\WorkShift::where('restaurant_id', $rid)
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get();

        // Bulk load all completed orders from the last 7 days to calculate revenues in memory
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $orders = Order::where('restaurant_id', $rid)
            ->where('status', 'completed')
            ->where('completed_at', '>=', $sevenDaysAgo)
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

                // Filter in memory to avoid running a database query in loop
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

    private function getOwnerSummary(int $rid): array
    {
        $topTodayProducts = \App\Models\OrderItem::query()
            ->join('orders',   'order_items.order_id',   '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $rid)
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
            ->count();

        return [
            'top_products_today' => $topTodayProducts,
            'active_shifts'      => $activeShifts,
            'pending_over_20min' => $pendingOrders,
            'revenue_this_week'  => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_type', 'daily')
                ->whereBetween('summary_date', [today()->startOfWeek(), today()])
                ->sum('net_revenue'),
            'revenue_last_week'  => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                ->where('summary_type', 'daily')
                ->whereBetween('summary_date', [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()])
                ->sum('net_revenue'),
        ];
    }
}
