<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BusinessIntelligenceService
{
    public function getRevenueTrend(int $restaurantId, int $months = 12): array
    {
        return Cache::remember("bi_revenue_trend:{$restaurantId}:{$months}", 300, function () use ($restaurantId, $months) {
            $data = DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subMonths($months))
                ->select(
                    DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('AVG(total_amount) as avg_order')
                )
                ->groupBy(DB::raw("DATE_FORMAT(completed_at, '%Y-%m')"))
                ->orderBy('month')
                ->get();

            $result = $data->map(fn ($r) => [
                'month' => $r->month,
                'revenue' => (float) $r->revenue,
                'orders' => (int) $r->orders,
                'avg_order' => round((float) $r->avg_order),
            ])->all();

            // YoY comparison
            foreach ($result as $i => &$item) {
                $prevYear = date('Y-m', strtotime($item['month'] . '-01 -12 months'));
                $prev = collect($result)->firstWhere('month', $prevYear);
                $item['yoy_growth'] = $prev && $prev['revenue'] > 0
                    ? round((($item['revenue'] - $prev['revenue']) / $prev['revenue']) * 100, 1)
                    : null;
            }

            return $result;
        });
    }

    public function getUnitEconomics(int $restaurantId, int $days = 30): array
    {
        return Cache::remember("bi_unit_economics:{$restaurantId}:{$days}", 300, function () use ($restaurantId, $days) {
            $totalRevenue = (float) DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->sum('total_amount');

            $newCustomers = (int) DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            $totalCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'purchase')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->sum('total_cost');

            $wasteCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'waste')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->sum('total_cost');

            $totalCustomers = (int) DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->count();

            $avgOrdersPerCustomer = $totalCustomers > 0
                ? (float) DB::table('orders_unified')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->count() / $totalCustomers
                : 0;

            $avgOrderValue = (float) DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->avg('total_amount') ?? 0;

            $ltv = round($avgOrderValue * $avgOrdersPerCustomer);
            $cac = $newCustomers > 0 ? round($totalCost / $newCustomers) : 0;
            $ltvCacRatio = $cac > 0 ? round($ltv / $cac, 2) : 0;

            $grossMargin = $totalRevenue > 0 ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 1) : 0;

            return [
                'revenue' => $totalRevenue,
                'cogs' => $totalCost,
                'waste_cost' => $wasteCost,
                'gross_margin' => $grossMargin,
                'new_customers' => $newCustomers,
                'total_customers' => $totalCustomers,
                'avg_order_value' => round($avgOrderValue),
                'ltv' => $ltv,
                'cac' => $cac,
                'ltv_cac_ratio' => $ltvCacRatio,
                'avg_orders_per_customer' => round($avgOrdersPerCustomer, 1),
            ];
        });
    }

    public function getCohortAnalysis(int $restaurantId): array
    {
        return Cache::remember("bi_cohort_analysis:{$restaurantId}", 300, function () use ($restaurantId) {
            $cohorts = DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as cohort_month"), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->orderBy('cohort_month')
                ->get();

            $result = [];
            foreach ($cohorts as $cohort) {
                $customerIds = DB::table('customers')
                    ->where('restaurant_id', $restaurantId)
                    ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$cohort->cohort_month])
                    ->pluck('id');

                $retention = [];
                for ($m = 0; $m <= 5; $m++) {
                    $from = \Carbon\Carbon::createFromFormat('Y-m', $cohort->cohort_month)->addMonths($m)->startOfMonth();
                    $to = $from->copy()->endOfMonth();

                    if ($from->isFuture()) break;

                    $returning = DB::table('orders_unified')
                        ->where('restaurant_id', $restaurantId)
                        ->where('status', 'completed')
                        ->whereIn('customer_id', $customerIds)
                        ->whereBetween('completed_at', [$from, $to])
                        ->distinct('customer_id')
                        ->count('customer_id');

                    $retention[] = [
                        'month' => $m,
                        'returning' => $returning,
                        'rate' => $cohort->count > 0 ? round(($returning / $cohort->count) * 100, 1) : 0,
                    ];
                }

                $result[] = [
                    'cohort' => $cohort->cohort_month,
                    'size' => (int) $cohort->count,
                    'retention' => $retention,
                ];
            }

            return $result;
        });
    }

    public function getBreakEvenAnalysis(int $restaurantId, int $days = 30): array
    {
        return Cache::remember("bi_break_even:{$restaurantId}:{$days}", 300, function () use ($restaurantId, $days) {
            $revenue = (float) DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->sum('total_amount');

            $variableCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->whereIn('type', ['purchase', 'usage'])
                ->where('occurred_at', '>=', now()->subDays($days))
                ->sum('total_cost');

            $fixedCost = (float) DB::table('operating_expenses')
                ->where('restaurant_id', $restaurantId)
                ->whereNotNull('recurring_expense_id')
                ->where('expense_date', '>=', now()->subDays($days))
                ->sum('amount');

            $ordersCount = (int) DB::table('orders_unified')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->count();

            $avgOrderValue = $ordersCount > 0 ? $revenue / $ordersCount : 0;
            $variableCostPerOrder = $ordersCount > 0 ? $variableCost / $ordersCount : 0;
            $contributionMargin = $avgOrderValue - $variableCostPerOrder;
            $breakEvenOrders = $contributionMargin > 0 ? (int) ceil($fixedCost / $contributionMargin) : 0;
            $breakEvenRevenue = $breakEvenOrders * $avgOrderValue;

            $dailyOrders = $days > 0 ? round($ordersCount / $days, 1) : 0;
            $breakEvenDays = $dailyOrders > 0 ? (int) ceil($breakEvenOrders / $dailyOrders) : 0;

            return [
                'revenue' => $revenue,
                'variable_cost' => $variableCost,
                'fixed_cost' => $fixedCost,
                'total_orders' => $ordersCount,
                'avg_order_value' => round($avgOrderValue),
                'variable_cost_per_order' => round($variableCostPerOrder),
                'contribution_margin' => round($contributionMargin),
                'break_even_orders' => $breakEvenOrders,
                'break_even_revenue' => round($breakEvenRevenue),
                'break_even_days' => $breakEvenDays,
                'is_profitable' => $ordersCount >= $breakEvenOrders,
            ];
        });
    }

    public function getBenchmark(int $restaurantId, int $days = 30): array
    {
        $economics = $this->getUnitEconomics($restaurantId, $days);

        $benchmarks = [
            ['metric' => 'Gross Margin', 'value' => $economics['gross_margin'], 'unit' => '%', 'industry_low' => 55, 'industry_high' => 70, 'good_direction' => 'higher'],
            ['metric' => 'Food Cost %', 'value' => $economics['revenue'] > 0 ? round(($economics['cogs'] / $economics['revenue']) * 100, 1) : 0, 'unit' => '%', 'industry_low' => 25, 'industry_high' => 35, 'good_direction' => 'lower'],
            ['metric' => 'Waste %', 'value' => $economics['revenue'] > 0 ? round(($economics['waste_cost'] / $economics['revenue']) * 100, 1) : 0, 'unit' => '%', 'industry_low' => 3, 'industry_high' => 8, 'good_direction' => 'lower'],
            ['metric' => 'LTV/CAC Ratio', 'value' => $economics['ltv_cac_ratio'], 'unit' => 'x', 'industry_low' => 2, 'industry_high' => 5, 'good_direction' => 'higher'],
            ['metric' => 'Avg Order Value', 'value' => $economics['avg_order_value'], 'unit' => 'đ', 'industry_low' => 80000, 'industry_high' => 200000, 'good_direction' => 'higher'],
        ];

        foreach ($benchmarks as &$b) {
            $b['status'] = match (true) {
                $b['good_direction'] === 'higher' && $b['value'] >= $b['industry_high'] => 'excellent',
                $b['good_direction'] === 'higher' && $b['value'] >= $b['industry_low'] => 'normal',
                $b['good_direction'] === 'lower' && $b['value'] <= $b['industry_low'] => 'excellent',
                $b['good_direction'] === 'lower' && $b['value'] <= $b['industry_high'] => 'normal',
                default => 'warning',
            };
        }

        return $benchmarks;
    }
}

