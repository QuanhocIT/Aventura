<?php

namespace App\Events\Delivery;

use App\Models\Delivery\Shipper;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipperLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Shipper $shipper,
        public float $latitude,
        public float $longitude,
        public ?float $speedKmh = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->shipper->restaurant_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'shipper_id' => $this->shipper->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed_kmh' => $this->speedKmh,
            'logged_at' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipper.location.updated';
    }
}
