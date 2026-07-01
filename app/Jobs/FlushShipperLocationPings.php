<?php

namespace App\Jobs;

use App\Events\Delivery\ShipperLocationUpdated;
use App\Models\Delivery\Shipper;
use App\Models\Delivery\ShipperLocationLog;
use App\Services\Delivery\RouteOptimizationService;
use App\Services\Delivery\ShipperPingBuffer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Drains the Redis ping buffer for one shipper and performs the actual DB write,
 * broadcast, and ETA recalculation that used to happen synchronously on every single
 * GPS ping. Dispatched (delayed, debounced) from ShipperPwaController::updateLocation()
 * — see ShipperPingBuffer::claimFlush().
 */
class FlushShipperLocationPings implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $shipperId,
        public int $restaurantId,
    ) {
        $this->onQueue('location-pings');
    }

    // A ping's implied accuracy radius beyond this is too imprecise to trust as a real
    // position update (still stored, just flagged).
    private const MAX_PLAUSIBLE_ACCURACY_M = 100;

    public function handle(ShipperPingBuffer $buffer, RouteOptimizationService $router): void
    {
        $pings = $buffer->drain($this->shipperId);

        if (empty($pings)) {
            return;
        }

        $shipper = Shipper::find($this->shipperId);

        if (! $shipper) {
            return;
        }

        // Anti-spoofing: don't just trust the client's self-reported location. Walk the
        // pings in order, starting from the shipper's last known position, and flag any
        // ping whose implied speed since the previous point is physically implausible
        // for its vehicle type, or whose GPS accuracy is too coarse to trust.
        $maxSpeedKmh = $shipper->getMaxPlausibleSpeedKmh();
        $prevLat = $shipper->current_lat !== null ? (float) $shipper->current_lat : null;
        $prevLng = $shipper->current_lng !== null ? (float) $shipper->current_lng : null;
        $prevTime = $shipper->last_seen_at;

        $rows = [];
        $lastUnflagged = null;

        foreach ($pings as $p) {
            $loggedAt = Carbon::parse($p['logged_at'] ?? now());
            $flaggedReason = null;

            if (($p['accuracy_m'] ?? 0) > self::MAX_PLAUSIBLE_ACCURACY_M) {
                $flaggedReason = 'low_accuracy';
            } elseif ($prevLat !== null && $prevLng !== null && $prevTime) {
                $hours = max(0.0001, $prevTime->diffInSeconds($loggedAt, true) / 3600);
                $impliedSpeed = $router->haversine($prevLat, $prevLng, (float) $p['latitude'], (float) $p['longitude']) / $hours;

                if ($impliedSpeed > $maxSpeedKmh) {
                    $flaggedReason = 'implausible_speed';
                }
            }

            $rows[] = [
                'shipper_id'     => $this->shipperId,
                'restaurant_id'  => $this->restaurantId,
                'latitude'       => $p['latitude'],
                'longitude'      => $p['longitude'],
                'speed_kmh'      => $p['speed_kmh'] ?? null,
                'accuracy_m'     => $p['accuracy_m'] ?? null,
                'flagged_reason' => $flaggedReason,
                'logged_at'      => $loggedAt,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            if ($flaggedReason !== 'implausible_speed') {
                $lastUnflagged = $p;
            }

            $prevLat = (float) $p['latitude'];
            $prevLng = (float) $p['longitude'];
            $prevTime = $loggedAt;
        }

        $last = end($pings);
        // Prefer the last ping that wasn't flagged as an implausible jump so a spoofed
        // point can't corrupt the shipper's live position on the dashboard/ETA calc —
        // but still fall back to the raw last ping rather than freezing position updates
        // entirely if every point in this batch happened to be flagged.
        $positionSource = $lastUnflagged ?? $last;

        DB::transaction(function () use ($rows, $shipper, $positionSource) {
            ShipperLocationLog::insert($rows);

            // One UPDATE for the whole batch instead of one per ping — avoids hot-row
            // contention on the shippers table under high ping frequency.
            $shipper->update([
                'current_lat'  => $positionSource['latitude'],
                'current_lng'  => $positionSource['longitude'],
                'last_seen_at' => now(),
            ]);
        });

        $buffer = app(ShipperPingBuffer::class);

        // Debounced broadcast: at most once per 2s per shipper, regardless of how many
        // pings were just flushed, so the Reverb channel isn't flooded.
        if ($buffer->shouldRunDebounced('broadcast', $this->shipperId, 2)) {
            ShipperLocationUpdated::broadcastOnQueue(
                $shipper->fresh(),
                (float) $positionSource['latitude'],
                (float) $positionSource['longitude'],
                isset($positionSource['speed_kmh']) ? (float) $positionSource['speed_kmh'] : null,
            );
        }

        // Debounced ETA recalculation: only recompute when the shipper has an active
        // batch, and only every few seconds — recalculating on every flush would still
        // be wasteful if pings are frequent.
        if ($buffer->shouldRunDebounced('eta_recalc', $this->shipperId, 5)) {
            $activeBatch = $shipper->activeBatch()->first();

            if ($activeBatch) {
                RecalculateShipperEtasJob::dispatch(
                    $activeBatch->id,
                    (float) $positionSource['latitude'],
                    (float) $positionSource['longitude'],
                );
            }
        }
    }
}
