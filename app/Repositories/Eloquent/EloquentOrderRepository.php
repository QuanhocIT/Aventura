<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    /**
     * Lấy query danh sách đơn hàng theo bộ lọc.
     */
    public function getOrdersQuery(int $restaurantId, array $filters): Builder
    {
        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['table.area', 'items'])
            ->latest();

        if (!empty($filters['date'])) {
            $query->whereBetween('created_at', [
                $filters['date'] . ' 00:00:00',
                $filters['date'] . ' 23:59:59'
            ]);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Lấy thống kê số lượng đơn hàng theo trạng thái và doanh thu trong ngày.
     */
    public function getSummaryStats(int $restaurantId, string $date): array
    {
        $stats = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [
                $date . ' 00:00:00',
                $date . ' 23:59:59'
            ])
            ->selectRaw('status, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupBy('status')
            ->get();

        $total = $stats->sum('count');

        return [
            'total'     => (int) $total,
            'pending'   => (int) ($stats->firstWhere('status', 'pending')?->count ?? 0),
            'preparing' => (int) ($stats->firstWhere('status', 'preparing')?->count ?? 0),
            'completed' => (int) ($stats->firstWhere('status', 'completed')?->count ?? 0),
            'cancelled' => (int) ($stats->firstWhere('status', 'cancelled')?->count ?? 0),
            'revenue'   => (float) ($stats->firstWhere('status', 'completed')?->revenue ?? 0),
        ];
    }

    /**
     * Tìm đơn hàng theo ID kèm theo restaurant_id (nếu có).
     */
    public function findById(int $id, ?int $restaurantId = null): Order
    {
        $query = Order::query();

        if ($restaurantId !== null) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query->findOrFail($id);
    }

    /**
     * Tạo mới một đơn hàng.
     */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /**
     * Cập nhật thông tin đơn hàng.
     */
    public function update(Order $order, array $data): bool
    {
        return $order->update($data);
    }
}
