<?php

namespace App\Events\Kitchen;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenWaiterCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public int $orderId,
        public string $orderNumber,
        public string $tableName,
        public string $itemName = '',
        public string $message = 'Món ăn đã chế biến xong, bếp gọi phục vụ lấy món gấp!'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'kitchen.waiter_called';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'table_name' => $this->tableName,
            'item_name' => $this->itemName,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
