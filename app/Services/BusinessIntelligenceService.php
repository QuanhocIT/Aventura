<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\Tenant\TenantContext;

class BusinessIntelligenceService
{
    public function getRevenueTrend(int $restaurantId, int $months = 12, ?int $branchId = null): array
    {
        return Cache::remember($this->cacheKey('bi_revenue_trend', $restaurantId, $months, $branchId), 300, function () use ($restaurantId, $months, $branchId) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $monthFormat = $isSqlite ? "strftime('%Y-%m', completed_at)" : "DATE_FORMAT(completed_at, '%Y-%m')";

            $q1 = DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subMonths($months))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->select(
                    DB::raw("{$monthFormat} as month"),
                    DB::raw('1 as order_count'),
                    'total_amount'
                );

            $q2 = DB::table('orders_archive')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subMonths($months))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->select(
                    DB::raw("{$monthFormat} as month"),
                    DB::raw('1 as order_count'),
                    'total_amount'
                );

            $data = DB::query()
                ->fromSub($q1->unionAll($q2), 'unified')
                ->select(
                    'month',
                    DB::raw('SUM(order_count) as orders'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('AVG(total_amount) as avg_order')
                )
                ->groupBy('month')
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
                $prevYear = date('Y-m', strtotime($item['month'].'-01 -12 months'));
                $prev = collect($result)->firstWhere('month', $prevYear);
                $item['yoy_growth'] = $prev && $prev['revenue'] > 0
                    ? round((($item['revenue'] - $prev['revenue']) / $prev['revenue']) * 100, 1)
                    : null;
            }

