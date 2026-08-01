<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nghiệp vụ tách bill, gộp đơn và chuyển bàn — chỉ áp dụng cho đơn CHƯA
 * thanh toán. Mọi thao tác chạy trong transaction + ghi audit log.
 */
class OrderSplitService
{
    /**
     * Tách các món được chọn sang một bill mới (cùng bàn/kênh) — dùng khi
     * nhóm khách muốn thanh toán riêng. Phải để lại ít nhất 1 món ở bill gốc.
     */
    public function splitOrder(Order $order, array $itemIds, User $user): Order
    {
        $this->assertEditable($order);

        return DB::transaction(function () use ($order, $itemIds, $user) {
            $items = OrderItem::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Chọn ít nhất một món để tách.']);
            }

            $totalItems = OrderItem::withoutGlobalScopes()->where('order_id', $order->id)->count();

            if ($items->count() >= $totalItems) {
                throw ValidationException::withMessages(['items' => 'Không thể tách toàn bộ món — bill gốc phải còn ít nhất 1 món.']);
            }

            $newOrder = Order::create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'table_id' => $order->table_id,
                'customer_id' => $order->customer_id,
                'created_by' => $user->id,
                'order_number' => $order->order_number.'-T'.strtoupper(substr(uniqid(), -4)),
                'channel' => $order->channel,
                'status' => $order->status,
                'payment_status' => 'unpaid',
                'subtotal' => 0,
                'discount_amount' => 0,
                'service_charge' => 0,
                'total_amount' => 0,
                'note' => "Tách từ bill {$order->order_number}",
            ]);

            OrderItem::withoutGlobalScopes()
                ->whereIn('id', $items->pluck('id'))
                ->update(['order_id' => $newOrder->id]);

            $this->recalcTotals($order);
            $this->recalcTotals($newOrder);

            AuditLog::log('order_split', 'updated', $order, [], [
                'new_order' => $newOrder->order_number,
                'moved_items' => $items->pluck('id')->all(),
            ]);

            return $newOrder->fresh();
        });
    }

    /** Gộp toàn bộ món của đơn nguồn vào đơn đích (cùng nhà hàng, cả hai chưa thanh toán). */
    public function mergeOrders(Order $source, Order $target, User $user): Order
    {
        $this->assertEditable($source);
        $this->assertEditable($target);

        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['order' => 'Không thể gộp đơn vào chính nó.']);
        }

        if ($source->restaurant_id !== $target->restaurant_id) {
            throw ValidationException::withMessages(['order' => 'Hai đơn không thuộc cùng nhà hàng.']);
        }

        return DB::transaction(function () use ($source, $target) {
            OrderItem::withoutGlobalScopes()
                ->where('order_id', $source->id)
                ->update(['order_id' => $target->id]);

            $this->recalcTotals($target);

            // Giải phóng bàn của đơn nguồn nếu khác bàn đơn đích
            if ($source->table_id && $source->table_id !== $target->table_id) {
                RestaurantTable::withoutGlobalScopes()
                    ->where('id', $source->table_id)
                    ->update(['status' => 'available']);
            }

            $source->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'subtotal' => 0,
                'total_amount' => 0,
                'note' => trim(($source->note ? $source->note.' ' : '')."[Đã gộp vào bill {$target->order_number}]"),
            ]);

            AuditLog::log('order_merged', 'updated', $target, [], [
                'merged_from' => $source->order_number,
            ]);

            return $target->fresh();
        });
    }

    /** Chuyển đơn sang bàn khác (bàn mới phải trống). */
    public function moveTable(Order $order, int $tableId, User $user): void
    {
        $this->assertEditable($order);

        DB::transaction(function () use ($order, $tableId) {
            $newTable = RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $order->restaurant_id)
                ->where('id', $tableId)
                ->lockForUpdate()
                ->first();

            if (! $newTable) {
                throw ValidationException::withMessages(['table' => 'Bàn không tồn tại.']);
            }

            if ($newTable->status !== 'available') {
                throw ValidationException::withMessages(['table' => "Bàn \"{$newTable->name}\" đang có khách."]);
            }

            $oldTableId = $order->table_id;

            $order->update(['table_id' => $newTable->id]);
            $newTable->update(['status' => 'occupied']);

            // Bàn cũ trống nếu không còn đơn active nào khác
            if ($oldTableId) {
                $stillActive = Order::withoutGlobalScopes()
                    ->where('table_id', $oldTableId)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->exists();

                if (! $stillActive) {
                    RestaurantTable::withoutGlobalScopes()
                        ->where('id', $oldTableId)
                        ->update(['status' => 'available']);
                }
            }

            AuditLog::log('order_table_moved', 'updated', $order, ['table_id' => $oldTableId], ['table_id' => $newTable->id]);
        });
    }

    private function assertEditable(Order $order): void
    {
        if ($order->payment_status === 'paid' || in_array($order->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['order' => "Bill {$order->order_number} đã thanh toán/đóng — không thể thao tác."]);
        }
    }

    private function recalcTotals(Order $order): void
    {
        $subtotal = (float) OrderItem::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->sum('line_total');

        $order->update([
            'subtotal' => $subtotal,
            'total_amount' => max(0, $subtotal - (float) $order->discount_amount + (float) $order->service_charge),
        ]);
    }
}
