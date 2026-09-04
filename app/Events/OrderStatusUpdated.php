<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order status event broadcasted to tracking channel, restaurant staff, and table.
 */
class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing('table');
    }

    public function broadcastOn(): array
    {
        $channels = [];

        if (! empty($this->order->tracking_token)) {
            $channels[] = new Channel("order.{$this->order->tracking_token}");
        }

        if (! empty($this->order->restaurant_id)) {
            $channels[] = new PrivateChannel("restaurant.{$this->order->restaurant_id}");
        }

        /** @var \App\Models\RestaurantTable|null $table */
        $table = $this->order->table;
        if ($table && ! empty($table->qr_token)) {
            $channels[] = new Channel('table.'.$table->qr_token);
        }

        return $channels;
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
