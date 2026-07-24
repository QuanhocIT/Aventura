<?php

namespace App\Http\Controllers;

use App\Jobs\SendDailyReportEmail;
use App\Models\RestaurantRevenueSummary;
use App\Models\Salary;
use App\Services\DailyReportService;
use App\Services\MenuInsightService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct(private MenuInsightService $menuInsight) {}

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $date = $request->input('date', today()->toDateString());

        app(DailyReportService::class)->generateForRestaurant(
            $request->user()->restaurant_id,
            $date,
        );

        return back()->with('success', 'Đã tạo báo cáo ngày ' . Carbon::parse($date)->format('d/m/Y') . '.');
    }

    public function sendReport(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner']), 403);

        $date = $request->input('date', today()->toDateString());
        SendDailyReportEmail::dispatch($request->user()->restaurant_id, $date);

        return back()->with('success', 'Đã gửi email báo cáo vào hàng đợi.');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', '7days');

        $endDate   = today();
        $startDate = match ($period) {
            'today'  => today(),
            '30days' => today()->subDays(29),
            'month'  => today()->startOfMonth(),
            default  => today()->subDays(6),
        };

        $dayCount = $startDate->diffInDays($endDate) + 1;

        // ── Current period summaries ───────────────────────────────────────────
        $rawSummaries = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn ($s) => $s->summary_date->toDateString());

        $summaries = collect();
        for ($date = $startDate->copy(); $date->lte($endDate); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            if ($rawSummaries->has($dateString)) {
                $s = $rawSummaries->get($dateString);
                $summaries->push([
                    'date'                  => $s->summary_date->format('d/m'),
                    'date_full'             => $s->summary_date->toDateString(),
                    'order_count'           => (int) $s->order_count,
                    'completed_order_count' => (int) $s->completed_order_count,
                    'cancelled_count'       => (int) $s->cancelled_order_count,
                    'net_revenue'           => (float) $s->net_revenue,
                    'gross_revenue'         => (float) $s->gross_revenue,
                    'discount_total'        => (float) $s->discount_total,
                    'cogs_amount'           => (float) $s->cogs_amount,
                    'gross_profit'          => (float) $s->gross_profit,
                    'cash_revenue'          => (float) $s->cash_revenue,
                    'bank_transfer_revenue' => (float) $s->bank_transfer_revenue,
                    'card_revenue'          => (float) $s->card_revenue,
                    'ewallet_revenue'       => (float) $s->ewallet_revenue,
                    'average_order_value'   => (float) $s->average_order_value,
                ]);
            } else {
                $summaries->push([
                    'date'                  => $date->format('d/m'),
                    'date_full'             => $dateString,
                    'order_count'           => 0,
                    'completed_order_count' => 0,
                    'cancelled_count'       => 0,
                    'net_revenue'           => 0.0,
                    'gross_revenue'         => 0.0,
                    'discount_total'        => 0.0,
                    'cogs_amount'           => 0.0,
                    'gross_profit'          => 0.0,
                    'cash_revenue'          => 0.0,
                    'bank_transfer_revenue' => 0.0,
                    'card_revenue'          => 0.0,
                    'ewallet_revenue'       => 0.0,
                    'average_order_value'   => 0.0,
                ]);
            }
        }

        // ── Period totals ─────────────────────────────────────────────────────
        $totals = [
            'net_revenue'           => $summaries->sum('net_revenue'),
            'gross_revenue'         => $summaries->sum('gross_revenue'),
            'gross_profit'          => $summaries->sum('gross_profit'),
            'order_count'           => $summaries->sum('order_count'),
            'completed_order_count' => $summaries->sum('completed_order_count'),
            'discount_total'        => $summaries->sum('discount_total'),
            'cancelled_count'       => $summaries->sum('cancelled_count'),
        ];

        // ── Payment breakdown (kỳ này) ────────────────────────────────────────
        $totalPayment = max(
            $summaries->sum('cash_revenue')
            + $summaries->sum('bank_transfer_revenue')
            + $summaries->sum('card_revenue')
            + $summaries->sum('ewallet_revenue'),
            1
        );

        $paymentBreakdown = [
            'cash'          => (float) $summaries->sum('cash_revenue'),
            'bank_transfer' => (float) $summaries->sum('bank_transfer_revenue'),
            'card'          => (float) $summaries->sum('card_revenue'),
            'ewallet'       => (float) $summaries->sum('ewallet_revenue'),
            'cash_pct'          => round($summaries->sum('cash_revenue') / $totalPayment * 100, 1),
            'bank_transfer_pct' => round($summaries->sum('bank_transfer_revenue') / $totalPayment * 100, 1),
            'card_pct'          => round($summaries->sum('card_revenue') / $totalPayment * 100, 1),
            'ewallet_pct'       => round($summaries->sum('ewallet_revenue') / $totalPayment * 100, 1),
        ];

        // ── Period comparison (kỳ trước cùng độ dài) ─────────────────────────
        $prevEnd   = $startDate->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($dayCount - 1);

        $prevSummaries = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [$prevStart, $prevEnd])
            ->get();

        $prevNet    = (float) $prevSummaries->sum('net_revenue');
        $prevOrders = (int)   $prevSummaries->sum('completed_order_count');

        $currentNet    = (float) $totals['net_revenue'];
        $currentOrders = (int)   $totals['completed_order_count'];

        $revenueChangePct = $prevNet > 0 ? round(($currentNet - $prevNet) / $prevNet * 100, 1) : null;
        $orderChangePct   = $prevOrders > 0 ? round(($currentOrders - $prevOrders) / $prevOrders * 100, 1) : null;

        $avgCurrent  = $currentOrders > 0 ? $currentNet / $currentOrders : 0;
        $avgPrev     = $prevOrders > 0 ? $prevNet / $prevOrders : 0;
        $avgChangePct = $avgPrev > 0 ? round(($avgCurrent - $avgPrev) / $avgPrev * 100, 1) : null;

        $periodComparison = [
            'prev_net_revenue'   => $prevNet,
            'prev_order_count'   => $prevOrders,
            'revenue_change_pct' => $revenueChangePct,
            'order_change_pct'   => $orderChangePct,
            'avg_change_pct'     => $avgChangePct,
            'prev_date_range'    => [
                'start' => $prevStart->format('d/m/Y'),
                'end'   => $prevEnd->format('d/m/Y'),
            ],
            'has_prev_data' => $prevSummaries->isNotEmpty(),
        ];

        // ── Cancelled stats ───────────────────────────────────────────────────
        $totalCancelledOrders = (int) $summaries->sum('cancelled_count');
        $cancelledStats = [
            'total_cancelled'   => $totalCancelledOrders,
            'cancellation_rate' => $totals['order_count'] > 0
                ? round($totalCancelledOrders / $totals['order_count'] * 100, 1)
                : 0.0,
        ];

        // ── Peak hours (P1: cache 10 phút cho query analytics nặng) ────────────────
        // Query này JOIN orders + GROUP BY HOUR có thể scan hàng triệu rows khi
        // mặt dùng lưu lượng lớn. Cache theo restaurant + period + date.
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $hourExpr = $isSqlite ? "CAST(strftime('%H', completed_at) AS INTEGER)" : "HOUR(completed_at)";
        $peakCacheKey = "reports_peak:{$restaurantId}:{$period}:" . today()->toDateString();

        $peakHours = Cache::remember($peakCacheKey, 600, function () use ($restaurantId, $startDate, $endDate, $isSqlite, $hourExpr) {
            $peakRows = DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->selectRaw("{$hourExpr} as hour, SUM(total_amount) as revenue, COUNT(*) as order_count")
                ->groupBy(DB::raw($hourExpr))
                ->orderBy('hour')
                ->get();

            $maxPeakRevenue = $peakRows->max('revenue') ?: 1;

            return $peakRows->map(fn ($r) => [
                'hour'        => (int) $r->hour,
                'label'       => sprintf('%02d:00', $r->hour),
                'revenue'     => (float) $r->revenue,
                'order_count' => (int) $r->order_count,
                'width_pct'   => round((float) $r->revenue / $maxPeakRevenue * 100, 1),
            ])->values();
        });

        // ── Top 10 products (P1: cache 10 phút) ────────────────────────────────
        $topProductsCacheKey = "reports_top_products:{$restaurantId}:{$period}:" . today()->toDateString();

        $topProducts = Cache::remember($topProductsCacheKey, 600, function () use ($restaurantId, $startDate, $endDate) {
            return DB::table('order_items_unified')
                ->join('orders_unified', 'order_items_unified.order_id', '=', 'orders_unified.id')
                ->join('products', 'order_items_unified.product_id', '=', 'products.id')
                ->where('orders_unified.restaurant_id', $restaurantId)
                ->where('orders_unified.status', 'completed')
                ->whereBetween('orders_unified.completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->select('products.name', DB::raw('SUM(order_items_unified.quantity) as total_qty'), DB::raw('SUM(order_items_unified.line_total) as total_revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_revenue')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'name'          => $r->name,
                    'total_qty'     => (int) $r->total_qty,
                    'total_revenue' => (float) $r->total_revenue,
                ])->values();
        });

        // ── Today summary ─────────────────────────────────────────────────────
        $todayRecord = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereDate('summary_date', today())
            ->first();

        $yesterdayRecord = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereDate('summary_date', today()->subDay())
            ->first();

        $todaySummary = $todayRecord ? [
            'net_revenue'         => (float) $todayRecord->net_revenue,
            'gross_revenue'       => (float) $todayRecord->gross_revenue,
            'completed_count'     => (int) $todayRecord->completed_order_count,
            'order_count'         => (int) $todayRecord->order_count,
            'average_order_value' => (float) $todayRecord->average_order_value,
            'calculated_at'       => $todayRecord->calculated_at?->format('H:i d/m/Y'),
        ] : null;

        $comparisonCards = null;
        if ($todayRecord && $yesterdayRecord) {
            $revDelta    = $todayRecord->net_revenue - $yesterdayRecord->net_revenue;
            $prevNet2    = (float) $yesterdayRecord->net_revenue;
            $revDeltaPct = $prevNet2 > 0 ? round(($revDelta / $prevNet2) * 100, 1) : 0.0;
            $orderDelta  = $todayRecord->completed_order_count - $yesterdayRecord->completed_order_count;

            $comparisonCards = [
                'revenue_delta'     => (float) $revDelta,
                'revenue_delta_pct' => $revDeltaPct,
                'order_delta'       => (int) $orderDelta,
                'yesterday_revenue' => (float) $yesterdayRecord->net_revenue,
                'yesterday_orders'  => (int) $yesterdayRecord->completed_order_count,
            ];
        }

        // ── Net Profit = Gross Profit - Payroll - OPEX ────────────────────────
        $totalCogs = $summaries->sum('cogs_amount');

        $totalPayroll = (float) Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('pay_period_start', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('net_salary');

        $totalOpex = (float) \App\Models\OperatingExpense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        $grossProfit = (float) $totals['gross_profit'];
        $netProfit   = $grossProfit - $totalPayroll - $totalOpex;
        $netRevenue  = (float) $totals['net_revenue'];

        $profitBreakdown = [
            'gross_profit'   => $grossProfit,
            'total_cogs'     => (float) $totalCogs,
            'total_payroll'  => $totalPayroll,
            'total_opex'     => $totalOpex,
            'net_profit'     => $netProfit,
            'net_profit_pct' => $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 1) : 0.0,
            'cogs_pct'       => $netRevenue > 0 ? round(($totalCogs / $netRevenue) * 100, 1) : 0.0,
            'payroll_pct'    => $netRevenue > 0 ? round(($totalPayroll / $netRevenue) * 100, 1) : 0.0,
            'opex_pct'       => $netRevenue > 0 ? round(($totalOpex / $netRevenue) * 100, 1) : 0.0,
        ];

        // ── Product analysis (BCG + margins) ─────────────────────────────────
        $days = $startDate->diffInDays($endDate) + 1;

        // Tự động cập nhật báo cáo ngày hôm nay vào CSDL (có cooldown 10 phút để tránh quá tải DB)
        $cooldownKey = "reports_generate_cooldown:{$restaurantId}";
        if (!Cache::has($cooldownKey)) {
            try {
                app(DailyReportService::class)->generateForRestaurant($restaurantId, today()->toDateString());
                Cache::put($cooldownKey, true, 600); // 10 minutes cooldown
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('ReportsController: Lỗi tự động cập nhật báo cáo ngày: ' . $e->getMessage());
            }
        }

        return Inertia::render('reports/Index', [
            'summaries'          => $summaries->values(),
            'totals'             => $totals,
            'topProducts'        => collect($topProducts)->values()->all(),
            'period'             => $period,
            'dateRange'          => ['start' => $startDate->format('d/m/Y'), 'end' => $endDate->format('d/m/Y')],
            'paymentBreakdown'   => $paymentBreakdown,
            'periodComparison'   => $periodComparison,
            'cancelledStats'     => $cancelledStats,
            'peakHours'          => collect($peakHours)->values()->all(),
            'todaySummary'       => $todaySummary,
            'comparisonCards'    => $comparisonCards,
            'profitBreakdown'    => $profitBreakdown,
            'canGenerate'        => $user->hasAnyRole(['owner', 'manager']),
            'canSendEmail'       => $user->hasAnyRole(['owner']),
            'bcgData'            => Inertia::defer(fn() => collect($this->menuInsight->getBcgData($restaurantId, $days))->values()->all()),
            'productMargins'     => Inertia::defer(fn() => collect($this->menuInsight->getProductMargins($restaurantId, $days))->values()->all()),
            'todayRealtimeStats' => Inertia::defer(function() use ($restaurantId) {
                $todayStart = Carbon::today()->startOfDay();
                $todayEnd   = Carbon::today()->endOfDay();

                // 1. Số đơn hàng đã thanh toán & doanh thu tương ứng
                $todayPaidOrders = DB::table('orders')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$todayStart, $todayEnd])
                    ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue')
                    ->first();
                $todayPaidCount   = (int) ($todayPaidOrders->count ?? 0);
                $todayPaidRevenue = (float) ($todayPaidOrders->revenue ?? 0.0);

                // 2. Số đơn hàng đang hoạt động (chưa thanh toán) & doanh thu tạm tính
                $todayActiveOrders = DB::table('orders')
                    ->where('restaurant_id', $restaurantId)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue')
                    ->first();
                $todayActiveCount   = (int) ($todayActiveOrders->count ?? 0);
                $todayActiveRevenue = (float) ($todayActiveOrders->revenue ?? 0.0);

                // 3. Số đơn đã hủy hôm nay
                $todayCancelledCount = DB::table('orders')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'cancelled')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count();

                // 4. Tổng số đơn hàng hôm nay
                $todayTotalOrdersCount = DB::table('orders')
                    ->where('restaurant_id', $restaurantId)
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count();

                // 5. Tổng doanh thu hôm nay (Paid + Active)
                $todayTotalRevenue = $todayPaidRevenue + $todayActiveRevenue;

                // 6. Giá trị trung bình/đơn hôm nay
                $todayAvgOrderValue = $todayTotalOrdersCount > 0 ? $todayTotalRevenue / $todayTotalOrdersCount : 0;

                return [
                    'total_revenue'    => $todayTotalRevenue,
                    'total_orders'     => $todayTotalOrdersCount,
                    'active_orders'    => $todayActiveCount,
                    'active_revenue'   => $todayActiveRevenue,
                    'paid_orders'      => $todayPaidCount,
                    'paid_revenue'     => $todayPaidRevenue,
                    'cancelled_orders' => $todayCancelledCount,
                    'avg_order_value'  => $todayAvgOrderValue,
                    'calculated_at'    => now()->format('H:i:s d/m/Y'),
                ];
            }),
        ]);
    }

    public function exportPdf(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Xuất báo cáo PDF',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $date = $request->input('date', today()->toDateString());
        $restaurantId = $request->user()->restaurant_id;

        $summary = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->where('summary_date', $date)
            ->first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-pdf', [
            'restaurant' => $restaurant,
            'summary' => $summary,
            'date' => Carbon::parse($date)->format('d/m/Y'),
        ]);

        return $pdf->download("bao-cao-{$date}.pdf");
    }

    public function exportCsv(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'advanced_analytics')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'advanced_analytics',
                'feature_label' => 'Xuất báo cáo CSV',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Chuyên Nghiệp',
            ]);
        }

        $period = $request->input('period', '30days');
        $days = match ($period) { '7days' => 6, 'month' => today()->day - 1, default => 29 };
        $startDate = today()->subDays($days);
        $restaurantId = $request->user()->restaurant_id;

        $summaries = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [$startDate, today()])
            ->orderBy('summary_date')
            ->get();

        $csv = "\xEF\xBB\xBF\"Ngày\",\"Tổng đơn\",\"Đơn hoàn thành\",\"Đơn hủy\",\"Doanh thu thuần (đ)\",\"Giảm giá (đ)\",\"Lợi nhuận gộp (đ)\"\n";
        foreach ($summaries as $s) {
            $csv .= implode(',', [
                '"' . $s->summary_date->format('d/m/Y') . '"',
                $s->order_count,
                $s->completed_order_count,
                $s->cancelled_order_count,
                (float) $s->net_revenue,
                (float) $s->discount_total,
                (float) $s->gross_profit,
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=bao-cao-doanh-thu-{$period}.csv",
        ]);
    }

    public function exportExcel(Request $request)
    {
        return $this->exportCsv($request);
    }

    /**
     * Báo cáo đối soát chéo lượng món ăn bếp chế biến (KDS) vs lượng món đã thanh toán ở POS.
     */
    public function crossReconciliation(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', '7days');

        $endDate   = today();
        $startDate = match ($period) {
            '30days' => today()->subDays(29),
            'month'  => today()->startOfMonth(),
            default  => today()->subDays(6),
        };

        $reconciliation = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('order_items.restaurant_id', $restaurantId)
            ->whereBetween('order_items.created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.price as product_price',
                'products.cost_price as cost_price',
                DB::raw('SUM(CASE WHEN order_items.prepared_at IS NOT NULL OR order_items.served_at IS NOT NULL THEN order_items.quantity ELSE 0 END) as cooked_qty'),
                DB::raw('SUM(CASE WHEN orders.status = "completed" AND orders.payment_status = "paid" AND order_items.status != "cancelled" THEN order_items.quantity ELSE 0 END) as billed_qty')
            )
            ->groupBy('products.id', 'products.name', 'products.price', 'products.cost_price')
            ->get()
            ->map(function ($row) {
                $row->cooked_qty = (float) $row->cooked_qty;
                $row->billed_qty = (float) $row->billed_qty;
                $row->discrepancy = max(0.0, $row->cooked_qty - $row->billed_qty);
                $row->potential_loss_cost = $row->discrepancy * (float) ($row->cost_price ?: ($row->product_price * 0.4));
                $row->potential_loss_retail = $row->discrepancy * (float) $row->product_price;
                return $row;
            })
            ->filter(fn ($row) => $row->cooked_qty > 0 || $row->billed_qty > 0)
            ->values();

        $totals = [
            'total_cooked' => $reconciliation->sum('cooked_qty'),
            'total_billed' => $reconciliation->sum('billed_qty'),
            'total_discrepancy' => $reconciliation->sum('discrepancy'),
            'total_loss_cost' => $reconciliation->sum('potential_loss_cost'),
            'total_loss_retail' => $reconciliation->sum('potential_loss_retail'),
        ];

        return Inertia::render('reports/Reconciliation', [
            'reconciliation' => $reconciliation,
            'totals' => $totals,
            'period' => $period,
            'dateRange' => ['start' => $startDate->format('d/m/Y'), 'end' => $endDate->format('d/m/Y')],
        ]);
    }
}

