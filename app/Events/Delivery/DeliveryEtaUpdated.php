<?php

namespace App\Events\Delivery;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast recalculated ETAs for remaining stops after first pick-up.
 * Manager dashboard listens and updates ETA countdowns in-place.
 */
class DeliveryEtaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $restaurantId,
        public readonly int   $batchId,
        public readonly array $updatedRoute  // optimized_route array with recalculated ETAs
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("delivery.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'delivery.eta.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'batch_id'      => $this->batchId,
            'updated_route' => $this->updatedRoute,
            'timestamp'     => now()->toIso8601String(),
        ];
    }
}
