<?php

namespace App\Services;

use App\Events\Kitchen\KitchenItemCancelled;
use App\Events\Kitchen\KitchenUpdated;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderItemCancellationService
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Cancel one complete order item after the caller has authenticated the
     * operation (cashier confirmation or manager approval).
     *
     * @return array{product_name:string, table_name:string, quantity:float, order_id:int, refund_amount:float, was_started:bool}
     */
    public function cancel(OrderItem $item, User $user, string $reason): array
    {
        $result = DB::transaction(function () use ($item, $user, $reason): array {
            $lockedItem = OrderItem::whereKey($item->id)
                ->where('restaurant_id', $user->restaurant_id)
                ->with(['order.table', 'product.recipes.unit', 'product.recipes.ingredient.unit'])
                ->lockForUpdate()
                ->firstOrFail();
            $order = Order::whereKey($lockedItem->order_id)
                ->where('restaurant_id', $user->restaurant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status === 'cancelled') {
                throw new \RuntimeException('Món này đã được hủy trước đó.');
            }
            if ($lockedItem->status === 'served' || $lockedItem->served_at !== null) {
                throw new \RuntimeException('Món đã phục vụ, không thể hủy trên POS.');
            }

            $normalizedReason = trim($reason);
            if ($normalizedReason === '') {
                throw new \RuntimeException('Bắt buộc phải nhập lý do hủy món.');
            }

            $wasStarted = $lockedItem->started_preparing_at !== null
                || $lockedItem->prepared_at !== null
                || $lockedItem->status === 'preparing';
            $refundAmount = 0.0;
            $oldPaymentStatus = $order->payment_status;

            // Keep the original order total as the sales ledger total for a
            // paid bill; refund_amount is the authoritative offset.
            if (in_array($order->payment_status, ['paid', 'partial_refund'], true)) {
                $alreadyRefunded = (float) ($order->refund_amount ?? 0);
                $remaining = max(0.0, (float) $order->total_amount - $alreadyRefunded);
                $refundAmount = min((float) $lockedItem->line_total, $remaining);

                if ($refundAmount > 0) {
                    $newRefundTotal = $alreadyRefunded + $refundAmount;
                    $order->update([
                        'payment_status' => $newRefundTotal + 0.01 >= (float) $order->total_amount
                            ? 'refunded'
                            : 'partial_refund',
                        'refund_amount' => $newRefundTotal,
                        'refund_reason' => trim(($order->refund_reason ? $order->refund_reason."\n" : '')
                            ."Hủy món {$lockedItem->product?->name}: {$normalizedReason}"),
                        'refunded_at' => now(),
                        'refunded_by' => $user->id,
                    ]);

                    Payment::create([
                        'restaurant_id' => $order->restaurant_id,
                        'branch_id' => $order->branch_id,
                        'order_id' => $order->id,
                        'processed_by' => $user->id,
                        'payment_method' => $order->payments()->where('status', 'paid')->value('payment_method') ?? 'mixed',
                        'status' => 'refunded',
                        'amount' => $refundAmount,
                        'cash_received' => 0,
                        'change_amount' => 0,
                        'paid_at' => now(),
                    ]);
                }
            }

            $lockedItem->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $normalizedReason,
                'notes' => trim(($lockedItem->notes ? $lockedItem->notes.' ' : '')
                    ."[Hủy món: {$normalizedReason}]"),
            ]);

            $this->inventoryService->handleCancelledItem(
                $lockedItem->fresh(['order', 'product.recipes.unit', 'product.recipes.ingredient.unit']),
                $user,
                $wasStarted,
                $normalizedReason,
            );

            $activeSubtotal = (float) OrderItem::where('order_id', $order->id)
                ->where('status', '!=', 'cancelled')
                ->sum('line_total');
            $hasActiveItems = OrderItem::where('order_id', $order->id)
                ->where('status', '!=', 'cancelled')
                ->exists();

            $orderUpdates = ['subtotal' => $activeSubtotal];
            if (! in_array($oldPaymentStatus, ['paid', 'partial_refund'], true)) {
                $orderUpdates['total_amount'] = max(0.0, $activeSubtotal - (float) $order->discount_amount);
            }
            if (! $hasActiveItems) {
                $orderUpdates['status'] = 'cancelled';
                $orderUpdates['cancelled_at'] = now();
                $orderUpdates['cancelled_by'] = $user->id;
                $orderUpdates['note'] = trim(($order->note ? $order->note.' ' : '')
                    ."[Hủy món: {$normalizedReason}]");
            }
            $order->update($orderUpdates);

            if (! $hasActiveItems && $order->table_id) {
                $table = RestaurantTable::whereKey($order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();
                if ($table && ! $table->orders()->activeForService()->exists()) {
                    $table->update(['status' => 'available']);
                }
            }

            AuditLog::log('order_item_cancelled', 'updated', $lockedItem, null, [
                'order_id' => $order->id,
                'product_name' => $lockedItem->product?->name,
                'quantity' => (float) $lockedItem->quantity,
                'reason' => $normalizedReason,
                'was_started' => $wasStarted,
                'refund_amount' => $refundAmount,
                'cancelled_by_user_id' => $user->id,
                'cancelled_by_user_name' => $user->name,
            ]);

            return [
                'product_name' => $lockedItem->product?->name ?? 'Món ăn',
                'table_name' => $order->table?->name ?? 'Mang về',
                'quantity' => (float) $lockedItem->quantity,
                'order_id' => (int) $order->id,
                'refund_amount' => $refundAmount,
                'was_started' => $wasStarted,
            ];
        });

        $this->broadcastCancellation($result, $user, trim($reason));

        return $result;
    }

    public function broadcastCancellation(array $result, User $user, string $reason): void
    {
        try {
            event(new KitchenUpdated($user->restaurant_id));
            event(new KitchenItemCancelled(
                restaurantId: $user->restaurant_id,
                productName: $result['product_name'],
                tableName: $result['table_name'],
                quantity: $result['quantity'],
                scope: 'single',
                reason: $reason,
                cancelledByName: $user->name,
                cancelledCount: (int) round($result['quantity']),
                orderId: $result['order_id'],
                orderIds: [$result['order_id']],
            ));
        } catch (\Throwable $exception) {
            Log::warning('Kitchen cancellation notification skipped.', [
                'restaurant_id' => $user->restaurant_id,
                'order_id' => $result['order_id'],
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
