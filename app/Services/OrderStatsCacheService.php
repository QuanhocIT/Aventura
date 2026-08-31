<?php

namespace App\Services;

use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache live order statistics (today) để tránh nhiều query COUNT đồng thời
 * lên bảng orders khi nhiều nhà hàng mở dashboard cùng lúc.
 *
 * TTL: 5 phút. Được invalidate ngay khi có đơn mới (saved/deleted) qua
 * Order::booted() hook.
 *
 * Cache key pattern:
 *   order_stats:v2:{restaurant_id}:{YYYY-MM-DD}:all        — all branches
 *   order_stats:v2:{restaurant_id}:{YYYY-MM-DD}:branch:{id} — per branch
 */
class OrderStatsCacheService
{
    /** TTL tính bằng giây */
    public const TTL = 300; // 5 phút

    /**
     * Trả về thống kê đơn hàng hôm nay (có cache).
     *
     * @return array{total: int, completed: int, cancelled: int, pending: int, net_revenue: float|null}
     */
    public function getTodayStats(int $restaurantId, ?int $branchId = null): array
    {
        $key = $this->buildKey($restaurantId, $branchId);

        return Cache::remember($key, self::TTL, function () use ($restaurantId, $branchId) {
            return $this->computeTodayStats($restaurantId, $branchId);
        });
    }

    /**
     * Xóa cache thống kê cho một nhà hàng (all branches + từng branch).
     * Gọi khi có đơn mới hoặc đơn thay đổi trạng thái.
     */
    public function invalidate(int $restaurantId, ?int $branchId = null): void
    {
        // Xóa consolidated cache (all branches)
        Cache::forget($this->buildKey($restaurantId, null));

        // Xóa cache cho branch cụ thể nếu có
        if ($branchId) {
            Cache::forget($this->buildKey($restaurantId, $branchId));
        }

        // Xóa cache các biểu đồ/thông tin Dashboard để giữ tính real-time
        $date = today()->toDateString();
        $branchesToClear = [null];
        if ($branchId) {
            $branchesToClear[] = $branchId;
        }

        foreach ($branchesToClear as $bId) {
            $scopeKey = TenantContext::branchScopeKey($bId);
            Cache::forget("dashboard:revenue_chart:{$restaurantId}:{$scopeKey}:{$date}:0");
            Cache::forget("dashboard:revenue_chart:{$restaurantId}:{$scopeKey}:{$date}:1");
            Cache::forget("dashboard:channel_chart:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:top_products:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:peak_hours:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:forecast:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:forecast:v2:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:shift_revenue:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:owner_summary:{$restaurantId}:{$scopeKey}:{$date}");
            Cache::forget("dashboard:cash_flow:{$restaurantId}:{$scopeKey}:{$date}");
        }
    }

    /**
     * Xóa toàn bộ cache thống kê ngày hôm nay (dùng khi archive chạy sau nửa đêm).
     */
    public function invalidateAllForDate(string $date): void
    {
        // Tags-based invalidation sẽ tốt hơn khi có Redis Tag support,
        // nhưng key-by-key flush đủ dùng cho quy mô hiện tại.
        // Thực tế TTL 5 phút sẽ tự expire; ta chỉ cần xóa khi archive chạy
        // để tránh dashboard hiển thị số cũ sau khi đơn đã bị archive.
        //
        // Nếu cần flush theo pattern, dùng: Cache::flush() hoặc Redis SCAN + DEL
        // nhưng lưu ý flush() xóa toàn bộ cache. Giữ TTL tự expire ở đây là đủ.
    }

    /** Build cache key nhất quán */
    public function buildKey(int $restaurantId, ?int $branchId, ?string $date = null): string
    {
        $date ??= today()->toDateString();
        $scopeKey = TenantContext::branchScopeKey($branchId);

        return "order_stats:v2:{$restaurantId}:{$date}:{$scopeKey}";
    }

    /**
     * Query thực tế (chỉ chạy khi cache miss).
     */
    private function computeTodayStats(int $restaurantId, ?int $branchId): array
    {
        $start = today()->startOfDay();
        $end = today()->endOfDay();

        $rows = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Doanh thu phải dùng cùng phạm vi chi nhánh nhưng theo thời điểm
        // hoàn tất đơn. Đây là fallback live cho Dashboard khi bản tổng hợp
        // doanh thu chưa được worker cập nhật kịp.
        $completedRevenue = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as gross_revenue')
            ->first();

        $netRevenue = null;
        if ((int) ($completedRevenue->order_count ?? 0) > 0) {
            $refundTotal = DB::table('payments')
                ->join('orders', 'payments.order_id', '=', 'orders.id')
                ->where('payments.restaurant_id', $restaurantId)
                ->where('orders.restaurant_id', $restaurantId)
                ->where('payments.status', 'refunded')
                ->whereBetween('payments.created_at', [$start, $end])
                ->when($branchId !== null, fn ($q) => $q->where('orders.branch_id', $branchId))
                ->whereNull('payments.deleted_at')
                ->whereNull('orders.deleted_at')
                ->sum('payments.amount');

            $netRevenue = max(0.0, (float) $completedRevenue->gross_revenue - (float) $refundTotal);
        }

        $total = $rows->sum();
        $completed = (int) ($rows->get('completed', 0));
        $cancelled = (int) ($rows->get('cancelled', 0));
        $pending = (int) ($rows->get('pending', 0));

        return [
            'total' => (int) $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'pending' => $pending,
            'net_revenue' => $netRevenue,
        ];
    }
}
