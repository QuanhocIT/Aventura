<?php

namespace App\Services\Dashboard;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantRevenueSummary;
use App\Models\ScheduleAssignment;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Chuyển nguyên logic từ app/Http/Controllers/DashboardController.php (các
 * helper private cũ: getOwnerSummary, getCashFlowSummary) — di chuyển thuần
 * tuý, không đổi hành vi/cache key/TTL.
 */
class DashboardSummaryService
{
    public function getOwnerSummary(int $rid, ?int $branchId = null): array
    {
        $scopeKey = TenantContext::branchScopeKey($branchId);
        $key = "dashboard:owner_summary:{$rid}:{$scopeKey}:".today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $topTodayProducts = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.restaurant_id', $rid)
                ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [today()->startOfDay(), today()->endOfDay()])
                ->selectRaw('products.name, SUM(order_items.quantity) as total_qty, SUM(order_items.line_total) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_qty')
                ->take(3)
                ->get()
                ->map(fn ($r) => [
                    'name' => $r->name,
                    'qty' => (int) $r->total_qty,
                    'revenue' => (float) $r->total_revenue,
                ])
                ->all();

            $activeShifts = ScheduleAssignment::with(['employee', 'shift'])
                ->where('restaurant_id', $rid)
                ->where('scheduled_date', today())
                ->whereIn('status', ['checked_in', 'scheduled'])
                ->when($branchId, function ($q) use ($branchId) {
                    $q->whereHas('employee', fn ($emp) => $emp->where('branch_id', $branchId));
                })
                ->get()
                ->map(fn ($a) => [
                    'name' => $a->employee?->full_name ?? '—',
                    'shift' => $a->shift?->name ?? '—',
                    'status' => $a->status,
                ])
                ->all();

            $pendingOrders = Order::where('restaurant_id', $rid)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(20))
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->count();

            return [
                'top_products_today' => $topTodayProducts,
                'active_shifts' => $activeShifts,
                'pending_over_20min' => $pendingOrders,
                'revenue_this_week' => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                    ->where('summary_type', 'daily')
                    ->where('scope_key', TenantContext::summaryScopeKey($branchId))
                    ->whereBetween('summary_date', [today()->startOfWeek(), today()])
                    ->sum('net_revenue'),
                'revenue_last_week' => (float) RestaurantRevenueSummary::where('restaurant_id', $rid)
                    ->where('summary_type', 'daily')
                    ->where('scope_key', TenantContext::summaryScopeKey($branchId))
                    ->whereBetween('summary_date', [today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()])
                    ->sum('net_revenue'),
            ];
        });
    }

    public function getCashFlowSummary(int $rid, ?int $branchId = null): array
    {
        $scopeKey = TenantContext::branchScopeKey($branchId);
        $key = "dashboard:cash_flow:{$rid}:{$scopeKey}:".today()->toDateString();

        return Cache::remember($key, 300, function () use ($rid, $branchId) {
            $activeRegisters = CashRegister::where('restaurant_id', $rid)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'open')
                ->get();

            $activeRegisterIds = $activeRegisters->pluck('id')->toArray();
            $openingBalanceTotal = (float) $activeRegisters->sum('opening_balance');

            $totals = empty($activeRegisterIds) ? null : CashTransaction::whereIn('cash_register_id', $activeRegisterIds)
                ->selectRaw("SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as total_out")
                ->first();

            $currentCash = $openingBalanceTotal + (float) ($totals?->total_in ?? 0) - (float) ($totals?->total_out ?? 0);

            $sevenDaysAgo = now()->subDays(6)->startOfDay();
            $recentTransactions = CashTransaction::where('restaurant_id', $rid)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('occurred_at', '>=', $sevenDaysAgo)
                ->selectRaw('DATE(occurred_at) as date, type, SUM(amount) as total')
                ->groupBy('date', 'type')
                ->get();

            $chart = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dateStr = $date->toDateString();
                $label = $date->format('d/m');
                $in = (float) $recentTransactions->where('date', $dateStr)->where('type', 'in')->sum('total');
                $out = (float) $recentTransactions->where('date', $dateStr)->where('type', 'out')->sum('total');
                $chart[] = [
                    'date' => $label,
                    'in' => $in,
                    'out' => $out,
                ];
            }

            return [
                'active_register_status' => $activeRegisters->isNotEmpty() ? 'open' : 'closed',
                'current_cash' => $currentCash,
                'seven_days_in' => (float) $recentTransactions->where('type', 'in')->sum('total'),
                'seven_days_out' => (float) $recentTransactions->where('type', 'out')->sum('total'),
                'chart' => $chart,
            ];
        });
    }
}
