<?php

namespace App\Services\Delivery;

use App\Events\Delivery\BatchDispatched;
use App\Events\Delivery\DeliveryEtaUpdated;
use App\Events\Delivery\DeliveryStatusUpdated;
use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\Shipper;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDispatchService
{
    public function __construct(
        private RouteOptimizationService $routeService
    ) {}

    /**
     * Create a delivery batch from a list of order IDs assigned to a shipper.
     *
     * Improvements vs original:
     * - Passes vehicle_type to optimizeRoute() for accurate ETA
     * - Estimates weight from order total when product weight unavailable
     * - Populates estimated_delivery_at on each DeliveryDetail
     */
    public function createBatch(array $orderIds, int $shipperId, int $userId): DeliveryBatch
    {
        $shipper = Shipper::with('employee')->findOrFail($shipperId);

        $orders = Order::with('deliveryDetail')
            ->whereIn('id', $orderIds)
            ->get();

        foreach ($orders as $order) {
            if ($order->channel !== 'delivery') {
                throw ValidationException::withMessages([
                    'orders' => "Đơn #{$order->order_number} không phải đơn giao hàng.",
                ]);
            }
            if (! $order->deliveryDetail) {
                throw ValidationException::withMessages([
                    'orders' => "Đơn #{$order->order_number} chưa có thông tin địa chỉ giao hàng.",
                ]);
            }
            if (DeliveryBatchItem::where('order_id', $order->id)->exists()) {
                throw ValidationException::withMessages([
                    'orders' => "Đơn #{$order->order_number} đã được gán vào batch khác.",
                ]);
            }
        }

        $stops = $orders->map(fn (Order $o) => [
            'order_id'     => $o->id,
            'order_number' => $o->order_number,
            'address'      => $o->deliveryDetail->address,
            'latitude'     => (float) $o->deliveryDetail->latitude,
            'longitude'    => (float) $o->deliveryDetail->longitude,
            'customer_name'=> $o->deliveryDetail->customer_name,
            'phone'        => $o->deliveryDetail->phone,
            'delivery_fee' => (float) $o->deliveryDetail->delivery_fee,
        ])->values()->toArray();

        $restaurant = $orders->first()->restaurant;
        $origin = [
            'latitude'  => (float) ($restaurant->latitude  ?? 10.7769),
            'longitude' => (float) ($restaurant->longitude ?? 106.7009),
            'address'   => $restaurant->address ?? '',
        ];

        // Use shipper's vehicle type for accurate speed-based ETA
        $optimizedRoute = $this->routeService->optimizeRoute($stops, $origin, $shipper->vehicle_type ?? 'motorbike');

        // Total estimated distance (sum of per-leg km)
        $totalDistanceKm = collect($optimizedRoute)->sum('distance_from_prev_km')
            ?: $orders->sum(fn ($o) => $o->deliveryDetail->estimated_distance_km ?? 0);

        // Weight estimation: sum order totals ÷ 50_000 (rough kg proxy when no product weights)
        // Restaurants can later integrate actual product weight columns
        $estimatedWeightKg = round(
            $orders->sum(fn ($o) => $o->total_amount) / 50_000,
            2
        );

        return DB::transaction(function () use ($orders, $shipper, $optimizedRoute, $totalDistanceKm, $estimatedWeightKg, $userId): DeliveryBatch {
            $lastStop = last($optimizedRoute);

            $batch = DeliveryBatch::create([
                'shipper_id'                 => $shipper->id,
                'created_by'                 => $userId,
                'status'                     => 'draft',
                'total_orders'               => $orders->count(),
                'total_weight_kg'            => $estimatedWeightKg,
                'optimized_route'            => $optimizedRoute,
                'estimated_distance_km'      => $totalDistanceKm,
                'estimated_duration_minutes' => $lastStop['estimated_arrival_minutes'] ?? 0,
            ]);

            foreach ($optimizedRoute as $stop) {
                $order  = $orders->firstWhere('id', $stop['order_id']);
                $detail = $order->deliveryDetail;

                DeliveryBatchItem::create([
                    'batch_id'           => $batch->id,
                    'order_id'           => $order->id,
                    'delivery_detail_id' => $detail->id,
                    'sequence_order'     => $stop['sequence'],
                    'status'             => 'pending',
                ]);

                $detail->update(['delivery_status' => 'assigned']);
            }

            return $batch->load('items.deliveryDetail', 'shipper.employee');
        });
    }

    /**
     * Dispatch a batch — transitions to 'dispatched', populates estimated_delivery_at per stop.
     */
    public function dispatchBatch(DeliveryBatch $batch): DeliveryBatch
    {
        if ($batch->status !== 'draft') {
            throw ValidationException::withMessages([
                'batch' => "Batch này không thể dispatch (trạng thái: {$batch->status}).",
            ]);
        }

        $now = now();

        $batch->update([
            'status'       => 'dispatched',
            'dispatched_at'=> $now,
        ]);

        // Populate estimated_delivery_at on each DeliveryDetail from the optimized route
        $routeByOrderId = collect($batch->optimized_route ?? [])->keyBy('order_id');

        $batch->items()->with('deliveryDetail')->get()->each(function (DeliveryBatchItem $item) use ($now, $routeByOrderId) {
            $item->deliveryDetail->update(['delivery_status' => 'in_progress']);

            $etaMinutes = $routeByOrderId->get($item->order_id)['estimated_arrival_minutes'] ?? null;
            if ($etaMinutes !== null) {
                $item->deliveryDetail->update([
                    'estimated_delivery_at' => $now->copy()->addMinutes($etaMinutes),
                ]);
            }
        });

        broadcast(new BatchDispatched($batch->refresh()));

        return $batch;
    }

    /**
     * Mark a single delivery stop as picked_up, delivered, or failed.
     *
     * Improvements vs original:
     * - Transitions batch → in_progress on first picked_up
     * - Recalculates remaining ETAs based on actual pickup time
     * - Broadcasts full item payload (no extra reload needed)
     * - Syncs order.status = completed for delivered items
     */
    public function updateItemStatus(DeliveryBatchItem $item, string $status, ?string $notes = null): DeliveryBatchItem
    {
        $now     = now();
        $updates = ['status' => $status, 'notes' => $notes];
        $batch   = $item->batch;

        if ($status === 'picked_up') {
            $updates['picked_up_at'] = $now;

            // First pick_up → batch transitions to in_progress
            if ($batch->status === 'dispatched') {
                $batch->update(['status' => 'in_progress']);
            }

            // Recalculate remaining ETAs based on actual elapsed time since dispatch
            $this->recalculateRemainingEtas($batch, $item->order_id, $now);

        } elseif ($status === 'delivered') {
            $updates['delivered_at'] = $now;
            $item->deliveryDetail->update(['delivery_status' => 'delivered', 'delivered_at' => $now]);

        } elseif ($status === 'failed') {
            $item->deliveryDetail->update(['delivery_status' => 'failed']);
        }

        $item->update($updates);
        $freshItem = $item->fresh(['deliveryDetail', 'batch']);

        broadcast(new DeliveryStatusUpdated($batch->restaurant_id, $freshItem));

        // Auto-complete batch when all items are terminal
        $allDone = $batch->items()->whereNotIn('status', ['delivered', 'failed'])->doesntExist();
        if ($allDone) {
            $batch->update(['status' => 'completed', 'completed_at' => $now]);

            // Sync order.status = completed for delivered items only (bulk update to avoid N+1)
            $deliveredOrderIds = $batch->items()->where('status', 'delivered')->pluck('order_id');
            Order::whereIn('id', $deliveredOrderIds)->update(['status' => 'completed', 'completed_at' => $now]);
        }

        return $freshItem;
    }

    /**
     * Recalculate ETA for remaining stops after the first pickup occurs.
     * Uses actual elapsed time since dispatch to adjust subsequent ETAs.
     */
    private function recalculateRemainingEtas(DeliveryBatch $batch, int $pickedUpOrderId, \Carbon\Carbon $now): void
    {
        if (! $batch->dispatched_at || empty($batch->optimized_route)) return;

        $elapsedMin   = $batch->dispatched_at->diffInMinutes($now);
        $route        = $batch->optimized_route;
        $pickedSeq    = collect($route)->where('order_id', $pickedUpOrderId)->value('sequence') ?? 1;

        // Adjust ETAs for remaining stops proportionally
        $adjustedRoute = collect($route)->map(function ($stop) use ($pickedSeq, $elapsedMin) {
            if ($stop['sequence'] <= $pickedSeq) return $stop;
            // Remaining ETA = original ETA − elapsed + small buffer
            $originalEta = $stop['estimated_arrival_minutes'] ?? 0;
            $stop['estimated_arrival_minutes'] = max(1, $originalEta - $elapsedMin + 2);
            return $stop;
        })->toArray();

        $batch->update(['optimized_route' => $adjustedRoute]);

        // Broadcast updated ETAs to manager dashboard
        broadcast(new DeliveryEtaUpdated($batch->restaurant_id, $batch->id, $adjustedRoute));
    }
}
