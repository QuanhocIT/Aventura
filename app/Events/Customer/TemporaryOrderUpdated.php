<?php

namespace App\Events\Customer;

use App\Models\TemporaryOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemporaryOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TemporaryOrder $temporaryOrder) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("restaurant.{$this->temporaryOrder->restaurant_id}"),
            new Channel("table.{$this->temporaryOrder->table_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'temporary_order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'       => $this->temporaryOrder->id,
            'table_id' => $this->temporaryOrder->table_id,
            'status'   => $this->temporaryOrder->status,
            'order_id' => $this->temporaryOrder->order_id,
        ];
    }
}
