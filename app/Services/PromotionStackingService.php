<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionStackingService
{
    public function validateConditions(Promotion $promotion, Order $order): bool
    {
        $conditions = $promotion->conditions;

        if (empty($conditions)) {
            return true;
        }

        if (! empty($conditions['day_of_week'])) {
            $today = now()->dayOfWeekIso;
            if (! in_array($today, $conditions['day_of_week'])) {
                return false;
            }
        }

        if (! empty($conditions['time_range'])) {
            $now = now()->format('H:i');
            $start = $conditions['time_range']['start'] ?? '00:00';
            $end = $conditions['time_range']['end'] ?? '23:59';
            $isInRange = $start <= $end
                ? $now >= $start && $now <= $end
                : $now >= $start || $now <= $end;
            if (! $isInRange) {
                return false;
            }
        }

        if (! empty($conditions['min_items'])) {
            $itemCount = $order->relationLoaded('items')
                ? $order->items->where('status', '!=', 'cancelled')->count()
                : $order->items()->where('status', '!=', 'cancelled')->count();
            if ($itemCount < $conditions['min_items']) {
                return false;
            }
        }

        if (! empty($conditions['first_order_only'])) {
            if (! $order->customer_id) {
                return false;
            }

            $previousOrders = Order::where('customer_id', $order->customer_id)
                ->where('id', '!=', $order->id)
                ->where('status', 'completed')
                ->count();
            if ($previousOrders > 0) {
                return false;
            }
        }

        if (! empty($conditions['max_uses_per_customer']) && $order->customer_id) {
            $usageCount = Order::where('customer_id', $order->customer_id)
                ->where('note', 'like', "%{$promotion->code}%")
                ->where('status', '!=', 'cancelled')
                ->count();
            if ($usageCount >= $conditions['max_uses_per_customer']) {
                return false;
            }
        }

        return true;
    }

    public function evaluateApplicablePromotions(Order $order, array $promotionCodes): Collection
    {
        $restaurantId = $order->restaurant_id;

        $promotions = Promotion::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('code', $promotionCodes)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get();

        return $promotions
            ->filter(fn (Promotion $p) => ! $p->isBudgetExhausted())
            ->filter(fn (Promotion $p) => $this->validateConditions($p, $order))
            ->sortByDesc('stacking_priority');
    }

    public function calculateStackedDiscount(Order $order, Collection $promotions): float
    {
        $subtotal = (float) $order->total_amount;
        $totalDiscount = 0;
        $usedGroups = [];

        foreach ($promotions as $promotion) {
            if ($promotion->stacking_group && in_array($promotion->stacking_group, $usedGroups)) {
                continue;
            }

            if ($subtotal <= 0) {
                break;
            }

            if ($promotion->min_order_amount && $subtotal < (float) $promotion->min_order_amount) {
                continue;
            }

            $discount = $promotion->type === 'percent'
                ? $subtotal * ((float) $promotion->value / 100)
                : (float) $promotion->value;

            if ($promotion->max_discount_amount) {
                $discount = min($discount, (float) $promotion->max_discount_amount);
            }

            if ($promotion->budget_cap !== null) {
                $remaining = $promotion->remainingBudget();
                $discount = min($discount, $remaining);
            }

            $totalDiscount += $discount;

            if (! $promotion->is_stackable) {
                break;
            }

            if ($promotion->stacking_group) {
                $usedGroups[] = $promotion->stacking_group;
            }

            $subtotal -= $discount;
        }

        return round($totalDiscount, 2);
    }
}