            return $result;
        });
    }

    public function getUnitEconomics(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        return Cache::remember($this->cacheKey('bi_unit_economics', $restaurantId, $days, $branchId), 300, function () use ($restaurantId, $days, $branchId) {
            $totalRevenue = (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_amount') +
                (float) DB::table('orders_archive')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subDays($days))
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->sum('total_amount');

            $newCustomers = (int) DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count();

            $totalCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'purchase')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost');

            $wasteCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->where('type', 'waste')
                ->where('occurred_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost');

            $totalCustomers = (int) DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count();

            $avgOrdersPerCustomer = $totalCustomers > 0
                ? (float) (DB::table('orders')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->whereNull('deleted_at')
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->count() +
                    DB::table('orders_archive')
                        ->where('restaurant_id', $restaurantId)
                        ->where('status', 'completed')
                        ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                        ->count()) / $totalCustomers
                : 0;

            $sum1 = (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_amount');
            $sum2 = (float) DB::table('orders_archive')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_amount');
            $count1 = (int) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count();
            $count2 = (int) DB::table('orders_archive')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count();

            $avgOrderValue = ($count1 + $count2) > 0 ? ($sum1 + $sum2) / ($count1 + $count2) : 0;

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

    public function getCohortAnalysis(int $restaurantId, ?int $branchId = null): array
    {
        return Cache::remember($this->cacheKey('bi_cohort_analysis', $restaurantId, $branchId), 300, function () use ($restaurantId, $branchId) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $startDate = now()->subMonths(6)->startOfMonth();

            // 1. Get cohort sizes (total customers registered per month)
            $cohortFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
            $cohorts = DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->where('created_at', '>=', $startDate)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->select(DB::raw("{$cohortFormat} as cohort_month"), DB::raw('COUNT(*) as count'))
                ->groupBy('cohort_month')
                ->orderBy('cohort_month')
                ->get()
                ->keyBy('cohort_month');

            // 2. Query returning customers in a single query
            $monthDiffRaw = $isSqlite
                ? "((strftime('%Y', o.completed_at) - strftime('%Y', c.created_at)) * 12 + (strftime('%m', o.completed_at) - strftime('%m', c.created_at)))"
                : 'TIMESTAMPDIFF(MONTH, c.created_at, o.completed_at)';

            $cohortRaw = $isSqlite ? "strftime('%Y-%m', c.created_at)" : "DATE_FORMAT(c.created_at, '%Y-%m')";

            $q1 = DB::table('customers as c')
                ->join('orders as o', 'c.id', '=', 'o.customer_id')
                ->where('c.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->whereNull('o.deleted_at')
                ->where('c.created_at', '>=', $startDate)
                ->when($branchId !== null, fn ($query) => $query->where('c.branch_id', $branchId)->where('o.branch_id', $branchId))
                ->whereRaw("{$monthDiffRaw} BETWEEN 0 AND 5")
                ->select(
                    DB::raw("{$cohortRaw} as cohort_month"),
                    DB::raw("{$monthDiffRaw} as diff_month"),
                    'c.id as customer_id'
                );

            $q2 = DB::table('customers as c')
                ->join('orders_archive as o', 'c.id', '=', 'o.customer_id')
                ->where('c.restaurant_id', $restaurantId)
                ->where('o.status', 'completed')
                ->where('c.created_at', '>=', $startDate)
                ->when($branchId !== null, fn ($query) => $query->where('c.branch_id', $branchId)->where('o.branch_id', $branchId))
                ->whereRaw("{$monthDiffRaw} BETWEEN 0 AND 5")
                ->select(
                    DB::raw("{$cohortRaw} as cohort_month"),
                    DB::raw("{$monthDiffRaw} as diff_month"),
                    'c.id as customer_id'
                );

            $retentionData = DB::query()
                ->fromSub($q1->unionAll($q2), 'unified')
                ->select(
                    'cohort_month',
                    'diff_month',
                    DB::raw('COUNT(DISTINCT customer_id) as returning_count')
                )
                ->groupBy('cohort_month', 'diff_month')
                ->get()
                ->groupBy('cohort_month');

            // 3. Assemble response
            $result = [];
            foreach ($cohorts as $cohortMonth => $cohort) {
                $cohortSize = (int) $cohort->count;
                $retention = [];

                $monthData = $retentionData->get($cohortMonth, collect())->keyBy('diff_month');

                for ($m = 0; $m <= 5; $m++) {
                    $cohortDate = Carbon::createFromFormat('Y-m', $cohortMonth)->addMonths($m)->startOfMonth();
                    if ($cohortDate->isFuture()) {
                        break;
                    }

                    $returning = (int) ($monthData->get($m)?->returning_count ?? 0);
                    $retention[] = [
                        'month' => $m,
                        'returning' => $returning,
                        'rate' => $cohortSize > 0 ? round(($returning / $cohortSize) * 100, 1) : 0,
                    ];
                }

                $result[] = [
                    'cohort' => $cohortMonth,
                    'size' => $cohortSize,
                    'retention' => $retention,
                ];
            }

            return $result;
        });
    }

    public function getBreakEvenAnalysis(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        return Cache::remember($this->cacheKey('bi_break_even', $restaurantId, $days, $branchId), 300, function () use ($restaurantId, $days, $branchId) {
            $revenue = (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_amount') +
                (float) DB::table('orders_archive')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subDays($days))
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                    ->sum('total_amount');

            $variableCost = (float) DB::table('inventory_transactions')
                ->where('restaurant_id', $restaurantId)
                ->whereIn('type', ['purchase', 'usage'])
                ->where('occurred_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('total_cost');

            $fixedCost = (float) DB::table('operating_expenses')
                ->where('restaurant_id', $restaurantId)
                ->whereNotNull('recurring_expense_id')
                ->where('expense_date', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('amount');

            $ordersCount = (int) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('completed_at', '>=', now()->subDays($days))
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->count() +
                (int) DB::table('orders_archive')
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', now()->subDays($days))
                    ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
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

    public function getBenchmark(int $restaurantId, int $days = 30, ?int $branchId = null): array
    {
        $economics = $this->getUnitEconomics($restaurantId, $days, $branchId);

        $benchmarks = [
            ['metric' => 'Tỷ suất Lợi nhuận gộp (Gross Margin)', 'value' => $economics['gross_margin'], 'unit' => '%', 'industry_low' => 55, 'industry_high' => 70, 'good_direction' => 'higher'],
            ['metric' => 'Tỷ lệ Chi phí Nguyên vật liệu (Food Cost %)', 'value' => $economics['revenue'] > 0 ? round(($economics['cogs'] / $economics['revenue']) * 100, 1) : 0, 'unit' => '%', 'industry_low' => 25, 'industry_high' => 35, 'good_direction' => 'lower'],
            ['metric' => 'Tỷ lệ Hao hụt/Hủy món (Waste %)', 'value' => $economics['revenue'] > 0 ? round(($economics['waste_cost'] / $economics['revenue']) * 100, 1) : 0, 'unit' => '%', 'industry_low' => 3, 'industry_high' => 8, 'good_direction' => 'lower'],
            ['metric' => 'Tỷ lệ LTV/CAC', 'value' => $economics['ltv_cac_ratio'], 'unit' => 'x', 'industry_low' => 2, 'industry_high' => 5, 'good_direction' => 'higher'],
            ['metric' => 'Giá trị đơn hàng trung bình (AOV)', 'value' => $economics['avg_order_value'], 'unit' => 'đ', 'industry_low' => 80000, 'industry_high' => 200000, 'good_direction' => 'higher'],
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

    private function cacheKey(string $prefix, int $restaurantId, mixed ...$parts): string
    {
        $branchId = array_pop($parts);
        $parts[] = TenantContext::branchScopeKey($branchId);

        return implode(':', array_merge([$prefix, $restaurantId], array_map(
            static fn ($part): string => (string) $part,
            $parts,
        )));
    }
}
