<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QrOrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->order->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'qr-order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'table_name' => $this->order->table?->name,
                'total_amount' => (float) $this->order->total_amount,
                'items_count' => $this->order->items->count(),
                'created_at' => $this->order->created_at->format('H:i'),
            ]
        ];
    }
}
