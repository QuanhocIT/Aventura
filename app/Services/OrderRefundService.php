<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderRefundService
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Thực hiện hoàn tiền sau khi đã được chủ doanh nghiệp phê duyệt.
     * Luôn kiểm tra lại số dư hoàn tiền trong transaction để không hoàn trùng.
     */
    public function process(Order $order, array $data, User $processor): void
    {
        DB::transaction(function () use ($order, $data, $processor): void {
            $lockedOrder = Order::whereKey($order->id)
                ->where('restaurant_id', $order->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldPaymentStatus = $lockedOrder->payment_status;
            $oldRefundAmount = (float) ($lockedOrder->refund_amount ?? 0);
            $refundAmount = (float) $data['refund_amount'];
            $remainingRefundable = max(0.0, (float) $lockedOrder->total_amount - $oldRefundAmount);

            if (! in_array($oldPaymentStatus, ['paid', 'partial_refund'], true)) {
                throw new \RuntimeException('Đơn hàng không còn đủ điều kiện để hoàn tiền.');
            }

            if ($refundAmount < 1000 || $refundAmount > $remainingRefundable + 0.01) {
                throw new \RuntimeException('Số tiền hoàn không hợp lệ hoặc vượt quá số dư còn được hoàn.');
            }

            if ($data['refund_type'] === 'full'
                && abs($refundAmount - $remainingRefundable) > 0.01) {
                throw new \RuntimeException('Hoàn toàn phần phải bằng đúng số tiền còn được hoàn.');
            }

            $newPaymentStatus = $data['refund_type'] === 'full' ? 'refunded' : 'partial_refund';

            $lockedOrder->update([
                'payment_status' => $newPaymentStatus,
                'refund_amount' => $oldRefundAmount + $refundAmount,
                'refund_reason' => trim(($lockedOrder->refund_reason ? $lockedOrder->refund_reason."\n" : '').$data['reason']),
                'refunded_at' => now(),
                'refunded_by' => $processor->id,
            ]);

            Payment::create([
                'restaurant_id' => $lockedOrder->restaurant_id,
                'branch_id' => $lockedOrder->branch_id,
                'order_id' => $lockedOrder->id,
                'processed_by' => $processor->id,
                'payment_method' => $lockedOrder->payments()->where('status', 'paid')->value('payment_method') ?? 'mixed',
                'status' => 'refunded',
                'amount' => $refundAmount,
                'cash_received' => 0,
                'change_amount' => 0,
                'paid_at' => now(),
            ]);

            if ($data['refund_type'] === 'full' && ! $lockedOrder->inventory_restored_at) {
                $this->inventoryService->restoreStockForOrder($lockedOrder);
                $lockedOrder->update([
                    'status' => 'cancelled',
                    'inventory_restored_at' => now(),
                ]);
            }

            AuditLog::log(
                'refund_processed',
                'updated',
                $lockedOrder,
                ['payment_status' => $oldPaymentStatus],
                [
                    'payment_status' => $newPaymentStatus,
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $data['reason'],
                    'processed_by_user_id' => $processor->id,
                    'processed_by_user_name' => $processor->name,
                ],
            );
        });
    }
}
