<?php

namespace App\Events\Delivery;

use App\Models\Delivery\DeliveryBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryEtaUpdated implements ShouldBroadcastNow
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
            'batch_id' => $this->batch->id,
            'items'    => $this->batch->items->map(fn ($item) => [
                'id'      => $item->id,
                'eta'     => $item->eta?->toISOString(),
                'status'  => $item->status,
            ])->values(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.eta.updated';
    }
}
