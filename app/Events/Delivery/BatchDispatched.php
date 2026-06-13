<?php

namespace App\Events\Delivery;

use App\Models\Delivery\DeliveryBatch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
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

    public function broadcastAs(): string
    {
        return 'batch.dispatched';
    }

    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batch->id,
            'shipper_id' => $this->batch->shipper_id,
            'total_orders' => $this->batch->total_orders,
            'status' => $this->batch->status,
            'dispatched_at' => $this->batch->dispatched_at?->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
