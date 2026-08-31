<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromotionAnalyticsSnapshot;
use App\Models\PromotionUsage;
use Illuminate\Support\Collection;

class PromotionAnalyticsService
{
    public function calculateDailySnapshot(int $restaurantId, string $date, ?int $branchId = null): void
    {
        $orders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('created_at', $date)
            ->where('discount_amount', '>', 0)
            ->where('status', 'completed');

        $totalUses = $orders->count();
        $totalDiscount = (float) $orders->sum('discount_amount');
        $totalRevenue = (float) $orders->sum('total_amount');

        $customerIds = (clone $orders)->whereNotNull('customer_id')->distinct()->pluck('customer_id');
        $uniqueCustomers = $customerIds->count();

        [$newCustomers, $repeatRate] = $this->calculateCustomerAcquisition($restaurantId, $date, $customerIds, $branchId);

        PromotionAnalyticsSnapshot::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId, 'promotion_id' => null, 'snapshot_date' => $date, 'branch_id' => $branchId],
            [
                'branch_id' => $branchId,
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
    private function calculateCustomerAcquisition(int $restaurantId, string $date, Collection $customerIds, ?int $branchId = null): array
    {
        if ($customerIds->isEmpty()) {
            return [0, 0.0];
        }

        $firstOrderDates = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
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

    public function getDashboardMetrics(int $restaurantId, string $startDate, string $endDate, ?int $branchId = null): array
    {
        $snapshots = PromotionAnalyticsSnapshot::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'))
            ->whereNull('promotion_id')
            ->whereBetween('snapshot_date', [$startDate, $endDate])
            ->orderBy('snapshot_date')
            ->get();

        // Nếu chưa có snapshot nào (do chưa chạy cron job), tính toán trực tiếp từ bảng orders & promotion_usages
        if ($snapshots->isEmpty()) {
            $this->generateSnapshotsForPeriod($restaurantId, $startDate, $endDate, $branchId);

            $snapshots = PromotionAnalyticsSnapshot::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'))
                ->whereNull('promotion_id')
                ->whereBetween('snapshot_date', [$startDate, $endDate])
                ->orderBy('snapshot_date')
                ->get();
        }

        $totalDiscount = (float) $snapshots->sum('total_discount_given');
        $totalRevenue = (float) $snapshots->sum('total_revenue_with_promo');
        $totalUses = (int) $snapshots->sum('total_uses');
        $roi = $totalDiscount > 0 ? round(($totalRevenue / $totalDiscount) * 100, 1) : 0;
        $totalNewCustomers = (int) $snapshots->sum('new_customers_acquired');
        $totalUniqueCustomers = (int) $snapshots->sum('unique_customers');
        $avgRepeatRate = $totalUniqueCustomers > 0
            ? round((($totalUniqueCustomers - $totalNewCustomers) / $totalUniqueCustomers) * 100, 1)
            : 0.0;

        // Tính AOV Đơn có KM vs Đơn không KM
        $promoOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('status', 'completed')
            ->where('discount_amount', '>', 0);

        $regularOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('status', 'completed')
            ->where('discount_amount', '<=', 0);

        $promoOrderCount = (clone $promoOrders)->count();
        $promoOrderSum = (float) (clone $promoOrders)->sum('total_amount');
        $aovWithPromo = $promoOrderCount > 0 ? round($promoOrderSum / $promoOrderCount, 0) : 0.0;

        $regularOrderCount = (clone $regularOrders)->count();
        $regularOrderSum = (float) (clone $regularOrders)->sum('total_amount');
        $aovWithoutPromo = $regularOrderCount > 0 ? round($regularOrderSum / $regularOrderCount, 0) : 0.0;

        $basketLift = $aovWithoutPromo > 0 && $aovWithPromo > 0
            ? round((($aovWithPromo - $aovWithoutPromo) / $aovWithoutPromo) * 100, 1)
            : 0.0;

        $perPromotion = $this->getPerPromotionBreakdown($restaurantId, $startDate, $endDate, $branchId);

        $insights = $this->generateInsights($totalDiscount, $totalRevenue, $roi, $basketLift, $perPromotion);

        return [
            'total_discount' => $totalDiscount,
            'total_revenue' => $totalRevenue,
            'total_uses' => $totalUses,
            'roi_percent' => $roi,
            'new_customers_acquired' => $totalNewCustomers,
            'repeat_rate' => $avgRepeatRate,
            'aov_with_promo' => $aovWithPromo,
            'aov_without_promo' => $aovWithoutPromo,
            'basket_lift_percent' => $basketLift,
            'insights' => $insights,
            'daily' => $snapshots->map(fn ($s) => [
                'date' => $s->snapshot_date->format('d/m'),
                'discount' => (float) $s->total_discount_given,
                'revenue' => (float) $s->total_revenue_with_promo,
                'uses' => (int) $s->total_uses,
                'new_customers' => (int) $s->new_customers_acquired,
                'repeat_rate' => (float) $s->repeat_rate,
            ]),
            'per_promotion' => $perPromotion,
        ];
    }

    public function generateSnapshotsForPeriod(int $restaurantId, string $startDate, string $endDate, ?int $branchId = null): void
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($start->lte($end)) {
            $this->calculateDailySnapshot($restaurantId, $start->toDateString(), $branchId);
            $start->addDay();
        }
    }

    private function generateInsights(float $totalDiscount, float $totalRevenue, float $roi, float $basketLift, array $perPromotion): array
    {
        $insights = [];

        if ($roi >= 300) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Chỉ số ROI rất ấn tượng',
                'message' => "Mỗi 1đ chiết khấu mang lại {$roi}% doanh thu tác động. Chương trình khuyến mãi đang mang lại lợi nhuận cao.",
            ];
        } elseif ($roi > 0 && $roi < 150) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Cần tối ưu chi phí khuyến mãi',
                'message' => "Chỉ số ROI hiện tại ({$roi}%) tương đối thấp. Bạn nên xem xét điều chỉnh giá trị chiết khấu hoặc đặt ngưỡng đơn tối thiểu cao hơn.",
            ];
        }

        if ($basketLift > 10) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Tăng trưởng giá trị giỏ hàng (Basket Lift)',
                'message' => "Khách hàng sử dụng khuyến mãi mua đơn hàng có giá trị cao hơn {$basketLift}% so với đơn thường.",
            ];
        }

        if (! empty($perPromotion)) {
            $topPromo = $perPromotion[0];
            $insights[] = [
                'type' => 'info',
                'title' => "Chương trình hiệu quả nhất: {$topPromo['name']}",
                'message' => "Đã được sử dụng {$topPromo['uses']} lần, tạo ra ".number_format($topPromo['revenue_influenced'], 0, ',', '.').' ₫ doanh thu.',
            ];
        }

        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Theo dõi hiệu quả chiến dịch',
                'message' => 'Tích cực tạo thêm mã ưu đãi và trigger tự động để thu hút khách quay lại.',
            ];
        }

        return $insights;
    }

    /**
     * Hiệu quả của TỪNG chương trình khuyến mãi.
     *
     * Các chỉ số tổng ở trên gộp mọi loại giảm giá trên đơn (điểm loyalty, ưu đãi
     * VIP, giảm tay...) vì chúng đọc orders.discount_amount, và snapshot luôn ghi
     * promotion_id = null. Bảng promotion_usages mới cho phép quy đúng từng đồng
     * chiết khấu về đúng mã đã tạo ra nó.
     *
     * @return list<array<string, mixed>>
     */
    private function getPerPromotionBreakdown(int $restaurantId, string $startDate, string $endDate, ?int $branchId = null): array
    {
        return PromotionUsage::withoutGlobalScopes()
            ->where('promotion_usages.restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('promotion_usages.branch_id', $branchId))
            ->whereBetween('promotion_usages.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->join('promotions', 'promotions.id', '=', 'promotion_usages.promotion_id')
            ->groupBy('promotions.id', 'promotions.name', 'promotions.code', 'promotions.type', 'promotions.value')
            ->selectRaw('promotions.id as promotion_id')
            ->selectRaw('promotions.name as promotion_name')
            ->selectRaw('promotions.code as promotion_code')
            ->selectRaw('promotions.type as promotion_type')
            ->selectRaw('promotions.value as promotion_value')
            ->selectRaw('COUNT(*) as uses')
            ->selectRaw('COUNT(DISTINCT promotion_usages.customer_id) as unique_customers')
            ->selectRaw('SUM(promotion_usages.discount_amount) as discount_given')
            ->selectRaw('SUM(promotion_usages.order_subtotal) as revenue_influenced')
            ->selectRaw('SUM(promotion_usages.used_bypass) as bypass_count')
            ->orderByDesc('discount_given')
            ->get()
            ->map(function ($row): array {
                $discount = (float) $row->discount_given;
                $revenue = (float) $row->revenue_influenced;

                return [
                    'promotion_id' => (int) $row->promotion_id,
                    'name' => $row->promotion_name,
                    'code' => $row->promotion_code,
                    'type' => $row->promotion_type,
                    'value' => (float) $row->promotion_value,
                    'uses' => (int) $row->uses,
                    'unique_customers' => (int) $row->unique_customers,
                    'discount_given' => $discount,
                    'revenue_influenced' => $revenue,
                    'avg_order_value' => $row->uses > 0 ? round($revenue / (int) $row->uses, 0) : 0.0,
                    // Bao nhiêu đồng doanh thu đổi được trên mỗi đồng chiết khấu.
                    'roi_percent' => $discount > 0 ? round(($revenue / $discount) * 100, 1) : 0.0,
                    'bypass_count' => (int) $row->bypass_count,
                ];
            })
            ->all();
    }
}
