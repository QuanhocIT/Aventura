<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpamOrderCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $orderData,
        public string $cancelledBy,
        public string $reason
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->orderData['restaurant_id']}")];
    }

    public function broadcastAs(): string
    {
        return 'spam-order.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'order_data' => $this->orderData,
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
            'timestamp' => now()->format('H:i:s d/m/Y'),
        ];
    }
}
