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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryManagementController extends Controller
{
    public function __construct(
        private DeliveryDispatchService  $dispatchService,
        private LoadBalancingService     $loadBalancingService,
        private RouteOptimizationService $routeService,
        private GeoClusteringService     $clusteringService,
    ) {}

    // ─── Pages ────────────────────────────────────────────────────────────────

    public function index(Request $request): Response|RedirectResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return redirect()->route('choose-restaurant');

        $restaurant = \App\Models\Restaurant::find($restaurantId);

        return Inertia::render('delivery/Index', [
            'unassigned_orders'  => $this->getUnassignedOrders($restaurantId),
            'active_shippers'    => $this->getActiveShippers($restaurantId),
            'active_batches'     => $this->getActiveBatches($restaurantId),
            'google_maps_key'    => config('services.google_maps.key', ''),
            'restaurant_address' => $restaurant?->address ?? '',
            'restaurant_name'    => $restaurant?->name ?? '',
            'restaurant_lat'     => (float) ($restaurant?->latitude  ?? 10.7769),
            'restaurant_lng'     => (float) ($restaurant?->longitude ?? 106.7009),
        ]);
    }

    // ─── JSON data endpoints ───────────────────────────────────────────────────

    public function unassignedOrders(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return response()->json(['error' => 'Chưa chọn nhà hàng'], 403);
        return response()->json($this->getUnassignedOrders($restaurantId));
    }

    public function activeShippers(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return response()->json(['error' => 'Chưa chọn nhà hàng'], 403);
        return response()->json($this->getActiveShippers($restaurantId));
    }

    // ─── Route optimization ────────────────────────────────────────────────────

    public function optimizeRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids'      => 'required|array|min:1|max:24',
            'order_ids.*'    => 'integer|exists:orders,id',
            'origin_address' => 'nullable|string|max:500',
            'vehicle_type'   => 'nullable|in:bike,motorbike,car',
        ]);

        $orders = Order::with('deliveryDetail')->whereIn('id', $validated['order_ids'])->get();

        if ($missing = $orders->first(fn ($o) => ! $o->deliveryDetail)) {
            return response()->json(['message' => "Đơn #{$missing->order_number} chưa có địa chỉ giao hàng."], 422);
        }

        $stops = $orders->map(fn (Order $o) => [
            'order_id'      => $o->id,
            'order_number'  => $o->order_number,
            'address'       => $o->deliveryDetail->address,
            'latitude'      => (float) $o->deliveryDetail->latitude,
            'longitude'     => (float) $o->deliveryDetail->longitude,
            'customer_name' => $o->deliveryDetail->customer_name,
            'phone'         => $o->deliveryDetail->phone,
        ])->values()->toArray();

        $restaurant = $orders->first()->restaurant;
        $origin = [
            'latitude'  => (float) ($restaurant->latitude  ?? 10.7769),
            'longitude' => (float) ($restaurant->longitude ?? 106.7009),
            'address'   => $validated['origin_address'] ?? $restaurant->address ?? '',
        ];

        $vehicleType = $validated['vehicle_type'] ?? 'motorbike';
        $route       = $this->routeService->optimizeRoute($stops, $origin, $vehicleType);
        $mapsUrl     = $this->routeService->getGoogleMapsDirectionsUrl($origin, $route);

        return response()->json([
            'optimized_route'      => $route,
            'maps_url'             => $mapsUrl,
            'total_stops'          => count($route),
            'total_distance_km'    => round(collect($route)->sum('distance_from_prev_km'), 2),
            'total_duration_min'   => last($route)['estimated_arrival_minutes'] ?? 0,
        ]);
    }

    // ─── Smart batch suggestions (K-means++ clustering) ───────────────────────

    /**
     * Suggest how to split unassigned orders into batches using geo-clustering.
     * Returns K groups (one per available shipper) with pre-scored shipper recommendations.
     */
    public function suggestBatches(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return response()->json(['suggestions' => []]);

        $orders = $this->getUnassignedOrders($restaurantId);

        // Filter orders that have GPS coordinates for clustering
        $mappableOrders = array_filter($orders, fn ($o) => $o['delivery']['latitude'] && $o['delivery']['longitude']);
        $mappableOrders = array_values($mappableOrders);

        if (count($mappableOrders) === 0) return response()->json(['suggestions' => []]);

        $activeShippers = Shipper::active()
            ->with(['employee', 'latestLocation', 'activeBatch.items'])
            ->where('restaurant_id', $restaurantId)
            ->get();

        $availableShippers = $activeShippers->filter(fn ($s) => $s->getCurrentLoad()['orders'] < $s->max_orders_per_batch);
        $k = max(1, min($availableShippers->count(), (int) ceil(count($mappableOrders) / 4)));

        // Flatten orders for clustering
        $clusterInput = array_map(fn ($o) => [
            'id'        => $o['id'],
            'latitude'  => $o['delivery']['latitude'],
            'longitude' => $o['delivery']['longitude'],
            'order'     => $o,
        ], $mappableOrders);

        $clusters = $this->clusteringService->cluster($clusterInput, $k);

        // For each cluster, find best-scoring shipper
        $suggestions = [];
        $assignedShipperIds = [];

        foreach ($clusters as $cluster) {
            if (empty($cluster['order_ids'])) continue;

            // Score shippers for this cluster's centroid
            $centroidDetail = new DeliveryDetail([
                'latitude'  => $cluster['centroid']['lat'],
                'longitude' => $cluster['centroid']['lng'],
            ]);

            $scored = $this->loadBalancingService->scoreShippers($centroidDetail, $availableShippers)
                ->filter(fn ($s) => ! in_array($s['shipper']->id, $assignedShipperIds))
                ->first();

            $suggestedShipper = $scored ? $scored['shipper'] : null;
            if ($suggestedShipper) $assignedShipperIds[] = $suggestedShipper->id;

            $suggestions[] = [
                'order_ids'         => $cluster['order_ids'],
                'order_count'       => $cluster['count'],
                'radius_km'         => $cluster['radius_km'],
                'centroid'          => $cluster['centroid'],
                'suggested_shipper' => $suggestedShipper ? [
                    'id'            => $suggestedShipper->id,
                    'name'          => $suggestedShipper->employee->full_name ?? $suggestedShipper->employee->name ?? '—',
                    'vehicle_type'  => $suggestedShipper->vehicle_type,
                    'vehicle_plate' => $suggestedShipper->vehicle_plate,
                    'score'         => $scored['score'],
                    'available_slots' => $scored['available_slots'],
                ] : null,
                'reason' => $this->clusterReason($cluster),
            ];
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    // ─── Shipper suggestions ───────────────────────────────────────────────────

    public function suggestShippers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return response()->json(['shippers' => []]);

        $firstOrder = Order::with('deliveryDetail')->whereIn('id', $validated['order_ids'])->where('restaurant_id', $restaurantId)->first();
        if (! $firstOrder?->deliveryDetail) return response()->json(['shippers' => []]);

        $shippers = Shipper::active()
            ->with(['employee', 'latestLocation', 'activeBatch.items'])
            ->where('restaurant_id', $restaurantId)
            ->get();

        $scored = $this->loadBalancingService->scoreShippers($firstOrder->deliveryDetail, $shippers);

        return response()->json([
            'shippers' => $scored->map(fn ($item) => [
                'id'              => $item['shipper']->id,
                'name'            => $item['shipper']->employee->full_name ?? $item['shipper']->employee->name,
                'vehicle_type'    => $item['shipper']->vehicle_type,
                'vehicle_plate'   => $item['shipper']->vehicle_plate,
                'score'           => $item['score'],
                'current_orders'  => $item['current_orders'],
                'available_slots' => $item['available_slots'],
                'max_orders'      => $item['shipper']->max_orders_per_batch,
                'has_gps'         => $item['has_gps'],
                'last_seen_at'    => $item['last_seen_at'],
                'distance_factor' => $item['distance_factor'],
            ]),
        ]);
    }

    // ─── Batch CRUD ────────────────────────────────────────────────────────────

    public function createBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1|max:24',
            'order_ids.*' => 'integer|exists:orders,id',
            'shipper_id'  => 'required|integer|exists:shippers,id',
        ]);

        $batch = $this->dispatchService->createBatch(
            $validated['order_ids'],
            $validated['shipper_id'],
            $request->user()->id
        );

        return response()->json(['message' => 'Batch đã được tạo thành công.', 'batch' => $this->formatBatch($batch)], 201);
    }

    public function dispatchBatch(DeliveryBatch $batch): JsonResponse
    {
        $batch = $this->dispatchService->dispatchBatch($batch);
        return response()->json(['message' => 'Batch đã được dispatch cho shipper.', 'batch' => $this->formatBatch($batch)]);
    }

    public function completeBatch(DeliveryBatch $batch): JsonResponse
    {
        $batch->update(['status' => 'completed', 'completed_at' => now()]);
        return response()->json(['message' => 'Batch đã hoàn thành.']);
    }

    public function cancelBatch(DeliveryBatch $batch): JsonResponse
    {
        if (in_array($batch->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Batch này không thể hủy.'], 422);
        }

        DB::transaction(function () use ($batch) {
            $batch->items()->with('deliveryDetail')->get()->each(function ($item) {
                $item->deliveryDetail?->update(['delivery_status' => 'pending', 'estimated_delivery_at' => null]);
            });
            $batch->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Đã hủy batch.']);
    }

    // ─── Stats / KPI ──────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        if (! $restaurantId) return response()->json(['error' => 'Chưa chọn nhà hàng'], 403);

        $today = today();

        $pendingOrders = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'pending')->count();

        $activeShippers = Shipper::where('restaurant_id', $restaurantId)
            ->where('is_active', true)->count();

        $inProgressBatches = DeliveryBatch::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['dispatched', 'in_progress'])->count();

        $deliveredToday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'delivered')->whereDate('delivered_at', $today)->count();

        $failedToday = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'failed')->whereDate('updated_at', $today)->count();

        // Average delivery time: dispatched_at → completed_at for batches completed today
        $avgMinutes = DeliveryBatch::where('restaurant_id', $restaurantId)
            ->where('status', 'completed')->whereDate('completed_at', $today)
            ->whereNotNull('dispatched_at')->get()
            ->avg(fn ($b) => $b->dispatched_at->diffInMinutes($b->completed_at));

        // On-time rate: delivered within estimated_delivery_at
        $onTimeRate = $this->calcOnTimeRate($restaurantId, $today);

        return response()->json([
            'pending_orders'        => $pendingOrders,
            'active_shippers'       => $activeShippers,
            'in_progress_batches'   => $inProgressBatches,
            'delivered_today'       => $deliveredToday,
            'failed_today'          => $failedToday,
            'avg_delivery_minutes'  => $avgMinutes ? (int) round($avgMinutes) : null,
            'on_time_rate'          => $onTimeRate,
        ]);
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    private function calcOnTimeRate(int $restaurantId, \Carbon\Carbon $today): ?float
    {
        $delivered = DeliveryDetail::where('restaurant_id', $restaurantId)
            ->where('delivery_status', 'delivered')
            ->whereDate('delivered_at', $today)
            ->whereNotNull('estimated_delivery_at')
            ->get();

        if ($delivered->isEmpty()) return null;

        $onTime = $delivered->filter(
            fn ($d) => $d->delivered_at->lte($d->estimated_delivery_at->addMinutes(5))
        )->count();

        return round($onTime / $delivered->count() * 100, 1);
    }

    private function clusterReason(array $cluster): string
    {
        $r = $cluster['radius_km'];
        if ($r < 1)  return "Gần nhau (~{$r}km bán kính) — giao nhanh";
        if ($r < 3)  return "Cùng khu vực (~{$r}km bán kính)";
        return "Phân vùng địa lý (~{$r}km bán kính)";
    }

    private function getUnassignedOrders(int $restaurantId): array
    {
        return Order::where('restaurant_id', $restaurantId)
            ->where('channel', 'delivery')
            ->whereIn('status', ['confirmed', 'preparing'])
            ->with('deliveryDetail')
            ->whereHas('deliveryDetail', fn ($q) => $q->where('delivery_status', 'pending'))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'status'       => $o->status,
                'total_amount' => (float) $o->total_amount,
                'created_at'   => $o->created_at->toIso8601String(),
                'delivery'     => $o->deliveryDetail ? [
                    'id'                   => $o->deliveryDetail->id,
                    'customer_name'        => $o->deliveryDetail->customer_name,
                    'phone'                => $o->deliveryDetail->phone,
                    'address'              => $o->deliveryDetail->address,
                    'latitude'             => $o->deliveryDetail->latitude,
                    'longitude'            => $o->deliveryDetail->longitude,
                    'delivery_fee'         => $o->deliveryDetail->delivery_fee,
                    'estimated_distance_km'=> $o->deliveryDetail->estimated_distance_km,
                ] : null,
            ])
            ->toArray();
    }

    private function getActiveShippers(int $restaurantId): array
    {
        return Shipper::where('restaurant_id', $restaurantId)
            ->active()
            ->with(['employee', 'latestLocation', 'activeBatch.items'])
            ->get()
            ->map(fn (Shipper $s) => [
                'id'                  => $s->id,
                'name'                => $s->employee->full_name ?? $s->employee->name ?? '—',
                'vehicle_type'        => $s->vehicle_type,
                'vehicle_plate'       => $s->vehicle_plate,
                'max_orders_per_batch'=> $s->max_orders_per_batch,
                'max_capacity_kg'     => $s->max_capacity_kg,
                'current_load'        => $s->getCurrentLoad(),
                'latitude'            => $s->latestLocation?->latitude,
                'longitude'           => $s->latestLocation?->longitude,
                'last_seen_at'        => $s->latestLocation?->logged_at?->toIso8601String(),
                'active_batch_id'     => $s->activeBatch?->id,
            ])
            ->toArray();
    }

    private function getActiveBatches(int $restaurantId): array
    {
        return DeliveryBatch::where('restaurant_id', $restaurantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['shipper.employee', 'items.deliveryDetail'])
            ->latest()
            ->get()
            ->map(fn (DeliveryBatch $b) => $this->formatBatch($b))
            ->toArray();
    }

    private function formatBatch(DeliveryBatch $batch): array
    {
        $done  = $batch->items->filter(fn ($i) => in_array($i->status, ['delivered', 'failed']))->count();
        $total = $batch->items->count();

        return [
            'id'                         => $batch->id,
            'status'                     => $batch->status,
            'total_orders'               => $batch->total_orders,
            'estimated_distance_km'      => $batch->estimated_distance_km,
            'estimated_duration_minutes' => $batch->estimated_duration_minutes,
            'total_weight_kg'            => $batch->total_weight_kg,
            'dispatched_at'              => $batch->dispatched_at?->toIso8601String(),
            'completed_at'               => $batch->completed_at?->toIso8601String(),
            'created_at'                 => $batch->created_at->toIso8601String(),
            'progress'                   => ['done' => $done, 'total' => $total],
            'shipper'                    => $batch->shipper ? [
                'id'           => $batch->shipper->id,
                'name'         => $batch->shipper->employee->full_name ?? $batch->shipper->employee->name ?? '—',
                'vehicle_type' => $batch->shipper->vehicle_type,
                'vehicle_plate'=> $batch->shipper->vehicle_plate,
            ] : null,
            'optimized_route' => $batch->optimized_route,
            'items'           => $batch->items->map(fn ($item) => [
                'id'             => $item->id,
                'order_id'       => $item->order_id,
                'sequence_order' => $item->sequence_order,
                'status'         => $item->status,
                'picked_up_at'   => $item->picked_up_at?->toIso8601String(),
                'delivered_at'   => $item->delivered_at?->toIso8601String(),
                'address'        => $item->deliveryDetail?->address,
                'customer_name'  => $item->deliveryDetail?->customer_name,
                'phone'          => $item->deliveryDetail?->phone,
                'estimated_delivery_at' => $item->deliveryDetail?->estimated_delivery_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
