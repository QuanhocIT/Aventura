<?php

namespace App\Http\Controllers\Delivery;

use App\Events\Delivery\ShipperLocationUpdated;
use App\Http\Controllers\Controller;
use App\Jobs\FlushShipperLocationPings;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\Shipper;
use App\Models\Delivery\ShipperLocationLog;
use App\Services\Delivery\DeliveryDispatchService;
use App\Services\Delivery\ShipperPingBuffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShipperPwaController extends Controller
{
    // Debounce window: how long to accumulate pings in Redis before a flush job drains
    // them into MySQL. Keeps the live map feeling real-time without writing/broadcasting
    // on every single ping.
    private const FLUSH_DEBOUNCE_SECONDS = 3;

    public function __construct(
        private DeliveryDispatchService $dispatcher,
        private ShipperPingBuffer $pingBuffer,
    ) {}

    /** GET /delivery/shipper — shipper's PWA page */
    public function app(Request $request): Response
    {
        $user = $request->user();

        $shipper = Shipper::with(['employee', 'activeBatch.items.order.deliveryDetail'])
            ->active()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        return Inertia::render('delivery/shipper/App', [
            'shipper'      => $shipper ? [
                'id'           => $shipper->id,
                'vehicle_type' => $shipper->vehicle_type,
                'name'         => $shipper->employee?->full_name,
                'active_batch' => $shipper->activeBatch ? [
                    'id'     => $shipper->activeBatch->id,
                    'status' => $shipper->activeBatch->status,
                    'items'  => $shipper->activeBatch->items->map(fn ($item) => [
                        'id'             => $item->id,
                        'order_id'       => $item->order_id,
                        'order_number'   => $item->order?->order_number,
                        'sequence_order' => $item->sequence_order,
                        'status'         => $item->status,
                        'eta'            => $item->eta?->toISOString(),
                        'address'        => $item->order?->deliveryDetail?->address,
                        'customer_name'  => $item->order?->deliveryDetail?->customer_name,
                        'phone'          => $item->order?->deliveryDetail?->phone,
                        'latitude'       => $item->order?->deliveryDetail?->latitude ? (float) $item->order->deliveryDetail->latitude : null,
                        'longitude'      => $item->order?->deliveryDetail?->longitude ? (float) $item->order->deliveryDetail->longitude : null,
                        'cod_amount'     => (float) ($item->order?->deliveryDetail?->cod_amount ?? 0),
                    ])->values(),
                ] : null,
            ] : null,
        ]);
    }

    /** POST /delivery/api/shipper/location — single GPS ping */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => ['required', 'integer'],
            'latitude'   => ['required', 'numeric', 'between:-90,90'],
            'longitude'  => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh'  => ['nullable', 'numeric', 'min:0'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        // Push to Redis and return immediately — no DB write, broadcast, or ETA
        // recalculation happens inline. A debounced queued job (FlushShipperLocationPings)
        // drains the buffer shortly after, batching all of that work per shipper instead
        // of doing it on every single ping.
        $this->pingBuffer->push($shipper->id, [
            'latitude'   => $validated['latitude'],
            'longitude'  => $validated['longitude'],
            'speed_kmh'  => $validated['speed_kmh'] ?? null,
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'logged_at'  => now()->toDateTimeString(),
        ]);

        if ($this->pingBuffer->claimFlush($shipper->id, self::FLUSH_DEBOUNCE_SECONDS)) {
            FlushShipperLocationPings::dispatch($shipper->id, $shipper->restaurant_id)
                ->delay(now()->addSeconds(self::FLUSH_DEBOUNCE_SECONDS));
        }

        return response()->json(['ok' => true, 'buffered' => true]);
    }

    /** POST /delivery/api/shipper/location/batch — flush offline GPS queue */
    public function updateLocationBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => ['required', 'integer'],
            'pings'      => ['required', 'array', 'min:1', 'max:50'],
            'pings.*.latitude'   => ['required', 'numeric', 'between:-90,90'],
            'pings.*.longitude'  => ['required', 'numeric', 'between:-180,180'],
            'pings.*.speed_kmh'  => ['nullable', 'numeric', 'min:0'],
            'pings.*.accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'pings.*.logged_at'  => ['nullable', 'date'],
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        DB::transaction(function () use ($shipper, $validated) {
            $logs = array_map(fn ($p) => [
                'shipper_id'  => $shipper->id,
                'restaurant_id'=> $shipper->restaurant_id,
                'latitude'    => $p['latitude'],
                'longitude'   => $p['longitude'],
                'speed_kmh'   => $p['speed_kmh'] ?? null,
                'accuracy_m'  => $p['accuracy_m'] ?? null,
                'logged_at'   => $p['logged_at'] ?? now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $validated['pings']);

            ShipperLocationLog::insert($logs);

            // Update shipper position to last ping
            $last = end($validated['pings']);
            $shipper->update([
                'current_lat'  => $last['latitude'],
                'current_lng'  => $last['longitude'],
                'last_seen_at' => now(),
            ]);
        });

        $last = end($validated['pings']);

        if ($this->pingBuffer->shouldRunDebounced('broadcast', $shipper->id, 2)) {
            ShipperLocationUpdated::broadcastOnQueue(
                $shipper->refresh(),
                (float) $last['latitude'],
                (float) $last['longitude'],
                isset($last['speed_kmh']) ? (float) $last['speed_kmh'] : null,
            );
        }

        if ($this->pingBuffer->shouldRunDebounced('eta_recalc', $shipper->id, 5)) {
            $activeBatch = $shipper->activeBatch()->first();

            if ($activeBatch) {
                \App\Jobs\RecalculateShipperEtasJob::dispatch(
                    $activeBatch->id,
                    (float) $last['latitude'],
                    (float) $last['longitude'],
                );
            }
        }

        return response()->json(['ok' => true, 'count' => count($validated['pings'])]);
    }

    /** POST /delivery/api/shipper/items/{item}/status — update delivery item status */
    public function updateItemStatus(Request $request, DeliveryBatchItem $item): JsonResponse
    {
        abort_if($item->batch->restaurant_id !== $request->user()->restaurant_id, 403);

        $userShipper = Shipper::whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))->first();
        abort_if(!$userShipper || $item->batch->shipper_id !== $userShipper->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:picked_up,delivered,failed'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $item = $this->dispatcher->updateItemStatus(
            $item,
            $validated['status'],
            $validated['notes'] ?? null,
        );

        return response()->json(['item' => $item]);
    }
}
