<?php

namespace App\Services;

use App\Models\OperatingExpense;
use App\Models\Order;
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
    public function buildMonthly(int $restaurantId, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();

        $revenue = $this->revenueForPeriod($restaurantId, $start, $end);
        $cogs = $this->cogsForPeriod($restaurantId, $start, $end);
        $labor = $this->laborCostForPeriod($restaurantId, $start, $end);
        $opex = $this->operatingExpensesForPeriod($restaurantId, $start, $end);

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
        ];
    }

    /** P&L tháng hiện tại + so sánh tháng trước + chuỗi 6 tháng cho biểu đồ. */
    public function buildWithComparison(int $restaurantId, int $year, int $month): array
    {
        $current = $this->buildMonthly($restaurantId, $year, $month);

        $prevDate = CarbonImmutable::create($year, $month, 1)->subMonth();
        $previous = $this->buildMonthly($restaurantId, $prevDate->year, $prevDate->month);

        // Chuỗi 6 tháng gần nhất (kể cả tháng đang xem) cho sparkline
        $trend = [];
        $cursor = CarbonImmutable::create($year, $month, 1)->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $point = $this->buildMonthly($restaurantId, $cursor->year, $cursor->month);
            $trend[] = [
                'period' => $point['period'],
                'net_revenue' => $point['revenue']['net_revenue'],
                'net_profit' => $point['net_profit'],
            ];
            $cursor = $cursor->addMonth();
        }

        return [
            'current' => $current,
            'previous' => [
                'period' => $previous['period'],
                'net_revenue' => $previous['revenue']['net_revenue'],
                'gross_profit' => $previous['gross_profit'],
                'net_profit' => $previous['net_profit'],
            ],
            'trend' => $trend,
        ];
    }

    private function revenueForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(subtotal), 0) as gross_revenue,
                COALESCE(SUM(discount_amount), 0) as discount_total,
                COALESCE(SUM(service_charge), 0) as service_charge_total,
                COALESCE(SUM(total_amount), 0) as net_revenue
            ')
            ->first();

        return [
            'order_count' => (int) $row->order_count,
            'gross_revenue' => round((float) $row->gross_revenue),
            'discount_total' => round((float) $row->discount_total),
            'service_charge_total' => round((float) $row->service_charge_total),
            'net_revenue' => round((float) $row->net_revenue),
        ];
    }

    /** Cùng công thức COGS với DailyReportService: định lượng BOM x (1 + hao hụt) x giá vốn TB. */
    private function cogsForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $result = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_recipes', 'product_recipes.product_id', '=', 'order_items.product_id')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.completed_at', [$start, $end])
            ->whereNull('ingredients.deleted_at')
            ->selectRaw('
                SUM(
                    order_items.quantity
                    * product_recipes.quantity
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
    private function laborCostForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereBetween('pay_period_end', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'net_salary'])
            ->sum(fn (Salary $s) => (float) $s->net_salary);
    }

    private function operatingExpensesForPeriod(int $restaurantId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $expenses = OperatingExpense::withoutGlobalScopes()
            ->with('category:id,name')
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
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
