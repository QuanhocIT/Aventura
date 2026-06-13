<?php

namespace App\Events\Delivery;

use App\Models\Delivery\DeliveryBatchItem;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Full-payload broadcast so manager dashboard can update item in-place
 * without triggering a full Inertia page reload.
 */
class DeliveryStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int              $restaurantId,
        public readonly DeliveryBatchItem $item
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'delivery.status.updated';
    }

    public function broadcastWith(): array
    {
        $item   = $this->item;
        $batch  = $item->batch;
        $detail = $item->deliveryDetail;

        // Compute batch progress for optimistic UI update
        $done  = $batch?->items()->whereIn('status', ['delivered', 'failed'])->count() ?? 0;
        $total = $batch?->items()->count() ?? 0;

        return [
            // Item info
            'batch_id'       => $item->batch_id,
            'item_id'        => $item->id,
            'order_id'       => $item->order_id,
            'sequence_order' => $item->sequence_order,
            'status'         => $item->status,
            'picked_up_at'   => $item->picked_up_at?->toIso8601String(),
            'delivered_at'   => $item->delivered_at?->toIso8601String(),
            'notes'          => $item->notes,
            // Delivery detail — allows in-place update
            'customer_name'  => $detail?->customer_name,
            'address'        => $detail?->address,
            'phone'          => $detail?->phone,
            // Batch summary — allows progress bar update
            'batch_status'   => $batch?->status,
            'batch_done'     => $done,
            'batch_total'    => $total,
            'completed_at'   => $batch?->completed_at?->toIso8601String(),
            'timestamp'      => now()->toIso8601String(),
        ];
    }
}
