<?php

namespace App\Events\Delivery;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipperLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public int $shipperId,
        public float $latitude,
        public float $longitude,
        public ?float $speedKmh = null
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'shipper.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'shipper_id' => $this->shipperId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed_kmh' => $this->speedKmh,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
