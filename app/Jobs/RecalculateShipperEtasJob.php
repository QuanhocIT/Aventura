<?php

namespace App\Jobs;

use App\Models\Delivery\DeliveryBatch;
use App\Services\Delivery\DeliveryDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs ETA recalculation off the HTTP request path. Dispatched from the location-ping
 * flush job rather than directly from the controller, and only when the shipper has
 * moved meaningfully since the last recalculation (see ShipperPingBuffer debounce).
 */
class RecalculateShipperEtasJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $batchId,
        public float $latitude,
        public float $longitude,
    ) {
        $this->onQueue('location-pings');
    }

    public function handle(DeliveryDispatchService $dispatcher): void
    {
        $batch = DeliveryBatch::find($this->batchId);

        if (! $batch) {
            return;
        }

        $dispatcher->recalculateEtas($batch, $this->latitude, $this->longitude);
    }
}
