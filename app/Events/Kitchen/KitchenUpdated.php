<?php

namespace App\Events\Kitchen;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $restaurantId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("kitchen.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'kitchen.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
