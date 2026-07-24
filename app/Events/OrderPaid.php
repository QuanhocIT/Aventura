<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public int $restaurantId,
        public int $tableId
    ) {}

    public function broadcastOn(): array
    {
        $tableToken = $this->order->table?->qr_token ?? $this->tableId;
        return [
            new Channel("restaurant.{$this->restaurantId}"),
            new Channel("table.{$tableToken}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'payment_status' => 'paid',
            'status' => 'completed',
        ];
    }
}
