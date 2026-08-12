<?php

namespace App\Http\Controllers;

use App\Models\DeliveryManifest;
use App\Services\DeliveryManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\SupplyRequest;

class DeliveryManifestController extends Controller
{
    public function __construct(
        protected DeliveryManifestService $manifestService
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $manifests = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->with(['items.supplyRequest.toBranch', 'creator', 'dispatchedBy'])
            ->orderBy('id', 'desc')
            ->get();

        $approvedRequests = SupplyRequest::where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', [SupplyRequest::STATUS_APPROVED, SupplyRequest::STATUS_PREPARING])
            ->with(['toBranch', 'items.ingredient'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('inventory/DeliveryManifests', [
            'manifests'        => $manifests,
            'approvedRequests' => $approvedRequests,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $manifests = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->with(['items.supplyRequest.toBranch', 'creator', 'dispatchedBy'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['manifests' => $manifests]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $fromBranchId = $request->input('from_branch_id', $user->branch_id);

        $validated = $request->validate([
            'route_name'             => 'nullable|string|max:150',
            'driver_name'            => 'nullable|string|max:150',
            'driver_phone'           => 'nullable|string|max:50',
            'vehicle_number'         => 'nullable|string|max:50',
            'seal_code'              => 'nullable|string|max:50',
            'scheduled_dispatch_at'  => 'nullable|date',
            'notes'                  => 'nullable|string',
            'supply_request_ids'     => 'required|array|min:1',
            'supply_request_ids.*'   => 'integer',
        ]);

        $manifest = $this->manifestService->createManifest($user->restaurant_id, (int) $fromBranchId, $validated, $user);

        return response()->json([
            'message'  => 'Tạo chuyến xe giao hàng thành công.',
            'manifest' => $manifest,
        ]);
    }

    public function packingList(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $packingList = $this->manifestService->getMasterPackingList($manifest);

        return response()->json([
            'manifest'     => $manifest,
            'packing_list' => $packingList,
        ]);
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $validated = $request->validate([
            'seal_code' => 'nullable|string|max:50',
        ]);

        $dispatched = $this->manifestService->dispatchManifest($manifest, $user, $validated['seal_code'] ?? null);

        return response()->json([
            'message'  => "Đã xuất bến chuyến xe #{$dispatched->manifest_code}.",
            'manifest' => $dispatched,
        ]);
    }
}
