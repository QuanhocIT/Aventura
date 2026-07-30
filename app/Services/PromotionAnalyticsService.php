<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromotionAnalyticsSnapshot;
use Illuminate\Support\Collection;

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

        $customerIds = (clone $orders)->whereNotNull('customer_id')->distinct()->pluck('customer_id');
        $uniqueCustomers = $customerIds->count();

        [$newCustomers, $repeatRate] = $this->calculateCustomerAcquisition($restaurantId, $date, $customerIds);

        PromotionAnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId, 'promotion_id' => null, 'snapshot_date' => $date],
            [
                'total_uses' => $totalUses,
                'total_discount_given' => $totalDiscount,
                'total_revenue_with_promo' => $totalRevenue,
                'unique_customers' => $uniqueCustomers,
                'new_customers_acquired' => $newCustomers,
                'repeat_rate' => $repeatRate,
            ]
        );
    }

    /**
     * "Khách mới": khách có đơn hoàn thành ĐẦU TIÊN trong toàn bộ lịch sử rơi đúng vào
     * ngày này (đơn có khuyến mãi hôm nay chính là lần mua đầu tiên của họ).
     * "repeat_rate": phần trăm khách dùng khuyến mãi hôm nay đã từng mua hàng trước đó.
     *
     * @return array{0: int, 1: float}
     */
    private function calculateCustomerAcquisition(int $restaurantId, string $date, Collection $customerIds): array
    {
        if ($customerIds->isEmpty()) {
            return [0, 0.0];
        }

        $firstOrderDates = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, MIN(DATE(created_at)) as first_order_date')
            ->groupBy('customer_id')
            ->pluck('first_order_date', 'customer_id');

        $newCustomers = $customerIds->filter(fn ($id) => ($firstOrderDates[$id] ?? null) === $date)->count();

        $total = $customerIds->count();
        $repeatRate = $total > 0 ? round((($total - $newCustomers) / $total) * 100, 1) : 0.0;

        return [$newCustomers, $repeatRate];
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
        $totalNewCustomers = $snapshots->sum('new_customers_acquired');
        // Trung bình có trọng số theo unique_customers từng ngày, không phải trung bình cộng đơn thuần
        // giữa các ngày (ngày 100 khách và ngày 2 khách không nên có trọng số bằng nhau).
        $totalUniqueCustomers = $snapshots->sum('unique_customers');
        $avgRepeatRate = $totalUniqueCustomers > 0
            ? round((($totalUniqueCustomers - $totalNewCustomers) / $totalUniqueCustomers) * 100, 1)
            : 0.0;

        return [
            'total_discount' => $totalDiscount,
            'total_revenue' => $totalRevenue,
            'total_uses' => $totalUses,
            'roi_percent' => $roi,
            'new_customers_acquired' => $totalNewCustomers,
            'repeat_rate' => $avgRepeatRate,
            'daily' => $snapshots->map(fn ($s) => [
                'date' => $s->snapshot_date->format('d/m'),
                'discount' => (float) $s->total_discount_given,
                'revenue' => (float) $s->total_revenue_with_promo,
                'uses' => $s->total_uses,
                'new_customers' => $s->new_customers_acquired,
                'repeat_rate' => (float) $s->repeat_rate,
            ]),
        ];
    }
}
