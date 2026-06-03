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
            $query->whereDate('created_at', $filters['date']);
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
        $baseQuery = Order::where('restaurant_id', $restaurantId)->whereDate('created_at', $date);

        return [
            'total'     => (clone $baseQuery)->count(),
            'pending'   => (clone $baseQuery)->where('status', 'pending')->count(),
            'preparing' => (clone $baseQuery)->where('status', 'preparing')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'revenue'   => (float) (clone $baseQuery)->where('status', 'completed')->sum('total_amount'),
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
