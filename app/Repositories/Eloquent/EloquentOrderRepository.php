<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\OrderRepositoryInterface;
use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    /**
     * Lấy query danh sách đơn hàng theo bộ lọc.
     */
    public function getOrdersQuery(int $restaurantId, array $filters): Builder
    {
        $query = Order::where('restaurant_id', $restaurantId)
            ->with(['table.area', 'items.product', 'items.preparedBy:id,name', 'items.servedBy:id,name', 'deliveryDetail', 'branch:id,name', 'refundedBy:id,name'])
            ->latest();

        if (! empty($filters['date'])) {
            $dateStart = $filters['date'].' 00:00:00';
            $dateEnd = $filters['date'].' 23:59:59';
            $query->where(function (Builder $q) use ($dateStart, $dateEnd) {
                $q->whereBetween('created_at', [$dateStart, $dateEnd])
                    ->orWhereBetween('completed_at', [$dateStart, $dateEnd])
                    // Đơn cũ có thể nhận thêm món trong ngày (đồng bộ offline
                    // hoặc gọi thêm qua QR), nên ngày hoạt động phải được tính.
                    ->orWhereBetween('updated_at', [$dateStart, $dateEnd]);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'refunded') {
                $query->whereIn('payment_status', ['partial_refund', 'refunded']);
            } elseif ($filters['status'] === 'waiting_preparing') {
                // Món đã gửi xuống bếp nhưng chưa được bếp bấm "Bắt đầu chế biến".
                $query->where('status', '!=', 'cancelled')
                    ->currentTableOrder()
                    ->whereHas('items', function (Builder $iq) {
                        $iq->where('status', '!=', 'cancelled')
                            ->whereNull('served_at')
                            ->whereNull('prepared_at')
                            ->whereNull('started_preparing_at');
                    })
                    ->whereDoesntHave('items', function (Builder $iq) {
                        $iq->where('status', '!=', 'cancelled')
                            ->whereNull('served_at')
                            ->where(function (Builder $itemQuery) {
                                $itemQuery->whereNotNull('started_preparing_at')
                                    ->orWhereNotNull('prepared_at');
                            });
                    });
            } elseif ($filters['status'] === 'preparing') {
                // Chỉ hiện đơn hiện hành có món đã được bếp bấm bắt đầu chế biến.
                // Payment có thể đã hoàn tất trước vòng đời bếp/phục vụ.
                $query->where('status', '!=', 'cancelled')
                    ->currentTableOrder()
                    ->whereHas('items', function (Builder $iq) {
                        $iq->where('status', '!=', 'cancelled')
                            ->whereNull('served_at')
                            ->whereNull('prepared_at')
                            ->whereNotNull('started_preparing_at');
                    });
            } elseif ($filters['status'] === 'ready') {
                // Đơn chờ phục vụ: mọi món còn lại đã làm xong, nhưng chưa
                // được phục vụ. Đây là một trạng thái vận hành độc lập với
                // orders.status/payment_status.
                $query->where('status', '!=', 'cancelled')
                    ->currentTableOrder()
                    ->whereHas('items', function (Builder $iq) {
                        $iq->where('status', '!=', 'cancelled')
                            ->whereNull('served_at');
                    })
                    ->whereDoesntHave('items', function (Builder $iq) {
                        $iq->where('status', '!=', 'cancelled')
                            ->whereNull('served_at')
                            ->whereNull('prepared_at');
                    });
            } elseif ($filters['status'] === 'pending') {
                $query->where('status', 'pending')
                    ->whereDoesntHave('items', function (Builder $iq) {
                        $iq->where(function (Builder $itemQuery) {
                            $itemQuery->where('status', 'preparing')
                                ->orWhereNotNull('started_preparing_at')
                                ->orWhereNotNull('prepared_at');
                        });
                    });
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // TRƯỚC ĐÂY KHÔNG LỌC THEO CHI NHÁNH: owner chuyển chi nhánh đang xem
        // (BranchSwitchController) nhưng trang đơn hàng vẫn hiện đơn của MỌI
        // chi nhánh — không đồng bộ với Dashboard/CashFlow đã lọc đúng.
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped() || $tenantContext->isUnassigned()) {
            $tenantContext->applyBranchScope($query);
        } elseif (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query;
    }

    /**
     * Lấy thống kê số lượng đơn hàng theo trạng thái và doanh thu trong ngày.
     */
    public function getSummaryStats(int $restaurantId, string $date, bool $kitchenOnly = false, ?int $branchId = null): array
    {
        $baseQuery = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [
                $date.' 00:00:00',
                $date.' 23:59:59',
            ]);

        $refundedQuery = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('refunded_at', [
                $date.' 00:00:00',
                $date.' 23:59:59',
            ])
            ->whereIn('payment_status', ['partial_refund', 'refunded']);

        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped() || $tenantContext->isUnassigned()) {
            $tenantContext->applyBranchScope($baseQuery);
            $tenantContext->applyBranchScope($refundedQuery);
        } elseif ($branchId) {
            $baseQuery->where('branch_id', $branchId);
            $refundedQuery->where('branch_id', $branchId);
        }

        if ($kitchenOnly) {
            $baseQuery->whereHas('items', function ($q) {
                $q->whereNotNull('served_at');
            });
            $refundedQuery->whereHas('items', function ($q) {
                $q->whereNotNull('served_at');
            });
        }

        $orders = $baseQuery->with('items:id,order_id,status,started_preparing_at,prepared_at,served_at')->get();

        $total = $orders->count();
        $cancelledCount = $orders->where('status', 'cancelled')->count();
        // Revenue still follows payment/order status, while the operational
        // counters follow the item lifecycle (kitchen -> waiter).
        $revenue = $orders->where('status', 'completed')->sum('total_amount');

        $preparingCount = 0;
        $pendingCount = 0;
        $completedCount = 0;

        foreach ($orders->reject(fn ($o) => $o->status === 'cancelled') as $o) {
            $items = $o->items->reject(fn ($i) => $i->status === 'cancelled');
            $allServed = $items->isEmpty() || $items->every(fn ($i) => $i->served_at !== null);
            $hasPreparingItems = $items->contains(fn ($i) => $i->started_preparing_at !== null || $i->prepared_at !== null
            );

            if ($allServed) {
                $completedCount++;
            } elseif ($hasPreparingItems) {
                $preparingCount++;
            } else {
                $pendingCount++;
            }
        }

        return [
            'total' => (int) $total,
            'pending' => (int) $pendingCount,
            'preparing' => (int) $preparingCount,
            'completed' => (int) $completedCount,
            'cancelled' => (int) $cancelledCount,
            'revenue' => (float) $revenue,
            'refunded' => (int) $refundedQuery->count(),
            'refunded_amount' => (float) $refundedQuery->sum('refund_amount'),
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
