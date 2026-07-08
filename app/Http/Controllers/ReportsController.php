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
            '30days' => today()->subDays(29),
            'month'  => today()->startOfMonth(),
            default  => today()->subDays(6),
        };

        $dayCount = $startDate->diffInDays($endDate) + 1;

        // ── Current period summaries ───────────────────────────────────────────
        $rawSummaries = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [$startDate, $endDate])
            ->orderBy('summary_date')
            ->get();

        $summaries = $rawSummaries->map(fn ($s) => [
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
                ->join('orders', 'order_items_unified.order_id', '=', 'orders_unified.id')
                ->join('products', 'order_items_unified.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $restaurantId)
                ->where('orders.status', 'completed')
                ->whereBetween('orders.completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->select('products.name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.line_total) as total_revenue'))
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
        $bcgData        = $this->menuInsight->getBcgData($restaurantId, $days);
        $productMargins = $this->menuInsight->getProductMargins($restaurantId, $days);

        return Inertia::render('reports/Index', [
            'summaries'        => $summaries->values(),
            'totals'           => $totals,
            'topProducts'      => $topProducts,
            'period'           => $period,
            'dateRange'        => ['start' => $startDate->format('d/m/Y'), 'end' => $endDate->format('d/m/Y')],
            'paymentBreakdown' => $paymentBreakdown,
            'periodComparison' => $periodComparison,
            'cancelledStats'   => $cancelledStats,
            'peakHours'        => $peakHours,
            'todaySummary'     => $todaySummary,
            'comparisonCards'  => $comparisonCards,
            'profitBreakdown'  => $profitBreakdown,
            'canGenerate'      => $user->hasAnyRole(['owner', 'manager']),
            'canSendEmail'     => $user->hasAnyRole(['owner']),
            // Mới
            'bcgData'          => $bcgData,
            'productMargins'   => $productMargins,
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

        $csv = "Ngay,Don hang,Hoan thanh,Huy,Doanh thu,Giam gia,Loi nhuan\n";
        foreach ($summaries as $s) {
            $csv .= implode(',', [
                $s->summary_date->format('Y-m-d'),
                $s->order_count,
                $s->completed_order_count,
                $s->cancelled_order_count,
                $s->net_revenue,
                $s->discount_total,
                $s->gross_profit,
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=bao-cao-{$period}.csv",
        ]);
    }
}

