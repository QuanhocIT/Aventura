<?php

namespace App\Services\Delivery;

use App\Events\Delivery\BatchDispatched;
use App\Events\Delivery\DeliveryEtaUpdated;
use App\Events\Delivery\DeliveryStatusUpdated;
use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\DeliveryDetail;
use App\Models\Delivery\Shipper;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDispatchService
{
    public function __construct(
        private RouteOptimizationService $router,
    ) {}

    /**
     * Create a new batch and assign orders to it.
     * $orderIds: array of order IDs (must have delivery_details with coordinates)
     */
    public function createBatch(int $shipperId, array $orderIds, User $createdBy): DeliveryBatch
    {
        return DB::transaction(function () use ($shipperId, $orderIds, $createdBy) {
            $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

            $shipper = Shipper::with('employee')
                ->where('restaurant_id', $createdBy->restaurant_id)
                ->lockForUpdate()
                ->findOrFail($shipperId);

            if (! $shipper->is_active) {
                throw ValidationException::withMessages([
                    'shipper_id' => 'Shipper đang bị tạm ngưng.',
                ]);
            }

            $hasOpenBatch = DeliveryBatch::query()
                ->where('restaurant_id', $createdBy->restaurant_id)
                ->where('shipper_id', $shipper->id)
                ->whereIn('status', ['pending', 'dispatched', 'in_progress'])
                ->exists();

            if ($hasOpenBatch) {
                throw ValidationException::withMessages([
                    'shipper_id' => 'Shipper đang có một chuyến chưa hoàn tất.',
                ]);
            }

            if (count($orderIds) > (int) $shipper->max_orders_per_batch) {
                throw ValidationException::withMessages([
                    'order_ids' => "Chuyến này vượt giới hạn {$shipper->max_orders_per_batch} đơn của shipper.",
                ]);
            }

            $orders = Order::with('deliveryDetail')
                ->where('restaurant_id', $createdBy->restaurant_id)
                ->whereIn('id', $orderIds)
                ->get();

            if ($orders->count() !== count($orderIds)) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Có đơn hàng không thuộc nhà hàng hiện tại.',
                ]);
            }

            $eligibleOrders = $orders->filter(function (Order $order): bool {
                return $order->status === 'confirmed'
                    && $order->deliveryDetail?->delivery_status === 'pending';
            });

            if ($eligibleOrders->count() !== count($orderIds)) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Chỉ có thể phân công đơn đã xác nhận và đang chờ giao.',
                ]);
            }

            $alreadyAssigned = DeliveryBatchItem::query()
                ->whereIn('order_id', $orderIds)
                ->whereHas('batch', fn ($query) => $query->whereIn('status', ['pending', 'dispatched', 'in_progress']))
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Có đơn đã nằm trong một chuyến giao khác.',
                ]);
            }

            // Build geo points for route optimization
            $points = $orders
                ->filter(fn ($o) => $o->deliveryDetail?->hasCoordinates())
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'lat' => (float) $o->deliveryDetail->latitude,
                    'lng' => (float) $o->deliveryDetail->longitude,
                    'order_number' => $o->order_number,
                    'address' => $o->deliveryDetail->address,
                    'customer' => $o->deliveryDetail->customer_name,
                ])
                ->values()
                ->all();

            $origin = ($shipper->current_lat !== null)
                ? ['lat' => (float) $shipper->current_lat, 'lng' => (float) $shipper->current_lng]
                : null;

            $optimized = $this->router->optimize($points, $origin, $shipper->getSpeedKmh());
            $totalKm = $this->router->totalDistanceKm($optimized);

            $batch = DeliveryBatch::create([
                'shipper_id' => $shipper->id,
                'created_by' => $createdBy->id,
                'status' => 'pending',
                'total_orders' => count($orderIds),
                'optimized_route' => $optimized,
                'estimated_duration_minutes' => $optimized
                    ? (int) ($optimized[array_key_last($optimized)]['eta_minutes'] ?? 0)
                    : null,
                'total_distance_km' => (int) round($totalKm),
            ]);

            foreach ($optimized as $stop) {
                DeliveryBatchItem::create([
                    'batch_id' => $batch->id,
                    'order_id' => $stop['id'],
                    'sequence_order' => $stop['sequence'],
                    'status' => 'pending',
                    'eta' => $stop['eta'],
                ]);

                // Mark delivery_detail as assigned
                DeliveryDetail::where('order_id', $stop['id'])
                    ->update(['delivery_status' => 'assigned']);
            }

            // Handle orders with no coordinates (append at end)
            $missingIds = array_diff($orderIds, array_column($optimized, 'id'));
            $seq = count($optimized) + 1;
            foreach ($missingIds as $oid) {
                DeliveryBatchItem::create([
                    'batch_id' => $batch->id,
                    'order_id' => $oid,
                    'sequence_order' => $seq++,
                    'status' => 'pending',
                ]);
                DeliveryDetail::where('order_id', $oid)->update(['delivery_status' => 'assigned']);
            }

            return $batch->load('items');
        });
    }

    /**
     * Dispatch a batch to the shipper (changes status pending → dispatched).
     */
    public function dispatchBatch(DeliveryBatch $batch): DeliveryBatch
    {
        DB::transaction(function () use ($batch) {
            $batch->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ]);

            // Do not rewrite the order status here. The order has already been
            // validated as confirmed before assignment; changing it during
            // dispatch could interfere with the kitchen workflow.
        });

        broadcast(new BatchDispatched($batch->load(['shipper.employee', 'items.order.deliveryDetail'])));

        return $batch->refresh();
    }

    /**
     * Update a single batch item status (picked_up / delivered / failed).
     */
    public function updateItemStatus(DeliveryBatchItem $item, string $status, ?string $notes = null): DeliveryBatchItem
    {
        return DB::transaction(function () use ($item, $status, $notes) {
            $item->loadMissing('batch');

            if ($item->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Điểm giao này đã ở trạng thái kết thúc.',
                ]);
            }

            if (! $item->batch->accepted_at) {
                throw ValidationException::withMessages([
                    'batch' => 'Shipper chưa nhận chuyến giao hàng.',
                ]);
            }

            if ($status === 'picked_up' && ! in_array($item->status, ['pending', 'in_transit'])) {
                throw ValidationException::withMessages([
                    'status' => 'Đơn chỉ có thể chuyển sang đã lấy hàng từ trạng thái chờ hoặc đang vận chuyển.',
                ]);
            }

            if ($status === 'delivered' && ! in_array($item->status, ['picked_up', 'in_transit'])) {
                throw ValidationException::withMessages([
                    'status' => 'Cần xác nhận đã lấy hàng trước khi xác nhận đã giao.',
                ]);
            }

            if ($status === 'failed' && blank($notes)) {
                throw ValidationException::withMessages([
                    'notes' => 'Vui lòng nhập lý do giao thất bại.',
                ]);
            }

            $now = now();
            $update = ['status' => $status, 'notes' => $notes];

            if ($status === 'picked_up') {
                $update['picked_up_at'] = $now;
                DeliveryDetail::where('order_id', $item->order_id)->update(['delivery_status' => 'picked_up']);
            } elseif ($status === 'delivered') {
                $update['delivered_at'] = $now;
                DeliveryDetail::where('order_id', $item->order_id)->update([
                    'delivery_status' => 'delivered',
                    'delivered_at' => $now,
                ]);
                Order::where('id', $item->order_id)->update([
                    'status' => 'completed',
                    'completed_at' => $now,
                ]);
            } elseif ($status === 'failed') {
                DeliveryDetail::where('order_id', $item->order_id)->update(['delivery_status' => 'failed']);
            }

            $item->update($update);

            // Check if entire batch is done
            $batch = $item->batch()->with('items')->first();
            if ($batch && $batch->items->every(fn ($i) => $i->isCompleted())) {
                $batch->update(['status' => 'completed', 'completed_at' => $now]);
            } elseif ($batch && $batch->status === 'dispatched') {
                $batch->update(['status' => 'in_progress']);
            }

            broadcast(new DeliveryStatusUpdated($item->load('order.deliveryDetail'), $batch));

            $trackedOrder = $item->order?->fresh();
            if ($trackedOrder?->tracking_token) {
                broadcast(new \App\Events\OrderStatusUpdated($trackedOrder));
            }

            return $item->refresh();
        });
    }

    /**
     * Recalculate ETAs for remaining items in a batch based on current shipper position.
     */
    public function recalculateEtas(DeliveryBatch $batch, float $currentLat, float $currentLng): void
    {
        $shipper = $batch->shipper;
        if (! $shipper) {
            return;
        }

        $pendingItems = $batch->items()
            ->whereIn('status', ['pending', 'picked_up'])
            ->with('order.deliveryDetail')
            ->orderBy('sequence_order')
            ->get();

        $now = now();
        $cumulativeKm = 0;
        $prevLat = $currentLat;
        $prevLng = $currentLng;

        foreach ($pendingItems as $item) {
            $detail = $item->order?->deliveryDetail;
            if (! $detail?->hasCoordinates()) {
                continue;
            }

            $km = $this->router->haversine($prevLat, $prevLng, (float) $detail->latitude, (float) $detail->longitude);
            $cumulativeKm += $km;
            $speed = $shipper->getSpeedKmh();
            $minutes = $speed > 0 ? ($cumulativeKm / $speed) * 60 : 0;
            $eta = $now->copy()->addMinutes((int) $minutes);

            $item->update(['eta' => $eta]);

            $prevLat = (float) $detail->latitude;
            $prevLng = (float) $detail->longitude;
        }

        broadcast(new DeliveryEtaUpdated($batch->refresh()));
    }

    public function cancelBatch(DeliveryBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            if (in_array($batch->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'batch' => 'Không thể hủy chuyến đã kết thúc.',
                ]);
            }

            $orderIds = $batch->items()->pluck('order_id');

            // Cancellation is not a delivery failure. Return each item to a
            // neutral state so it can be assigned to another shipper.
            $batch->items()->update([
                'status' => 'pending',
                'picked_up_at' => null,
                'delivered_at' => null,
            ]);
            $batch->update(['status' => 'cancelled']);

            DeliveryDetail::whereIn('order_id', $orderIds)->update(['delivery_status' => 'pending']);
        });
    }
}
