<?php

namespace App\Services;

use App\Models\OperatingExpense;
use App\Models\Order;
use App\Models\RestaurantBranch;
use App\Models\Salary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo Lãi/Lỗ (P&L) tự động theo tháng — tổng hợp từ dữ liệu có sẵn:
 * doanh thu (orders), giá vốn COGS (BOM định lượng x giá vốn nguyên liệu),
 * chi phí nhân sự (bảng lương), chi phí vận hành (operating_expenses).
 */
class ProfitLossService
{
    public function buildMonthly(int $restaurantId, int $year, int $month, ?int $branchId = null): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();

        $revenue = $this->revenueForPeriod($restaurantId, $start, $end, $branchId);
        $cogs = $this->cogsForPeriod($restaurantId, $start, $end, $branchId);
        $labor = $this->laborCostForPeriod($restaurantId, $start, $end, $branchId);
        $opex = $this->operatingExpensesForPeriod($restaurantId, $start, $end, $branchId);

        $grossProfit = $revenue['net_revenue'] - $cogs;
        $netProfit = $grossProfit - $labor - $opex['total'];

        return [
            'period' => $start->format('m/Y'),
            'year' => $year,
            'month' => $month,
            'revenue' => $revenue,
            'cogs' => round($cogs),
            'gross_profit' => round($grossProfit),
            'gross_margin' => $revenue['net_revenue'] > 0 ? round($grossProfit / $revenue['net_revenue'] * 100, 1) : 0,
            'labor_cost' => round($labor),
            'operating_expenses' => $opex,
            'net_profit' => round($netProfit),
            'net_margin' => $revenue['net_revenue'] > 0 ? round($netProfit / $revenue['net_revenue'] * 100, 1) : 0,
            'branch_id' => $branchId,
        ];
    }

    /** P&L tháng hiện tại + so sánh tháng trước + chuỗi 6 tháng cho biểu đồ. */
    public function buildWithComparison(int $restaurantId, int $year, int $month, ?int $branchId = null): array
    {
        $current = $this->buildMonthly($restaurantId, $year, $month, $branchId);

        $prevDate = CarbonImmutable::create($year, $month, 1)->subMonth();
        $previous = $this->buildMonthly($restaurantId, $prevDate->year, $prevDate->month, $branchId);

        // Chuỗi 6 tháng gần nhất (kể cả tháng đang xem) cho sparkline
        $trend = [];
        $cursor = CarbonImmutable::create($year, $month, 1)->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $point = $this->buildMonthly($restaurantId, $cursor->year, $cursor->month, $branchId);
            $trend[] = [
                'period' => $point['period'],
                'net_revenue' => $point['revenue']['net_revenue'],
                'net_profit' => $point['net_profit'],
            ];
            $cursor = $cursor->addMonth();
        }

        $result = [
            'current' => $current,
            'previous' => [
                'period' => $previous['period'],
                'net_revenue' => $previous['revenue']['net_revenue'],
                'gross_profit' => $previous['gross_profit'],
                'net_profit' => $previous['net_profit'],
            ],
            'trend' => $trend,
        ];

        if ($branchId === null) {
            $result['branch_reconciliation'] = $this->reconcileMonthly($restaurantId, $year, $month);
        }

        return $result;
    }

    public function reconcileMonthly(int $restaurantId, int $year, int $month): array
    {
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get(['id', 'name']);
        $reports = $branches->map(function (RestaurantBranch $branch) use ($restaurantId, $year, $month) {
            $report = $this->buildMonthly($restaurantId, $year, $month, (int) $branch->id);

            return [
                'branch_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'net_revenue' => $report['revenue']['net_revenue'],
                'net_profit' => $report['net_profit'],
            ];
        })->values();
        $all = $this->buildMonthly($restaurantId, $year, $month);
        $sumRevenue = (float) $reports->sum('net_revenue');
        $sumProfit = (float) $reports->sum('net_profit');

        return [
            'branches' => $reports->all(),
            'all' => [
                'net_revenue' => $all['revenue']['net_revenue'],
                'net_profit' => $all['net_profit'],
            ],
            'revenue_difference' => $all['revenue']['net_revenue'] - $sumRevenue,
            'profit_difference' => $all['net_profit'] - $sumProfit,
            'is_consistent' => abs($all['revenue']['net_revenue'] - $sumRevenue) < 0.01
                && abs($all['net_profit'] - $sumProfit) < 0.01,
        ];
    }

    private function revenueForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end, ?int $branchId = null): array
    {
        $row = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(subtotal), 0) as gross_revenue,
                COALESCE(SUM(discount_amount), 0) as discount_total,
                COALESCE(SUM(service_charge), 0) as service_charge_total,
                COALESCE(SUM(CASE
                    WHEN total_amount - COALESCE(refund_amount, 0) > 0
                    THEN total_amount - COALESCE(refund_amount, 0)
                    ELSE 0
                END), 0) as net_revenue,
                COALESCE(SUM(COALESCE(refund_amount, 0)), 0) as refund_total
            ')
            ->first();

        return [
            'order_count' => (int) $row->order_count,
            'gross_revenue' => round((float) $row->gross_revenue),
            'discount_total' => round((float) $row->discount_total),
            'service_charge_total' => round((float) $row->service_charge_total),
            'net_revenue' => round((float) $row->net_revenue),
            'refund_total' => round((float) $row->refund_total),
        ];
    }

    /** Cùng công thức COGS với DailyReportService: định lượng BOM x (1 + hao hụt) x giá vốn TB. */
    private function cogsForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end, ?int $branchId = null): float
    {
        // Prefer the immutable inventory usage ledger: it stores the actual
        // batch/average cost selected when the order consumed stock. The BOM
        // query below remains a compatibility fallback for legacy orders
        // created before inventory usage transactions existed.
        $usage = DB::table('inventory_transactions as transactions')
            ->join('orders', 'transactions.order_id', '=', 'orders.id')
            ->where('transactions.restaurant_id', $restaurantId)
            ->where('transactions.type', 'usage')
            ->where('transactions.direction', 'out')
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$start, $end])
            ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->selectRaw('COUNT(*) as usage_count, COALESCE(SUM(transactions.total_cost), 0) as total_cogs')
            ->first();

        if ((int) ($usage->usage_count ?? 0) > 0) {
            return (float) ($usage->total_cogs ?? 0);
        }

        $result = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_recipes', 'product_recipes.product_id', '=', 'order_items.product_id')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->join('units as recipe_units', 'recipe_units.id', '=', 'product_recipes.unit_id')
            ->join('units as ingredient_units', 'ingredient_units.id', '=', 'ingredients.unit_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$start, $end])
            ->when($branchId !== null, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereNull('ingredients.deleted_at')
            ->selectRaw('
                SUM(
                    order_items.quantity
                    * product_recipes.quantity
                    * (COALESCE(recipe_units.conversion_factor_to_base, 1) / COALESCE(ingredient_units.conversion_factor_to_base, 1))
                    * (1 + product_recipes.waste_rate / 100)
                    * ingredients.average_cost
                ) as total_cogs
            ')
            ->value('total_cogs');

        return (float) ($result ?? 0.0);
    }

    /**
     * Lương thuộc về tháng có pay_period_end rơi vào tháng đó (đã duyệt/đã trả).
     * net_salary là cast encrypted — bắt buộc cộng trong PHP, không SUM được bằng SQL.
     */
    private function laborCostForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end, ?int $branchId = null): float
    {
        return Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('pay_period_end', [$start->toDateString(), $end->toDateString()])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'net_salary'])
            ->sum(fn (Salary $s) => (float) $s->net_salary);
    }

    private function operatingExpensesForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end, ?int $branchId = null): array
    {
        $expenses = OperatingExpense::withoutGlobalScopes()
            ->with('category:id,name')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'category_id', 'amount']);

        $byCategory = $expenses
            ->groupBy(fn (OperatingExpense $e) => $e->category?->name ?? 'Chưa phân loại')
            ->map(fn ($group) => round((float) $group->sum('amount')))
            ->sortDesc();

        return [
            'total' => round((float) $expenses->sum('amount')),
            'by_category' => $byCategory->toArray(),
        ];
    }
}
