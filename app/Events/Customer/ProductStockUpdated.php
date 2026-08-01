<?php

namespace App\Events\Customer;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $restaurantId) {}

    public function broadcastOn(): array
    {
        return [new Channel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'product.stock_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
