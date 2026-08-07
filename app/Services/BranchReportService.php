<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Live revenue aggregates used by tenant reports.
 *
 * Revenue summaries created before branch reporting are restaurant-wide. The
 * report screens therefore use the same hot + archive source and apply the
 * active branch scope before aggregating.
 */
class BranchReportService
{
    public function dailyRevenue(
        int $restaurantId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $branchId = null,
    ): Collection {
        $dateExpr = $this->dateExpression('completed_at');
        $createdDateExpr = $this->dateExpression('created_at');

        $completed = DB::table('orders_unified')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("{$dateExpr} as report_date")
            ->selectRaw('COUNT(*) as completed_order_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as gross_revenue')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(service_charge), 0) as service_charge_total')
            ->groupBy(DB::raw($dateExpr))
            ->get()
            ->keyBy('report_date');

        $orders = DB::table('orders_unified')
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("{$createdDateExpr} as report_date")
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->groupBy(DB::raw($createdDateExpr))
            ->get()
            ->keyBy('report_date');

        $cogsDateExpr = $this->dateExpression('orders_unified.completed_at');
        $cogs = DB::table('order_items_unified')
            ->join('orders_unified', function ($join) {
                $join->on('order_items_unified.order_id', '=', 'orders_unified.id')
                    ->on('order_items_unified.restaurant_id', '=', 'orders_unified.restaurant_id');
            })
            ->join('product_recipes', 'product_recipes.product_id', '=', 'order_items_unified.product_id')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->join('units as recipe_units', 'recipe_units.id', '=', 'product_recipes.unit_id')
            ->join('units as ingredient_units', 'ingredient_units.id', '=', 'ingredients.unit_id')
            ->where('orders_unified.restaurant_id', $restaurantId)
            ->where('orders_unified.status', 'completed')
            ->whereBetween('orders_unified.completed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNull('ingredients.deleted_at')
            ->when($branchId !== null, fn ($query) => $query->where('orders_unified.branch_id', $branchId))
            ->selectRaw("{$cogsDateExpr} as report_date")
            ->selectRaw('SUM(order_items_unified.quantity * product_recipes.quantity * (COALESCE(recipe_units.conversion_factor_to_base, 1) / COALESCE(ingredient_units.conversion_factor_to_base, 1)) * (1 + product_recipes.waste_rate / 100) * ingredients.average_cost) as cogs_amount')
            ->groupBy(DB::raw($cogsDateExpr))
            ->get()
            ->keyBy('report_date');

        // Payments are not archived separately. They still carry branch_id,
        // so this remains correct for all hot payment records.
        $paymentDateExpr = $this->dateExpression('paid_at');
        $payments = DB::table('payments')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw("{$paymentDateExpr} as report_date")
            ->selectRaw("SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_revenue")
            ->selectRaw("SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount ELSE 0 END) as bank_transfer_revenue")
            ->selectRaw("SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as card_revenue")
            ->selectRaw("SUM(CASE WHEN payment_method = 'ewallet' THEN amount ELSE 0 END) as ewallet_revenue")
            ->groupBy(DB::raw($paymentDateExpr))
            ->get()
            ->keyBy('report_date');

        $rows = collect();
        for ($date = $start->copy()->startOfDay(); $date->lte($end->copy()->startOfDay()); $date = $date->addDay()) {
            $key = $date->toDateString();
            $completedRow = $completed->get($key);
            $orderRow = $orders->get($key);
            $cogsRow = $cogs->get($key);
            $paymentRow = $payments->get($key);

            $gross = (float) ($completedRow->gross_revenue ?? 0);
            $discount = (float) ($completedRow->discount_total ?? 0);
            $net = $gross - $discount;
            $completedCount = (int) ($completedRow->completed_order_count ?? 0);
            $cogsAmount = (float) ($cogsRow->cogs_amount ?? 0);

            $rows->push([
                'date' => $date->format('d/m'),
                'date_full' => $key,
                'order_count' => (int) ($orderRow->order_count ?? 0),
                'completed_order_count' => $completedCount,
                'cancelled_count' => (int) ($orderRow->cancelled_count ?? 0),
                'net_revenue' => $net,
                'gross_revenue' => $gross,
                'discount_total' => $discount,
                'cogs_amount' => $cogsAmount,
                'gross_profit' => $net - $cogsAmount,
                'cash_revenue' => (float) ($paymentRow->cash_revenue ?? 0),
                'bank_transfer_revenue' => (float) ($paymentRow->bank_transfer_revenue ?? 0),
                'card_revenue' => (float) ($paymentRow->card_revenue ?? 0),
                'ewallet_revenue' => (float) ($paymentRow->ewallet_revenue ?? 0),
                'average_order_value' => $completedCount > 0 ? $net / $completedCount : 0.0,
            ]);
        }

        return $rows;
    }

    public function summarize(Collection $rows): array
    {
        return [
            'net_revenue' => (float) $rows->sum('net_revenue'),
            'gross_revenue' => (float) $rows->sum('gross_revenue'),
            'gross_profit' => (float) $rows->sum('gross_profit'),
            'order_count' => (int) $rows->sum('order_count'),
            'completed_order_count' => (int) $rows->sum('completed_order_count'),
            'discount_total' => (float) $rows->sum('discount_total'),
            'cancelled_count' => (int) $rows->sum('cancelled_count'),
        ];
    }

    public function reconcile(
        int $restaurantId,
        CarbonInterface $start,
        CarbonInterface $end,
        SupportCollection $branches,
    ): array {
        $branchTotals = $branches->map(function ($branch) use ($restaurantId, $start, $end) {
            $rows = $this->dailyRevenue($restaurantId, $start, $end, (int) $branch->id);
            $totals = $this->summarize($rows);

            return [
                'branch_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'net_revenue' => $totals['net_revenue'],
                'gross_profit' => $totals['gross_profit'],
                'order_count' => $totals['order_count'],
            ];
        })->values();

        $sumBranchRevenue = (float) $branchTotals->sum('net_revenue');
        $sumBranchProfit = (float) $branchTotals->sum('gross_profit');
        $allTotals = $this->summarize($this->dailyRevenue($restaurantId, $start, $end));

        return [
            'branches' => $branchTotals->all(),
            'all' => $allTotals,
            'sum_branch_net_revenue' => $sumBranchRevenue,
            'sum_branch_gross_profit' => $sumBranchProfit,
            'revenue_difference' => $allTotals['net_revenue'] - $sumBranchRevenue,
            'gross_profit_difference' => $allTotals['gross_profit'] - $sumBranchProfit,
            'is_consistent' => abs($allTotals['net_revenue'] - $sumBranchRevenue) < 0.01
                && abs($allTotals['gross_profit'] - $sumBranchProfit) < 0.01,
        ];
    }

    private function dateExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }
}
