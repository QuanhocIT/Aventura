<?php

namespace App\Services\Dashboard;

use App\Models\CustomerFeedback;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\RestaurantRevenueSummary;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Support\Tenant\TenantContext;

/**
 * Chuyển nguyên logic từ app/Http/Controllers/DashboardController.php (các
 * helper private cũ: calculateBranchHealthScore, getHealthScore,
 * getHealthScoresForBranches) — di chuyển thuần tuý, không đổi hành vi/cache
 * key/TTL (vẫn dùng chung key "health_score:{rid}:{branchId}" giữa 2 phương
 * thức để giữ tính nhất quán cache như code gốc).
 */
class DashboardHealthService
{
    private function calculateBranchHealthScore(int $rid, ?int $branchId = null): int
    {
        $todaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today())
            ->where('summary_type', 'daily');
        if ($branchId) {
            $todaySummary->where('branch_id', $branchId);
        } else {
            $todaySummary->whereNull('branch_id');
        }
        $todaySummary = $todaySummary->first();

        $yesterdaySummary = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->where('summary_date', today()->subDay())
            ->where('summary_type', 'daily');
        if ($branchId) {
            $yesterdaySummary->where('branch_id', $branchId);
        } else {
            $yesterdaySummary->whereNull('branch_id');
        }
        $yesterdaySummary = $yesterdaySummary->first();

        $ordersStats = Order::where('restaurant_id', $rid)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled')
            ->first();

        $totalToday = (int) ($ordersStats->total ?? 0);
        $completedToday = (int) ($ordersStats->completed ?? 0);
        $cancelledToday = (int) ($ordersStats->cancelled ?? 0);

