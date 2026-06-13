<?php

namespace App\Http\Controllers\Delivery;

use App\Events\Delivery\ShipperLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\Shipper;
use App\Models\Delivery\ShipperLocationLog;
use App\Services\Delivery\DeliveryDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipperPwaController extends Controller
{
    public function __construct(private DeliveryDispatchService $dispatchService) {}

    public function app(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        // Find shipper profile linked to current user's employee record
        $shipper = Shipper::where('restaurant_id', $restaurantId)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->with(['activeBatch.items.deliveryDetail'])
            ->first();

        return Inertia::render('delivery/shipper/App', [
            'shipper' => $shipper ? [
                'id' => $shipper->id,
                'vehicle_type' => $shipper->vehicle_type,
                'vehicle_plate' => $shipper->vehicle_plate,
                'active_batch' => $shipper->activeBatch ? $this->formatBatch($shipper->activeBatch) : null,
            ] : null,
            'restaurant_id' => $restaurantId,
        ]);
    }

    /**
     * Single GPS ping — used for immediate dispatch or fallback.
     * Returns recommended next_interval_ms so the client can self-throttle.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => 'required|integer|exists:shippers,id',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
            'speed_kmh'  => 'nullable|numeric|min:0',
            'heading'    => 'nullable|numeric|min:0|max:360',
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();
        $speed   = (float) ($validated['speed_kmh'] ?? 0);

        ShipperLocationLog::create([
            'shipper_id'    => $shipper->id,
            'restaurant_id' => $shipper->restaurant_id,
            'latitude'      => $validated['latitude'],
            'longitude'     => $validated['longitude'],
            'speed_kmh'     => $speed ?: null,
            'logged_at'     => now(),
        ]);

        $this->pruneOldLogs($shipper->id);

        broadcast(new ShipperLocationUpdated(
            $shipper->restaurant_id,
            $shipper->id,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $speed ?: null,
        ));

        return response()->json([
            'ok'                 => true,
            'next_interval_ms'   => $this->adaptiveIntervalMs($speed),
        ]);
    }

    /**
     * Batched GPS upload — client queues up to 5 pings locally and flushes.
     * Only the latest position triggers a real-time broadcast; older points are
     * stored for trail history without spamming WebSocket.
     */
    public function updateLocationBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id'          => 'required|integer|exists:shippers,id',
            'pings'               => 'required|array|min:1|max:10',
            'pings.*.latitude'    => 'required|numeric|between:-90,90',
            'pings.*.longitude'   => 'required|numeric|between:-180,180',
            'pings.*.speed_kmh'   => 'nullable|numeric|min:0',
            'pings.*.heading'     => 'nullable|numeric|min:0|max:360',
            'pings.*.logged_at'   => 'nullable|date',
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();
        $pings   = $validated['pings'];
        $now     = now();

        // Bulk-insert all pings (no individual broadcast per ping)
        $rows = array_map(fn ($p) => [
            'shipper_id'    => $shipper->id,
            'restaurant_id' => $shipper->restaurant_id,
            'latitude'      => $p['latitude'],
            'longitude'     => $p['longitude'],
            'speed_kmh'     => isset($p['speed_kmh']) ? (float) $p['speed_kmh'] : null,
            'logged_at'     => isset($p['logged_at']) ? $p['logged_at'] : $now->toDateTimeString(),
        ], $pings);

        ShipperLocationLog::insert($rows);
        $this->pruneOldLogs($shipper->id);

        // Broadcast only the most recent ping to avoid WebSocket spam
        $latest      = last($pings);
        $latestSpeed = (float) ($latest['speed_kmh'] ?? 0);

        broadcast(new ShipperLocationUpdated(
            $shipper->restaurant_id,
            $shipper->id,
            (float) $latest['latitude'],
            (float) $latest['longitude'],
            $latestSpeed ?: null,
        ));

        return response()->json([
            'ok'               => true,
            'stored'           => count($pings),
            'next_interval_ms' => $this->adaptiveIntervalMs($latestSpeed),
        ]);
    }

    public function updateItemStatus(Request $request, DeliveryBatchItem $item): JsonResponse
    {
        abort_if($item->batch->restaurant_id !== $request->user()->restaurant_id, 403);

        $validated = $request->validate([
            'status' => 'required|in:picked_up,delivered,failed',
            'notes' => 'nullable|string|max:500',
        ]);

        $item = $this->dispatchService->updateItemStatus(
            $item,
            $validated['status'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Đã cập nhật trạng thái.',
            'status' => $item->status,
        ]);
    }

    /**
     * Return recommended GPS polling interval (ms) based on current speed.
     * Stationary (<3 km/h) → 30s  |  Slow (<15 km/h) → 10s  |  Normal → 5s
     */
    private function adaptiveIntervalMs(float $speedKmh): int
    {
        if ($speedKmh < 3)  return 30_000;
        if ($speedKmh < 15) return 10_000;
        return 5_000;
    }

    private function pruneOldLogs(int $shipperId): void
    {
        ShipperLocationLog::where('shipper_id', $shipperId)
            ->where('logged_at', '<', now()->subHours(24))
            ->delete();
    }

    private function formatBatch(DeliveryBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'total_orders' => $batch->total_orders,
            'optimized_route' => $batch->optimized_route,
            'items' => $batch->items->map(fn ($item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'sequence_order' => $item->sequence_order,
                'status' => $item->status,
                'picked_up_at' => $item->picked_up_at?->toIso8601String(),
                'delivered_at' => $item->delivered_at?->toIso8601String(),
                'address' => $item->deliveryDetail?->address,
                'customer_name' => $item->deliveryDetail?->customer_name,
                'phone' => $item->deliveryDetail?->phone,
                'latitude' => $item->deliveryDetail?->latitude,
                'longitude' => $item->deliveryDetail?->longitude,
                'notes' => $item->notes,
            ])->toArray(),
        ];
    }
}
