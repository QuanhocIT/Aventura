<?php

namespace App\Http\Controllers;

use App\Models\RestaurantRevenueSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $period = $request->input('period', '7days');

        $endDate   = today();
        $startDate = match ($period) {
            '30days' => today()->subDays(29),
            'month'  => today()->startOfMonth(),
            default  => today()->subDays(6),
        };

        $summaries = RestaurantRevenueSummary::where('restaurant_id', $restaurantId)
            ->where('summary_type', 'daily')
            ->whereBetween('summary_date', [$startDate, $endDate])
            ->orderBy('summary_date')
            ->get()
            ->map(fn ($s) => [
                'date'                  => $s->summary_date->format('d/m'),
                'date_full'             => $s->summary_date->toDateString(),
                'order_count'           => $s->order_count,
                'completed_order_count' => $s->completed_order_count,
                'net_revenue'           => (float) $s->net_revenue,
                'gross_revenue'         => (float) $s->gross_revenue,
                'discount_total'        => (float) $s->discount_total,
                'cogs_amount'           => (float) $s->cogs_amount,
                'gross_profit'          => (float) $s->gross_profit,
            ]);

        // Tổng hợp trong kỳ
        $totals = [
            'net_revenue'           => $summaries->sum('net_revenue'),
            'gross_profit'          => $summaries->sum('gross_profit'),
            'order_count'           => $summaries->sum('order_count'),
            'completed_order_count' => $summaries->sum('completed_order_count'),
            'discount_total'        => $summaries->sum('discount_total'),
        ];

        // Top sản phẩm trong kỳ (từ orders)
        $topProducts = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select('products.name', \DB::raw('SUM(order_items.quantity) as total_qty'), \DB::raw('SUM(order_items.line_total) as total_revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'          => $r->name,
                'total_qty'     => (int) $r->total_qty,
                'total_revenue' => (float) $r->total_revenue,
            ]);

        return Inertia::render('reports/Index', [
            'summaries'   => $summaries->values(),
            'totals'      => $totals,
            'topProducts' => $topProducts,
            'period'      => $period,
            'dateRange'   => ['start' => $startDate->format('d/m/Y'), 'end' => $endDate->format('d/m/Y')],
        ]);
    }
}
