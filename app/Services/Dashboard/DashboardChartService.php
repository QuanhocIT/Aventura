<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkShift;
use App\Services\ForecastService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển nguyên logic từ app/Http/Controllers/DashboardController.php (các
 * helper private cũ: getRevenueChartData, getChannelChartData,
 * getTopProductsChartData, getPeakHoursChartData, getShiftRevenue) — di chuyển
 * thuần tuý, không đổi hành vi/cache key/TTL.
 */
class DashboardChartService
{
    public function __construct(
        private ForecastService $forecast,
    ) {}

    public function getRevenueChartData(int $rid, ?int $branchId = null, bool $hasAiForecasting = false): array
    {
        $key = "dashboard:revenue_chart:{$rid}".($branchId ? ":{$branchId}" : '').':'.today()->toDateString().':'.($hasAiForecasting ? '1' : '0');

        return Cache::remember($key, 300, function () use ($rid, $branchId, $hasAiForecasting) {
            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $dailyStats = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('created_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as count')
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $revenueChartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $targetDate = now()->subDays($i);
                $dateStr = $targetDate->format('Y-m-d');
                $label = $targetDate->format('d/m');
                $dayStat = $dailyStats->get($dateStr);
                $revenueChartData[] = [
                    'date' => $label,
                    'revenue' => $dayStat ? (float) $dayStat->revenue : 0,
                    'orders' => $dayStat ? (int) $dayStat->count : 0,
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

    public function getChannelChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:channel_chart:{$rid}".($branchId ? ":{$branchId}" : '').':'.today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $channelStats = Order::where('restaurant_id', $rid)
                ->where('created_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->get();

            $channelNames = [
                'dine_in' => 'Tại bàn',
                'takeaway' => 'Mang về',
                'delivery' => 'Giao hàng',
                'qr' => 'Mã QR',
            ];

            $channelChartData = [];
            $totalChannelsCount = $channelStats->sum('count');
            foreach ($channelStats as $cs) {
                $channelChartData[] = [
                    'channel' => $cs->channel,
                    'label' => $channelNames[$cs->channel] ?? $cs->channel,
                    'count' => (int) $cs->count,
                    'percentage' => $totalChannelsCount > 0
                        ? round(($cs->count / $totalChannelsCount) * 100, 1) : 0,
                ];
            }

            return $channelChartData;
        });
    }

    public function getTopProductsChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:top_products:{$rid}".($branchId ? ":{$branchId}" : '').':'.today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            return OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
                ->where('orders.status', 'completed')
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->selectRaw('products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.quantity * order_items.unit_price) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->take(5)
                ->get()
                ->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => (int) $item->total_qty,
                    'revenue' => (float) $item->total_revenue,
                ])
                ->all();
        });
    }

    public function getPeakHoursChartData(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:peak_hours:{$rid}".($branchId ? ":{$branchId}" : '').':'.today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $thirtyDaysAgo = now()->subDays(30)->startOfDay();
            $hourlyStats = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->selectRaw(DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER) as hour, COUNT(*) as count" : 'HOUR(created_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->keyBy('hour');

            $peakHoursData = [];
            for ($h = 6; $h <= 23; $h++) {
                $stat = $hourlyStats->get($h);
                $peakHoursData[] = [
                    'hour' => $h,
                    'label' => sprintf('%02dh', $h),
                    'count' => $stat ? (int) $stat->count : 0,
                ];
            }

            return $peakHoursData;
        });
    }

    public function getShiftRevenue(int $rid, ?int $branchId = null): array
    {
        $key = "dashboard:shift_revenue:{$rid}".($branchId ? ":{$branchId}" : '').':'.today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $shifts = WorkShift::where('restaurant_id', $rid)
                ->where('status', 'active')
                ->orderBy('start_time')
                ->get();

            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $orders = Order::where('restaurant_id', $rid)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $sevenDaysAgo)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->select('total_amount', 'completed_at')
                ->get();

            $shiftRevenue = [];
            foreach ($shifts as $shift) {
                $row = ['shift_name' => $shift->name, 'days' => []];
                for ($d = 6; $d >= 0; $d--) {
                    $day = now()->subDays($d);
                    $start = $day->copy()->setTimeFromTimeString($shift->start_time);
                    $end = $shift->is_overnight
                        ? $day->copy()->addDay()->setTimeFromTimeString($shift->end_time)
                        : $day->copy()->setTimeFromTimeString($shift->end_time);

                    $rev = $orders->filter(function ($order) use ($start, $end) {
                        return $order->completed_at >= $start && $order->completed_at <= $end;
                    })->sum('total_amount');

                    $row['days'][] = [
                        'date' => $day->format('d/m'),
                        'revenue' => (float) $rev,
                    ];
                }
                $shiftRevenue[] = $row;
            }

            return $shiftRevenue;
        });
    }
}
