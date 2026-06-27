<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryBatch;
use App\Models\Delivery\DeliveryDetail;
use App\Models\Delivery\Shipper;
use App\Models\Order;
use App\Services\Delivery\DeliveryDispatchService;
use App\Services\Delivery\GeoClusteringService;
use App\Services\Delivery\LoadBalancingService;
use App\Services\Delivery\RouteOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryManagementController extends Controller
{
    public function __construct(
        private DeliveryDispatchService  $dispatcher,
        private RouteOptimizationService $router,
        private GeoClusteringService     $clustering,
        private LoadBalancingService     $loadBalancer,
    ) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'qr_ordering')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'qr_ordering',
                'feature_label' => 'Giao hàng',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;

        $initialStats = $this->buildStats($restaurantId);

        return Inertia::render('delivery/Index', [
            'initialStats' => $initialStats,
        ]);
    }

    /** GET /delivery/api/unassigned-orders */
    public function unassignedOrders(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $orders = Order::with('deliveryDetail', 'items')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'confirmed')
            ->whereHas('deliveryDetail', fn ($q) => $q->where('delivery_status', 'pending'))
            ->whereDoesntHave('batchItems', fn ($q) => $q->whereHas('batch', fn ($bq) => $bq->whereIn('status', ['pending', 'dispatched', 'in_progress'])))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $o) => [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'status'         => $o->status,
                'total_amount'   => (float) $o->total_amount,
                'created_at'     => $o->created_at?->toISOString(),
                'delivery_detail' => $o->deliveryDetail ? [
                    'id'              => $o->deliveryDetail->id,
                    'customer_name'   => $o->deliveryDetail->customer_name,
                    'phone'           => $o->deliveryDetail->phone,
                    'address'         => $o->deliveryDetail->address,
                    'latitude'        => $o->deliveryDetail->latitude ? (float) $o->deliveryDetail->latitude : null,
                    'longitude'       => $o->deliveryDetail->longitude ? (float) $o->deliveryDetail->longitude : null,
                    'delivery_fee'    => (float) $o->deliveryDetail->delivery_fee,
                    'delivery_status' => $o->deliveryDetail->delivery_status,
                ] : null,
                'items_count' => $o->items->count(),
            ]);

        return response()->json($orders);
    }

    /** GET /delivery/api/active-shippers */
    public function activeShippers(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $shippers = Shipper::with([
                'employee',
                'activeBatch.items.order.deliveryDetail',
                'locationLogs' => fn ($q) => $q->orderByDesc('logged_at')->limit(20),
            ])
            ->where('restaurant_id', $restaurantId)
            ->active()
            ->get()
            ->map(fn (Shipper $s) => [
                'id'                   => $s->id,
                'name'                 => $s->employee?->full_name ?? "Shipper #{$s->id}",
                'vehicle_type'         => $s->vehicle_type,
                'vehicle_plate'        => $s->vehicle_plate,
                'is_active'            => $s->is_active,
                'max_orders_per_batch' => $s->max_orders_per_batch,
                'current_lat'          => $s->current_lat ? (float) $s->current_lat : null,
                'current_lng'          => $s->current_lng ? (float) $s->current_lng : null,
                'last_seen_at'         => $s->last_seen_at?->toISOString(),
                'has_gps'              => $s->hasGps(),
                'current_load'         => $s->getCurrentLoad(),
                // GPS trail: newest first → reverse for chronological order
                'trail' => $s->locationLogs
                    ->sortBy('logged_at')
                    ->map(fn ($l) => ['lat' => (float) $l->latitude, 'lng' => (float) $l->longitude])
                    ->values(),
                'active_batch'         => $s->activeBatch ? [
                    'id'                         => $s->activeBatch->id,
                    'status'                     => $s->activeBatch->status,
                    'total_orders'               => $s->activeBatch->total_orders,
                    'estimated_duration_minutes' => $s->activeBatch->estimated_duration_minutes,
                    'dispatched_at'              => $s->activeBatch->dispatched_at?->toISOString(),
                    'optimized_route'            => $s->activeBatch->optimized_route,
                    'items'                      => $s->activeBatch->items->map(fn ($item) => [
                        'id'             => $item->id,
                        'order_id'       => $item->order_id,
                        'order_number'   => $item->order?->order_number,
                        'sequence_order' => $item->sequence_order,
                        'status'         => $item->status,
                        'eta'            => $item->eta?->toISOString(),
                        'picked_up_at'   => $item->picked_up_at?->toISOString(),
                        'delivered_at'   => $item->delivered_at?->toISOString(),
                        'address'        => $item->order?->deliveryDetail?->address,
                        'customer_name'  => $item->order?->deliveryDetail?->customer_name,
                        'phone'          => $item->order?->deliveryDetail?->phone,
                        'latitude'       => $item->order?->deliveryDetail?->latitude ? (float) $item->order->deliveryDetail->latitude : null,
                        'longitude'      => $item->order?->deliveryDetail?->longitude ? (float) $item->order->deliveryDetail->longitude : null,
                        'total_amount'   => (float) ($item->order?->total_amount ?? 0),
                        'cod_amount'     => (float) ($item->order?->deliveryDetail?->cod_amount ?? 0),
                        'order_created_at'   => $item->order?->created_at?->toISOString(),
                        'order_confirmed_at' => $item->order?->confirmed_at?->toISOString(),
                    ])->values(),
                ] : null,
            ]);

        return response()->json($shippers);
    }

    /** POST /delivery/api/optimize-route */
    public function optimizeRoute(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $validated = $request->validate([
            'order_ids'  => ['required', 'array', 'min:1'],
            'order_ids.*'=> ['required', 'integer', "exists:orders,id,restaurant_id,{$restaurantId}"],
            'shipper_id' => ['nullable', 'integer', "exists:shippers,id,restaurant_id,{$restaurantId}"],
        ]);

        $shipper = null;
        $origin  = null;
        $speed   = 32;

        if (!empty($validated['shipper_id'])) {
            $shipper = Shipper::find($validated['shipper_id']);
            if ($shipper?->current_lat) {
                $origin = ['lat' => (float) $shipper->current_lat, 'lng' => (float) $shipper->current_lng];
            }
            $speed = $shipper?->getSpeedKmh() ?? 32;
        }

        $orders = Order::with('deliveryDetail')
            ->whereIn('id', $validated['order_ids'])
            ->where('restaurant_id', $restaurantId)
            ->get();

        $points = $orders
            ->filter(fn ($o) => $o->deliveryDetail?->hasCoordinates())
            ->map(fn ($o) => [
                'id'           => $o->id,
                'lat'          => (float) $o->deliveryDetail->latitude,
                'lng'          => (float) $o->deliveryDetail->longitude,
                'order_number' => $o->order_number,
                'address'      => $o->deliveryDetail->address,
                'customer'     => $o->deliveryDetail->customer_name,
            ])
            ->values()
            ->all();

        $optimized   = $this->router->optimize($points, $origin, $speed);
        $totalKm     = $this->router->totalDistanceKm($optimized);
        $totalMinutes = $speed > 0 ? ($totalKm / $speed) * 60 : 0;

        return response()->json([
            'route'                    => $optimized,
            'total_distance_km'        => round($totalKm, 2),
            'estimated_duration_minutes' => (int) $totalMinutes,
        ]);
    }

    /** POST /delivery/api/suggest-shippers */
    public function suggestShippers(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $validated = $request->validate([
            'order_ids'   => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', "exists:orders,id,restaurant_id,{$restaurantId}"],
        ]);

        $firstOrder = Order::with('deliveryDetail')
            ->whereIn('id', $validated['order_ids'])
            ->where('restaurant_id', $restaurantId)
            ->whereHas('deliveryDetail')
            ->first();

        $lat = $firstOrder?->deliveryDetail?->latitude ? (float) $firstOrder->deliveryDetail->latitude : 10.7769;
        $lng = $firstOrder?->deliveryDetail?->longitude ? (float) $firstOrder->deliveryDetail->longitude : 106.7009;

        $shippers = Shipper::with(['employee', 'activeBatch.items'])
            ->where('restaurant_id', $restaurantId)
            ->active()
            ->get();

        $ranked = $this->loadBalancer->rankShippers($shippers, $lat, $lng);

        return response()->json($ranked);
    }

    /** POST /delivery/api/suggest-batches */
    public function suggestBatches(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $validated = $request->validate([
            'order_ids'     => ['required', 'array', 'min:1'],
            'order_ids.*'   => ['required', 'integer', "exists:orders,id,restaurant_id,{$restaurantId}"],
            'max_per_batch' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $orders = Order::with('deliveryDetail')
            ->whereIn('id', $validated['order_ids'])
            ->where('restaurant_id', $restaurantId)
            ->get()
            ->map(fn ($o) => [
                'id'              => $o->id,
                'order_number'    => $o->order_number,
                'delivery_detail' => $o->deliveryDetail ? [
                    'latitude'  => $o->deliveryDetail->latitude ? (float) $o->deliveryDetail->latitude : null,
                    'longitude' => $o->deliveryDetail->longitude ? (float) $o->deliveryDetail->longitude : null,
                ] : null,
            ])
            ->all();

        $clusters = $this->clustering->suggestBatches($orders, $validated['max_per_batch'] ?? 5);

        return response()->json($clusters);
    }

    /** POST /delivery/api/batches */
    public function createBatch(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;

        $validated = $request->validate([
            'shipper_id'  => ['required', 'integer', "exists:shippers,id,restaurant_id,{$restaurantId}"],
            'order_ids'   => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', "exists:orders,id,restaurant_id,{$restaurantId}"],
        ]);

        $batch = $this->dispatcher->createBatch(
            $validated['shipper_id'],
            $validated['order_ids'],
            $request->user(),
        );

        return response()->json(['batch' => $batch], 201);
    }

    /** POST /delivery/api/batches/{batch}/dispatch */
    public function dispatchBatch(Request $request, DeliveryBatch $batch): JsonResponse
    {
        abort_if($batch->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_if($batch->status !== 'pending', 422, 'Batch không ở trạng thái pending');

        $batch = $this->dispatcher->dispatchBatch($batch);

        return response()->json(['batch' => $batch]);
    }

    /** POST /delivery/api/batches/{batch}/complete */
    public function completeBatch(Request $request, DeliveryBatch $batch): JsonResponse
    {
        abort_if($batch->restaurant_id !== $request->user()->restaurant_id, 403);

        $batch->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['batch' => $batch]);
    }

    /** POST /delivery/api/batches/{batch}/cancel */
    public function cancelBatch(Request $request, DeliveryBatch $batch): JsonResponse
    {
        abort_if($batch->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_if($batch->status === 'completed', 422, 'Không thể hủy batch đã hoàn thành');

        $this->dispatcher->cancelBatch($batch);

        return response()->json(['message' => 'Batch đã được hủy']);
    }

    /** GET /delivery/api/stats */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->buildStats($request->user()->restaurant_id));
    }

    private function buildStats(int $restaurantId): array
    {
        $today     = today();
        $yesterday = today()->subDay();

        $pendingOrders = Order::where('restaurant_id', $restaurantId)
            ->where('status', 'confirmed')
            ->whereHas('deliveryDetail', fn ($q) => $q->where('delivery_status', 'pending'))
            ->count();

        $activeShippers = Shipper::where('restaurant_id', $restaurantId)
            ->active()
            ->count();

        $activeBatches = DeliveryBatch::where('restaurant_id', $restaurantId)
            ->active()
            ->count();

        $deliveredToday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', $today)
            ->count();

        $failedToday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'failed')
            ->whereDate('updated_at', $today)
            ->count();

        $deliveredYesterday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', $yesterday)
            ->count();

        $failedYesterday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'failed')
            ->whereDate('updated_at', $yesterday)
            ->count();

        return [
            'pending_orders'      => $pendingOrders,
            'active_shippers'     => $activeShippers,
            'active_batches'      => $activeBatches,
            'delivered_today'     => $deliveredToday,
            'failed_today'        => $failedToday,
            'delivered_yesterday' => $deliveredYesterday,
            'failed_yesterday'    => $failedYesterday,
        ];
    }
}
