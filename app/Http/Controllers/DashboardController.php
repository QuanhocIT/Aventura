<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user       = auth()->user();
        $restaurant = $user?->restaurant;

        $stats        = null;
        $recentOrders = [];
        $alerts       = [];
        $revenueChartData = [];
        $channelChartData = [];
        $topProductsChartData = [];
        $operationFeed = [];
        $tablesData = [];
        $lowStockInventory = [];

        if ($restaurant) {
            $todaySummary = RestaurantRevenueSummary::where('restaurant_id', $restaurant->id)
                ->whereDate('summary_date', today())
                ->first();

            $ordersToday    = Order::where('restaurant_id', $restaurant->id)->whereDate('created_at', today());
            $totalToday     = (clone $ordersToday)->count();
            $completedToday = (clone $ordersToday)->where('status', 'completed')->count();
            $cancelledToday = (clone $ordersToday)->where('status', 'cancelled')->count();

            $stats = [
                'products_count'    => $restaurant->products()->count(),
                'employees_count'   => $restaurant->employees()->where('status', 'active')->count(),
                'branches_count'    => $restaurant->branches()->count(),
                'tables_count'      => $restaurant->tables()->count(),
                'orders_today'      => $totalToday,
                'revenue_today'     => $todaySummary?->total_revenue ?? 0,
                'orders_completed'  => $completedToday,
                'orders_cancelled'  => $cancelledToday,
            ];

            // 1. Doanh thu & số lượng đơn hàng trong 7 ngày gần nhất (tính cả hôm nay)
            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $dailyStats = Order::where('restaurant_id', $restaurant->id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $sevenDaysAgo)
                ->selectRaw("DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as count")
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 6; $i >= 0; $i--) {
                $targetDate = now()->subDays($i);
                $dateStr = $targetDate->format('Y-m-d');
                $label = $targetDate->format('d/m');
                $dayStat = $dailyStats->get($dateStr);
                $revenueChartData[] = [
                    'date' => $label,
                    'revenue' => $dayStat ? (float) $dayStat->revenue : 0,
                    'orders' => $dayStat ? (int) $dayStat->count : 0,
                ];
            }

            // 2. Cơ cấu đơn hàng theo các kênh bán hàng trong 7 ngày qua
            $channelStats = Order::where('restaurant_id', $restaurant->id)
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

            $totalChannelsCount = $channelStats->sum('count');
            foreach ($channelStats as $cs) {
                $channelChartData[] = [
                    'channel' => $cs->channel,
                    'label' => $channelNames[$cs->channel] ?? $cs->channel,
                    'count' => (int) $cs->count,
                    'percentage' => $totalChannelsCount > 0 ? round(($cs->count / $totalChannelsCount) * 100, 1) : 0,
                ];
            }

            // 3. Top 5 món bán chạy nhất trong 30 ngày qua
            $topProductsChartData = \App\Models\OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $restaurant->id)
                ->where('orders.status', 'completed')
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->selectRaw('products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.quantity * order_items.unit_price) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->take(5)
                ->get()
                ->map(fn($item) => [
                    'name' => $item->name,
                    'quantity' => (int) $item->total_qty,
                    'revenue' => (float) $item->total_revenue,
                ])
                ->all();

            // ── Đơn hàng gần đây (5 đơn mới nhất) ─────────────
            $recentOrders = Order::with('table')
                ->where('restaurant_id', $restaurant->id)
                ->latest()
                ->take(5)
                ->get()
                ->map(fn (Order $o) => [
                    'id'           => $o->id,
                    'order_number' => $o->order_number,
                    'table_name'   => $o->table?->name ?? null,
                    'total_amount' => (float) $o->total_amount,
                    'status'       => $o->status,
                    'payment_status' => $o->payment_status,
                    'channel'      => $o->channel,
                    'created_at'   => $o->created_at?->format('H:i'),
                ])
                ->all();

            // ── Cảnh báo & Nhắc nhở ─────────────────────────────
            // 1. Đơn pending lâu hơn 30 phút
            $stuckPending = Order::where('restaurant_id', $restaurant->id)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(30))
                ->count();
            if ($stuckPending > 0) {
                $alerts[] = [
                    'type'    => 'warning',
                    'message' => "{$stuckPending} đơn hàng đang chờ xử lý quá 30 phút",
                    'href'    => '/orders?status=pending',
                ];
            }

            // 2. Tỉ lệ huỷ cao hôm nay (> 20%)
            if ($totalToday > 0 && ($cancelledToday / $totalToday) > 0.2) {
                $pct = round(($cancelledToday / $totalToday) * 100);
                $alerts[] = [
                    'type'    => 'danger',
                    'message' => "Tỉ lệ huỷ đơn hôm nay cao: {$pct}% ({$cancelledToday}/{$totalToday} đơn)",
                    'href'    => '/orders?status=cancelled',
                ];
            }

            // 3. Đơn confirmed/preparing không chuyển sang completed (> 1 giờ)
            $stuckProcessing = Order::where('restaurant_id', $restaurant->id)
                ->whereIn('status', ['confirmed', 'preparing'])
                ->where('updated_at', '<', now()->subHour())
                ->count();
            if ($stuckProcessing > 0) {
                $alerts[] = [
                    'type'    => 'info',
                    'message' => "{$stuckProcessing} đơn đang chế biến chưa được cập nhật trạng thái",
                    'href'    => '/orders',
                ];
            }

            // ── 1. Live Activity Feed ──
            $feedItems = [];

            // 1a. Đơn hàng gần đây
            $ordersForFeed = Order::with('table')
                ->where('restaurant_id', $restaurant->id)
                ->latest()
                ->take(8)
                ->get();

            $channelLabels = [
                'dine_in'  => 'Tại bàn',
                'takeaway' => 'Mang về',
                'delivery' => 'Giao hàng',
                'qr'       => 'Mã QR',
            ];

            foreach ($ordersForFeed as $o) {
                $time = $o->created_at;
                $diff = $time->diffForHumans();
                $tblStr = $o->table ? " tại Bàn {$o->table->name}" : "";
                $chanStr = $channelLabels[$o->channel] ?? $o->channel;

                if ($o->status === 'pending') {
                    $feedItems[] = [
                        'type' => 'order_pending',
                        'title' => 'Đơn hàng mới chờ duyệt',
                        'description' => "Đơn #{$o->order_number}{$tblStr} đang chờ xác nhận ({$chanStr} - " . number_format($o->total_amount) . "đ).",
                        'amount' => (float)$o->total_amount,
                        'time' => $diff,
                        'timestamp' => $time->timestamp,
                        'icon' => 'ShoppingCart',
                        'color' => 'amber',
                        'link' => '/orders?status=pending'
                    ];
                } elseif ($o->status === 'preparing') {
                    $feedItems[] = [
                        'type' => 'order_preparing',
                        'title' => 'Đang chuẩn bị món',
                        'description' => "Bếp đang chuẩn bị món ăn cho Đơn #{$o->order_number}{$tblStr} ({$chanStr}).",
                        'amount' => (float)$o->total_amount,
                        'time' => $diff,
                        'timestamp' => $o->updated_at->timestamp,
                        'icon' => 'Utensils',
                        'color' => 'violet',
                        'link' => '/orders'
                    ];
                } elseif ($o->status === 'completed') {
                    $feedItems[] = [
                        'type' => 'order_completed',
                        'title' => 'Đơn hoàn thành',
                        'description' => "Đơn #{$o->order_number}{$tblStr} đã phục vụ xong và thanh toán thành công.",
                        'amount' => (float)$o->total_amount,
                        'time' => $diff,
                        'timestamp' => $o->updated_at->timestamp,
                        'icon' => 'CheckCircle2',
                        'color' => 'emerald',
                        'link' => '/orders'
                    ];
                } elseif ($o->status === 'cancelled') {
                    $feedItems[] = [
                        'type' => 'order_cancelled',
                        'title' => 'Đơn hàng bị hủy',
                        'description' => "Đơn #{$o->order_number} đã bị hủy bỏ khỏi hệ thống.",
                        'amount' => (float)$o->total_amount,
                        'time' => $diff,
                        'timestamp' => $o->updated_at->timestamp,
                        'icon' => 'XCircle',
                        'color' => 'rose',
                        'link' => '/orders?status=cancelled'
                    ];
                }
            }

            // 1b. Cảnh báo tồn kho sắp hết
            $lowStocksForFeed = \App\Models\Inventory::query()
                ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                ->where('inventories.restaurant_id', $restaurant->id)
                ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                ->select(
                    'ingredients.name as ingredient_name',
                    'inventories.quantity_on_hand',
                    'ingredients.min_stock_level',
                    'units.name as unit_name',
                    'inventories.updated_at'
                )
                ->take(4)
                ->get();

            foreach ($lowStocksForFeed as $item) {
                $time = $item->updated_at ?? now();
                $feedItems[] = [
                    'type' => 'stock_warning',
                    'title' => 'Cảnh báo hết nguyên liệu',
                    'description' => "Nguyên liệu \"{$item->ingredient_name}\" sắp hết, chỉ còn " . round($item->quantity_on_hand, 2) . " " . ($item->unit_name ?? 'đơn vị') . " (Tối thiểu: " . round($item->min_stock_level, 2) . ").",
                    'amount' => null,
                    'time' => $time->diffForHumans(),
                    'timestamp' => $time->timestamp,
                    'icon' => 'AlertTriangle',
                    'color' => 'rose',
                    'link' => '/inventory'
                ];
            }

            // 1c. Sự kiện vào ca nhân sự
            $activeSchedulesForFeed = \App\Models\ScheduleAssignment::with(['employee', 'shift'])
                ->where('restaurant_id', $restaurant->id)
                ->whereDate('scheduled_date', today())
                ->whereNotNull('check_in_at')
                ->latest('check_in_at')
                ->take(4)
                ->get();

            foreach ($activeSchedulesForFeed as $sa) {
                $time = $sa->check_in_at;
                $feedItems[] = [
                    'type' => 'shift_checkin',
                    'title' => 'Nhân sự vào ca',
                    'description' => "Nhân viên {$sa->employee->name} đã check-in vào ca " . ($sa->shift ? $sa->shift->name : 'làm việc') . " lúc " . $sa->check_in_at->format('H:i') . ".",
                    'amount' => null,
                    'time' => $time->diffForHumans(),
                    'timestamp' => $time->timestamp,
                    'icon' => 'Users',
                    'color' => 'sky',
                    'link' => '/employees'
                ];
            }

            // Sắp xếp Feed theo dòng thời gian giảm dần (mới nhất lên đầu)
            usort($feedItems, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });

            $operationFeed = array_slice($feedItems, 0, 8);

            // ── 2. Table Status Grid ──
            $tablesData = \App\Models\RestaurantTable::with('area')
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('name')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'area' => $t->area?->name ?? 'Khu vực chung',
                    'capacity' => $t->capacity,
                    'status' => $t->status, // 'available', 'occupied', 'reserved', 'cleaning'
                ])
                ->all();

            // ── 3. Low Stock Inventory Alert ──
            $lowStockInventory = \App\Models\Inventory::query()
                ->join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
                ->leftJoin('units', 'ingredients.unit_id', '=', 'units.id')
                ->where('inventories.restaurant_id', $restaurant->id)
                ->whereRaw('inventories.quantity_on_hand <= ingredients.min_stock_level')
                ->select(
                    'ingredients.id',
                    'ingredients.name as ingredient_name',
                    'inventories.quantity_on_hand',
                    'ingredients.min_stock_level',
                    'ingredients.reorder_level',
                    'units.name as unit_name'
                )
                ->orderBy('inventories.quantity_on_hand')
                ->take(8)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->id,
                    'ingredient_name' => $item->ingredient_name,
                    'quantity_on_hand' => (float)$item->quantity_on_hand,
                    'min_stock_level' => (float)$item->min_stock_level,
                    'reorder_level' => (float)$item->reorder_level,
                    'unit_name' => $item->unit_name ?? 'đơn vị',
                ])
                ->all();
        }

        $onboardingStatus   = $user?->onboarding_status ?? [];
        $onboardingComplete = !empty($onboardingStatus['day_1']['completed_at'])
            && !empty($onboardingStatus['day_2']['completed_at'])
            && !empty($onboardingStatus['day_3']['completed_at']);

        return Inertia::render('Dashboard', [
            'operationFeed'        => $operationFeed,
            'tablesData'           => $tablesData,
            'lowStockInventory'    => $lowStockInventory,
            'stats'                => $stats,
            'onboardingComplete'   => $onboardingComplete,
            'recentOrders'         => $recentOrders,
            'alerts'               => $alerts,
            'revenueChartData'     => $revenueChartData,
            'channelChartData'     => $channelChartData,
            'topProductsChartData' => $topProductsChartData,
        ]);
    }
}