        $revenueToday = (float) ($todaySummary?->net_revenue ?? 0);
        $revenueYesterday = (float) ($yesterdaySummary?->net_revenue ?? 0);
        $revTrend = $revenueYesterday > 0
            ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1)
            : null;

        $grossProfit = (float) ($todaySummary?->gross_profit ?? 0);
        $profitMargin = $revenueToday > 0
            ? round($grossProfit / $revenueToday * 100, 1)
            : 0.0;

        $completionRate = $totalToday > 0 ? round($completedToday / $totalToday * 100, 1) : 0.0;
        $cancellationRate = $totalToday > 0 ? ($cancelledToday / $totalToday) * 100 : 0;
        $revenueGrowthScore = $revTrend !== null ? min(100, max(0, 50 + $revTrend)) : 50;

        // Punctuality — Fetch assignments and check times in PHP to avoid correlated subquery and driver-specific CONCAT
        $assignmentsToday = ScheduleAssignment::where('restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->when($branchId, function ($q) use ($branchId) {
                $q->whereHas('employee', fn ($emp) => $emp->where('branch_id', $branchId));
            })
            ->get();

        $scheduledToday = $assignmentsToday->count();
        $checkedInOnTime = null;

        if ($scheduledToday > 0) {
            $shiftIds = $assignmentsToday->pluck('shift_id')->unique()->filter()->values();
            $shiftsMap = WorkShift::whereIn('id', $shiftIds)
                ->pluck('start_time', 'id');

            $checkedInOnTime = $assignmentsToday->filter(function ($a) use ($shiftsMap) {
                if (! $a->check_in_at) {
                    return false;
                }
                $startTime = $shiftsMap->get($a->shift_id);
                if (! $startTime) {
                    return false;
                }
                $dateStr = $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString();
                $deadline = Carbon::parse($dateStr.' '.$startTime);

                return Carbon::parse($a->check_in_at)->lte($deadline);
            })->count();
        }

        $punctualityScore = $scheduledToday > 0 && $checkedInOnTime !== null
            ? min(100, ($checkedInOnTime / $scheduledToday) * 100)
            : 75;

        // Inventory — Combine counts into one query
        $inventoryStats = Inventory::join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->where('inventories.restaurant_id', $rid)
            ->when($branchId, fn ($q) => $q->where('inventories.branch_id', $branchId))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN inventories.quantity_on_hand > ingredients.min_stock_level THEN 1 ELSE 0 END) as safe')
            ->first();

        $totalIngredients = (int) ($inventoryStats->total ?? 0);
        $safeIngredients = $totalIngredients > 0 ? (int) ($inventoryStats->safe ?? 0) : null;

        $inventoryScore = $totalIngredients > 0 && $safeIngredients !== null
            ? ($safeIngredients / $totalIngredients) * 100
            : 80;

        // NPS
        $npsData = CustomerFeedback::where('restaurant_id', $rid)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();
        $npsScore = $npsData && $npsData->cnt > 0
            ? min(100, max(0, (($npsData->avg_rating - 1) / 4) * 100))
            : 70;

        return (int) round(
            min(100, max(0,
                ($completionRate * 0.25) +
                (max(0, 100 - $cancellationRate * 4) * 0.15) +
                ($revenueGrowthScore * 0.20) +
                (min(100, $profitMargin * 1.5) * 0.15) +
                ($punctualityScore * 0.10) +
                ($inventoryScore * 0.10) +
                ($npsScore * 0.05)
            ))
        );
    }

    public function getHealthScore(int $rid, ?int $branchId = null): int
    {
        $scopeKey = TenantContext::branchScopeKey($branchId);
        $cached = Cache::get("health_score:{$rid}:{$scopeKey}");
        if ($cached !== null) {
            return (int) $cached;
        }

        $score = $this->calculateBranchHealthScore($rid, $branchId);
        Cache::put("health_score:{$rid}:{$scopeKey}", $score, 300);

        return $score;
    }

    /**
     * Batch pre-fetch health scores for multiple branches to prevent N+1 query bottlenecks.
     */
    public function getHealthScoresForBranches(int $rid, array $branchIds): array
    {
        $scores = [];
        $uncachedIds = [];
        foreach ($branchIds as $id) {
            $scopeKey = TenantContext::branchScopeKey((int) $id);
            $cached = Cache::get("health_score:{$rid}:{$scopeKey}");
            if ($cached !== null) {
                $scores[$id] = (int) $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        if (empty($uncachedIds)) {
            return $scores;
        }

        // 1. Pre-query summaries
        $summariesToday = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->where('summary_date', today())
            ->where('summary_type', 'daily')
            ->get()
            ->keyBy('branch_id');

        $summariesYesterday = RestaurantRevenueSummary::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->where('summary_date', today()->subDay())
            ->where('summary_type', 'daily')
            ->get()
            ->keyBy('branch_id');

        // 2. Pre-query orders today
        $ordersToday = Order::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->selectRaw('branch_id, status, COUNT(*) as cnt')
            ->groupBy('branch_id', 'status')
            ->get()
            ->groupBy('branch_id');

        // 3. Pre-query scheduled work assignments today
        $scheduledToday = ScheduleAssignment::where('schedule_assignments.restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->whereHas('employee', fn ($q) => $q->whereIn('branch_id', $uncachedIds))
            ->join('employees', 'schedule_assignments.employee_id', '=', 'employees.id')
            ->selectRaw('employees.branch_id, COUNT(*) as cnt')
            ->groupBy('employees.branch_id')
            ->pluck('cnt', 'employees.branch_id');

        // Checked in on time — dùng PHP thay vì correlated subquery để tránh N+1 SQL
        // Pre-fetch tất cả shifts liên quan đến assignments hôm nay
        $assignmentsToday = ScheduleAssignment::where('schedule_assignments.restaurant_id', $rid)
            ->where('scheduled_date', today())
            ->whereHas('employee', fn ($q) => $q->whereIn('branch_id', $uncachedIds))
            ->whereNotNull('check_in_at')
            ->join('employees', 'schedule_assignments.employee_id', '=', 'employees.id')
            ->select('schedule_assignments.shift_id', 'schedule_assignments.check_in_at',
                'schedule_assignments.scheduled_date', 'employees.branch_id')
            ->get();

        // Pre-fetch all shifts used in today's assignments (single query)
        $shiftIds = $assignmentsToday->pluck('shift_id')->unique()->filter()->values();
        $shiftsMap = WorkShift::whereIn('id', $shiftIds)
            ->pluck('start_time', 'id');

        // Count on-time check-ins per branch using PHP
        $checkedInOnTime = $assignmentsToday->filter(function ($a) use ($shiftsMap) {
            $startTime = $shiftsMap->get($a->shift_id);
            if (! $startTime) {
                return false;
            }
            $deadline = Carbon::parse($a->scheduled_date->toDateString().' '.$startTime);

            return Carbon::parse($a->check_in_at)->lte($deadline);
        })->groupBy('branch_id')->map->count();

        // 4. Pre-query inventories
        $totalIngredients = Inventory::where('restaurant_id', $rid)
            ->whereIn('branch_id', $uncachedIds)
            ->selectRaw('branch_id, COUNT(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt', 'branch_id');

        $safeIngredients = Inventory::join('ingredients', 'inventories.ingredient_id', '=', 'ingredients.id')
            ->where('inventories.restaurant_id', $rid)
            ->whereIn('inventories.branch_id', $uncachedIds)
            ->whereRaw('inventories.quantity_on_hand > ingredients.min_stock_level')
            ->selectRaw('inventories.branch_id, COUNT(*) as cnt')
            ->groupBy('inventories.branch_id')
            ->pluck('cnt', 'inventories.branch_id');

        // 5. Customer feedback NPS (restaurant-wide, constant across branches)
        $npsData = CustomerFeedback::where('restaurant_id', $rid)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();
        $npsScore = $npsData && $npsData->cnt > 0
            ? min(100, max(0, (($npsData->avg_rating - 1) / 4) * 100))
            : 70;

        foreach ($uncachedIds as $bId) {
            $todaySum = $summariesToday->get($bId);
            $yesterdaySum = $summariesYesterday->get($bId);

            $branchOrders = $ordersToday->get($bId) ?? collect();
            $totalTodayVal = $branchOrders->sum('cnt');
            $completedTodayVal = $branchOrders->where('status', 'completed')->sum('cnt');
            $cancelledTodayVal = $branchOrders->where('status', 'cancelled')->sum('cnt');

            $revenueTodayVal = (float) ($todaySum?->net_revenue ?? 0);
            $revenueYesterdayVal = (float) ($yesterdaySum?->net_revenue ?? 0);
            $revTrendVal = $revenueYesterdayVal > 0
                ? round(($revenueTodayVal - $revenueYesterdayVal) / $revenueYesterdayVal * 100, 1)
                : null;

            $grossProfitVal = (float) ($todaySum?->gross_profit ?? 0);
            $profitMarginVal = $revenueTodayVal > 0
                ? round($grossProfitVal / $revenueTodayVal * 100, 1)
                : 0.0;

            $completionRateVal = $totalTodayVal > 0 ? round($completedTodayVal / $totalTodayVal * 100, 1) : 0.0;
            $cancellationRateVal = $totalTodayVal > 0 ? ($cancelledTodayVal / $totalTodayVal) * 100 : 0;
            $revenueGrowthScoreVal = $revTrendVal !== null ? min(100, max(0, 50 + $revTrendVal)) : 50;

            // Punctuality
            $bScheduled = $scheduledToday->get($bId, 0);
            $bCheckedIn = $checkedInOnTime->get($bId, null);
            $punctualityScoreVal = $bScheduled > 0 && $bCheckedIn !== null
                ? min(100, ($bCheckedIn / $bScheduled) * 100)
                : 75;

            // Inventory
            $bTotalIng = $totalIngredients->get($bId, 0);
            $bSafeIng = $safeIngredients->get($bId, null);
            $inventoryScoreVal = $bTotalIng > 0 && $bSafeIng !== null
                ? ($bSafeIng / $bTotalIng) * 100
                : 80;

            $score = (int) round(
                min(100, max(0,
                    ($completionRateVal * 0.25) +
                    (max(0, 100 - $cancellationRateVal * 4) * 0.15) +
                    ($revenueGrowthScoreVal * 0.20) +
                    (min(100, $profitMarginVal * 1.5) * 0.15) +
                    ($punctualityScoreVal * 0.10) +
                    ($inventoryScoreVal * 0.10) +
                    ($npsScore * 0.05)
                ))
            );

            $scopeKey = TenantContext::branchScopeKey((int) $bId);
            Cache::put("health_score:{$rid}:{$scopeKey}", $score, 300);
            $scores[$bId] = $score;
        }

        return $scores;
    }
}
