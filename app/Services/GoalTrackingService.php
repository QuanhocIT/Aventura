<?php

namespace App\Services;

use App\Models\BusinessGoal;
use Illuminate\Support\Facades\DB;

class GoalTrackingService
{
    public function syncProgress(BusinessGoal $goal): void
    {
        $current = $this->calculateCurrentValue($goal);
        $target = (float) $goal->target_value;
        $percent = $target > 0 ? (int) min(100, round(($current / $target) * 100)) : 0;

        $goal->update([
            'current_value' => $current,
            'progress_percent' => $percent,
        ]);

        if ($percent >= 100 && $goal->status === 'active') {
            $goal->update(['status' => 'completed']);
        }

        if ($goal->isExpired() && $percent < 100) {
            $goal->update(['status' => 'failed']);
        }

        $this->checkMilestones($goal, $percent);
    }

    public function syncAllActive(int $restaurantId): int
    {
        $goals = BusinessGoal::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get();

        foreach ($goals as $goal) {
            $this->syncProgress($goal);
        }

        return $goals->count();
    }

    public function getHistory(int $restaurantId): array
    {
        return BusinessGoal::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['completed', 'failed'])
            ->latest('end_date')
            ->take(20)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'metric' => $g->metric,
                'period' => $g->period,
                'target' => (float) $g->target_value,
                'achieved' => (float) $g->current_value,
                'percent' => $g->progress_percent,
                'status' => $g->status,
                'end_date' => $g->end_date->format('d/m/Y'),
            ])
            ->all();
    }

    private function calculateCurrentValue(BusinessGoal $goal): float
    {
        $restaurantId = $goal->restaurant_id;
        $from = $goal->start_date;
        $to = min($goal->end_date, now());

        return match ($goal->metric) {
            'revenue' => (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('total_amount') +
                (float) DB::table('orders_archive')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$from, $to])
                ->sum('total_amount'),

            'orders' => (float) DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereBetween('completed_at', [$from, $to])
                ->count() +
                (float) DB::table('orders_archive')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            'customers' => (float) DB::table('customers')
                ->where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$from, $to])
                ->count(),

            'rating' => (float) DB::table('customer_feedback')
                ->where('restaurant_id', $restaurantId)
                ->whereBetween('created_at', [$from, $to])
                ->avg('rating') ?? 0,

            'cost_saving' => $this->calculateCostSaving($restaurantId, $from, $to),

            default => (float) $goal->current_value,
        };
    }

    private function calculateCostSaving(int $restaurantId, $from, $to): float
    {
        $wasteCost = (float) DB::table('inventory_transactions')
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('total_cost');

        $prevFrom = $from->copy()->subDays($from->diffInDays($to));
        $prevWaste = (float) DB::table('inventory_transactions')
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$prevFrom, $from])
            ->sum('total_cost');

        return max(0, $prevWaste - $wasteCost);
    }

    private function checkMilestones(BusinessGoal $goal, int $percent): void
    {
        foreach ($goal->milestones as $milestone) {
            if (! $milestone->reached && $percent >= $milestone->threshold_percent) {
                $milestone->update([
                    'reached' => true,
                    'reached_at' => now(),
                ]);
            }
        }
    }
}

