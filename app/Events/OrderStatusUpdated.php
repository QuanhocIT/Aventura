<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Guest-safe order status event. The tracking token is a per-order capability;
 * no restaurant or customer data is exposed on this channel.
 */
class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel("order.{$this->order->tracking_token}")];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}
