<?php

namespace App\Events\Delivery;

use App\Models\Delivery\Shipper;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Queued (not ShouldBroadcastNow) so a slow/unavailable Reverb connection can never add
// latency to the GPS ping request path. NOTE: Laravel's BroadcastEvent job wrapper does
// NOT read a $queue property off the event, so this alone would still land on the
// default queue — callers must dispatch via broadcastOnQueue() below to actually route
// onto the dedicated 'location-pings' Horizon queue.
class ShipperLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const QUEUE = 'location-pings';

    public function __construct(
        public Shipper $shipper,
        public float   $latitude,
        public float   $longitude,
        public ?float  $speedKmh = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->shipper->restaurant_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'shipper_id' => $this->shipper->id,
            'latitude'   => $this->latitude,
            'longitude'  => $this->longitude,
            'speed_kmh'  => $this->speedKmh,
            'logged_at'  => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipper.location.updated';
    }

    /**
     * Broadcast this event on the dedicated 'location-pings' queue instead of using the
     * plain broadcast() helper, which would otherwise dispatch Laravel's internal
     * BroadcastEvent job onto the default queue regardless of any $queue property here.
     */
    public static function broadcastOnQueue(Shipper $shipper, float $latitude, float $longitude, ?float $speedKmh = null): void
    {
        $event = new self($shipper, $latitude, $longitude, $speedKmh);

        dispatch((new \Illuminate\Broadcasting\BroadcastEvent($event))->onQueue(self::QUEUE));
    }
}
