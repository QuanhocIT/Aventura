<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FraudAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public array $alertData
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'fraud.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'alert' => $this->alertData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
