<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromotionAnalyticsSnapshot;
use Illuminate\Support\Facades\DB;

class PromotionAnalyticsService
{
    public function calculateDailySnapshot(int $restaurantId, string $date): void
    {
        $orders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $date)
            ->where('discount_amount', '>', 0)
            ->where('status', 'completed');

        $totalUses = $orders->count();
        $totalDiscount = (float) $orders->sum('discount_amount');
        $totalRevenue = (float) $orders->sum('total_amount');
        $uniqueCustomers = $orders->distinct('customer_id')->count('customer_id');

        PromotionAnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId, 'promotion_id' => null, 'snapshot_date' => $date],
            [
                'total_uses' => $totalUses,
                'total_discount_given' => $totalDiscount,
                'total_revenue_with_promo' => $totalRevenue,
                'unique_customers' => $uniqueCustomers,
                'new_customers_acquired' => 0,
                'repeat_rate' => 0,
            ]
        );
    }

    public function getDashboardMetrics(int $restaurantId, string $startDate, string $endDate): array
    {
        $snapshots = PromotionAnalyticsSnapshot::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereNull('promotion_id')
            ->whereBetween('snapshot_date', [$startDate, $endDate])
            ->orderBy('snapshot_date')
            ->get();

        $totalDiscount = $snapshots->sum('total_discount_given');
        $totalRevenue = $snapshots->sum('total_revenue_with_promo');
        $totalUses = $snapshots->sum('total_uses');
        $roi = $totalDiscount > 0 ? round(($totalRevenue / $totalDiscount) * 100, 1) : 0;

        return [
            'total_discount' => $totalDiscount,
            'total_revenue' => $totalRevenue,
            'total_uses' => $totalUses,
            'roi_percent' => $roi,
            'daily' => $snapshots->map(fn ($s) => [
                'date' => $s->snapshot_date->format('d/m'),
                'discount' => (float) $s->total_discount_given,
                'revenue' => (float) $s->total_revenue_with_promo,
                'uses' => $s->total_uses,
            ]),
        ];
    }
}
