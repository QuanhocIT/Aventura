<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

interface OrderRepositoryInterface
{
    /**
     * Lấy query danh sách đơn hàng theo bộ lọc.
     */
    public function getOrdersQuery(int $restaurantId, array $filters): Builder;

    /**
     * Lấy thống kê số lượng đơn hàng theo trạng thái và doanh thu trong ngày.
     */
    public function getSummaryStats(int $restaurantId, string $date): array;

    /**
     * Tìm đơn hàng theo ID kèm theo restaurant_id (nếu có).
     */
    public function findById(int $id, ?int $restaurantId = null): Order;

    /**
     * Tạo mới một đơn hàng.
     */
    public function create(array $data): Order;

    /**
     * Cập nhật thông tin đơn hàng.
     */
    public function update(Order $order, array $data): bool;
}
