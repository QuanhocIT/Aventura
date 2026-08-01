<?php

namespace App\Events\Delivery;

use App\Models\Delivery\DeliveryBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchDispatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeliveryBatch $batch) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->batch->restaurant_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'batch' => [
                'id' => $this->batch->id,
                'status' => $this->batch->status,
                'shipper_id' => $this->batch->shipper_id,
                'shipper_name' => $this->batch->shipper?->employee?->full_name
                    ?? $this->batch->shipper?->employee?->name,
                'total_orders' => $this->batch->total_orders,
                'estimated_duration_minutes' => $this->batch->estimated_duration_minutes,
                'dispatched_at' => $this->batch->dispatched_at?->toISOString(),
                'items' => $this->batch->items->map(fn ($item) => [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'sequence_order' => $item->sequence_order,
                    'status' => $item->status,
                    'eta' => $item->eta?->toISOString(),
                    'address' => $item->order?->deliveryDetail?->address,
                    'customer_name' => $item->order?->deliveryDetail?->customer_name,
                ])->values(),
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'batch.dispatched';
    }
}
