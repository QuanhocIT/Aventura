<?php

namespace App\Events\Delivery;

use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryBatchItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DeliveryBatchItem $item,
        public ?DeliveryBatch $batch = null,
    ) {}

    public function broadcastOn(): array
    {
        $restaurantId = $this->batch?->restaurant_id ?? $this->item->batch?->restaurant_id;

        return [new PrivateChannel("delivery.{$restaurantId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'item' => [
                'id' => $this->item->id,
                'batch_id' => $this->item->batch_id,
                'order_id' => $this->item->order_id,
                'sequence_order' => $this->item->sequence_order,
                'status' => $this->item->status,
                'picked_up_at' => $this->item->picked_up_at?->toISOString(),
                'delivered_at' => $this->item->delivered_at?->toISOString(),
                'eta' => $this->item->eta?->toISOString(),
            ],
            'batch_status' => $this->batch?->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.status.updated';
    }
}
