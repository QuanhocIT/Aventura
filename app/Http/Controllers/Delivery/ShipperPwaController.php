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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShipperPwaController extends Controller
{
    public function __construct(private DeliveryDispatchService $dispatcher) {}

    /** GET /delivery/shipper — shipper's PWA page */
    public function app(Request $request): Response
    {
        $shipper = $this->findCurrentShipper($request);

        return Inertia::render('delivery/shipper/App', [
            'shipper' => $this->serializeShipper($shipper),
        ]);
    }

    /** GET /delivery/api/shipper/current — lightweight polling for a new batch. */
    public function current(Request $request): JsonResponse
    {
        return response()->json([
            'shipper' => $this->serializeShipper($this->findCurrentShipper($request)),
        ]);
    }

    /** POST /delivery/api/shipper/batches/{batch}/accept — shipper accepts dispatch. */
    public function acceptBatch(Request $request, DeliveryBatch $batch): JsonResponse
    {
        $shipper = $this->findCurrentShipper($request);

        abort_unless($shipper, 403, 'Tài khoản chưa được đăng ký làm shipper.');
        abort_if($batch->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_if($batch->shipper_id !== $shipper->id, 403);
        $this->assertBatchBranch($shipper, $batch);
        abort_if($batch->status !== 'dispatched', 422, 'Chuyến không còn chờ shipper nhận.');

        $batch->update(['accepted_at' => now()]);

        return response()->json([
            'batch' => $batch->fresh(['items']),
        ]);
    }

    /** POST /delivery/api/shipper/location — single GPS ping */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        DB::transaction(function () use ($shipper, $validated) {
            ShipperLocationLog::create([
                'shipper_id' => $shipper->id,
                'restaurant_id' => $shipper->restaurant_id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'speed_kmh' => $validated['speed_kmh'] ?? null,
                'accuracy_m' => $validated['accuracy_m'] ?? null,
                'logged_at' => now(),
            ]);

            $shipper->update([
                'current_lat' => $validated['latitude'],
                'current_lng' => $validated['longitude'],
                'last_seen_at' => now(),
            ]);
        });

        broadcast(new ShipperLocationUpdated(
            $shipper,
            $validated['latitude'],
            $validated['longitude'],
            $validated['speed_kmh'] ?? null,
        ));

        // Recalculate ETAs if shipper has active batch
        $activeBatch = $shipper->activeBatch()->first();
        if ($activeBatch) {
            $this->dispatcher->recalculateEtas(
                $activeBatch,
                $validated['latitude'],
                $validated['longitude'],
            );
        }

        return response()->json(['ok' => true]);
    }

    /** POST /delivery/api/shipper/location/batch — flush offline GPS queue */
    public function updateLocationBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => ['required', 'integer'],
            'pings' => ['required', 'array', 'min:1', 'max:50'],
            'pings.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'pings.*.speed_kmh' => ['nullable', 'numeric', 'min:0'],
            'pings.*.accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'pings.*.logged_at' => ['nullable', 'date'],
        ]);

        $shipper = Shipper::where('id', $validated['shipper_id'])
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        DB::transaction(function () use ($shipper, $validated) {
            $logs = array_map(fn ($p) => [
                'shipper_id' => $shipper->id,
                'restaurant_id' => $shipper->restaurant_id,
                'latitude' => $p['latitude'],
                'longitude' => $p['longitude'],
                'speed_kmh' => $p['speed_kmh'] ?? null,
                'accuracy_m' => $p['accuracy_m'] ?? null,
                'logged_at' => $p['logged_at'] ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ], $validated['pings']);

            ShipperLocationLog::insert($logs);

            // Update shipper position to last ping
            $last = end($validated['pings']);
            $shipper->update([
                'current_lat' => $last['latitude'],
                'current_lng' => $last['longitude'],
                'last_seen_at' => now(),
            ]);
        });

        $last = end($validated['pings']);
        broadcast(new ShipperLocationUpdated(
            $shipper->refresh(),
            $last['latitude'],
            $last['longitude'],
            $last['speed_kmh'] ?? null,
        ));

        return response()->json(['ok' => true, 'count' => count($validated['pings'])]);
    }

    /** POST /delivery/api/shipper/items/{item}/status — update delivery item status */
    public function updateItemStatus(Request $request, DeliveryBatchItem $item): JsonResponse
    {
        $item->loadMissing('batch.shipper.employee', 'order');
        abort_if($item->batch->restaurant_id !== $request->user()->restaurant_id, 403);

        $userShipper = Shipper::whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))->first();
        abort_if(! $userShipper || $item->batch->shipper_id !== $userShipper->id, 403);
        $this->assertBatchBranch($userShipper, $item->batch);

        $validated = $request->validate([
            'status' => ['required', 'in:picked_up,delivered,failed'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === 'failed' && blank($validated['notes'] ?? null)) {
            abort(422, 'Vui lòng nhập lý do giao thất bại.');
        }

        $item = $this->dispatcher->updateItemStatus(
            $item,
            $validated['status'],
            $validated['notes'] ?? null,
        );

        return response()->json(['item' => $item]);
    }

    private function findCurrentShipper(Request $request): ?Shipper
    {
        return Shipper::with(['employee', 'activeBatch.items.order.deliveryDetail'])
            ->active()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();
    }

    private function assertBatchBranch(Shipper $shipper, DeliveryBatch $batch): void
    {
        $shipper->loadMissing('employee');
        $batch->loadMissing('items.order');
        $branchId = $shipper->employee?->branch_id;

        abort_unless($branchId !== null && $batch->items->isNotEmpty(), 403);
        abort_unless(
            $batch->items->every(fn ($item): bool => (int) $item->order?->branch_id === (int) $branchId),
            403,
            'Chuyáº¿n giao khÃ´ng thuá»™c chi nhÃ¡nh cá»§a shipper.',
        );
    }

    private function serializeShipper(?Shipper $shipper): ?array
    {
        if (! $shipper) {
            return null;
        }

        return [
            'id' => $shipper->id,
            'vehicle_type' => $shipper->vehicle_type,
            'name' => $shipper->employee?->full_name,
            'active_batch' => $shipper->activeBatch ? [
                'id' => $shipper->activeBatch->id,
                'status' => $shipper->activeBatch->status,
                'accepted_at' => $shipper->activeBatch->accepted_at?->toISOString(),
                'items' => $shipper->activeBatch->items->map(fn ($item) => [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_number' => $item->order?->order_number,
                    'sequence_order' => $item->sequence_order,
                    'status' => $item->status,
                    'eta' => $item->eta?->toISOString(),
                    'address' => $item->order?->deliveryDetail?->address,
                    'customer_name' => $item->order?->deliveryDetail?->customer_name,
                    'phone' => $item->order?->deliveryDetail?->phone,
                    'latitude' => $item->order?->deliveryDetail?->latitude ? (float) $item->order->deliveryDetail->latitude : null,
                    'longitude' => $item->order?->deliveryDetail?->longitude ? (float) $item->order->deliveryDetail->longitude : null,
                    'cod_amount' => (float) ($item->order?->deliveryDetail?->cod_amount ?? 0),
                ])->values(),
            ] : null,
        ];
    }
}
