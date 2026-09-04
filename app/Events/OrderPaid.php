<?php

namespace App\Events;

use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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
    ) {
        $this->order->loadMissing('table');
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("restaurant.{$this->restaurantId}")];

        /** @var RestaurantTable|null $table */
        $table = $this->order->table ?? RestaurantTable::find($this->tableId);
        if ($table && ! empty($table->qr_token)) {
            $channels[] = new Channel('table.'.$table->qr_token);
        }

        return $channels;
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
