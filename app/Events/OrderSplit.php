<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderSplit implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public Order $newOrder, public $user) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->order->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'order.split';
    }

    public function broadcastWith(): array
    {
        $this->order->load('table');
        $this->newOrder->load('table');

        return [
            'original_order_number' => $this->order->order_number,
            'original_table_name' => $this->order->table?->name,
            'new_order_number' => $this->newOrder->order_number,
            'new_table_name' => $this->newOrder->table?->name,
            'split_by' => $this->user->name ?? 'Nhân viên',
        ];
    }
}
