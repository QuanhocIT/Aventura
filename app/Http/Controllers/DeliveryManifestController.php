<?php

namespace App\Http\Controllers;

use App\Models\DeliveryManifest;
use App\Services\DeliveryManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\SupplyRequest;
use App\Services\CentralWarehouseService;

class DeliveryManifestController extends Controller
{
    public function __construct(
        protected DeliveryManifestService $manifestService,
        protected CentralWarehouseService $warehouseService
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $manifests = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['items.supplyRequest.toBranch', 'creator', 'dispatchedBy'])
            ->orderBy('id', 'desc')
            ->get();

        $approvedRequests = SupplyRequest::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_DISPATCH_PENDING)
            ->with(['toBranch', 'items.ingredient'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('inventory/DeliveryManifests', [
            'manifests'        => $manifests,
            'approvedRequests' => $approvedRequests,
            'canCreateManifest' => $this->canCreateManifest($user),
            'canDispatchManifest' => $this->canDispatchManifest($user),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $manifests = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['items.supplyRequest.toBranch', 'creator', 'dispatchedBy'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['manifests' => $manifests]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManifestCreate($user);

        $validated = $request->validate([
            'route_name'             => 'nullable|string|max:150',
            'driver_name'            => 'nullable|string|max:150',
            'driver_phone'           => 'nullable|string|max:50',
            'vehicle_number'         => 'nullable|string|max:50',
            'seal_code'              => 'nullable|string|max:50',
            'scheduled_dispatch_at'  => 'nullable|date',
            'notes'                  => 'nullable|string',
            'supply_request_ids'     => 'required|array|min:1',
            'supply_request_ids.*'   => ['integer', 'distinct'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');

        $manifest = $this->manifestService->createManifest($user->restaurant_id, (int) $centralBranch->id, $validated, $user);

        return response()->json([
            'message'  => 'Tạo chuyến xe giao hàng thành công.',
            'manifest' => $manifest,
        ]);
    }

    public function packingList(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->findOrFail($id);

        $packingList = $this->manifestService->getMasterPackingList($manifest);

        return response()->json([
            'manifest'     => $manifest,
            'packing_list' => $packingList,
        ]);
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManifestDispatch($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->findOrFail($id);

        $validated = $request->validate([
            'seal_code' => 'nullable|string|max:50',
        ]);

        $dispatched = $this->manifestService->dispatchManifest($manifest, $user, $validated['seal_code'] ?? null);

        return response()->json([
            'message'  => "Đã xuất bến chuyến xe #{$dispatched->manifest_code}.",
            'manifest' => $dispatched,
        ]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $request->validate(['notes' => 'nullable|string|max:1000']);

        $manifest = DeliveryManifest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $completed = $this->manifestService->completeManifest($manifest, $user, $request->input('notes'));

        return response()->json([
            'message' => 'Đã xác nhận hoàn tất chuyến xe và đối soát đủ hàng.',
            'manifest' => $completed,
        ]);
    }

    private function authorizeWarehouseView($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->hasRole('warehouse_staff') || $user->can('warehouse.view'),
            403,
            'Bạn không có quyền xem chuyến xe Kho Tổng.'
        );
    }

    private function authorizeWarehouseManage($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage') || $user->can('warehouse.handover'),
            403,
            'Bạn không có quyền điều phối chuyến xe Kho Tổng.'
        );
    }

    private function authorizeManifestCreate($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            403,
            'Bạn không có quyền tạo chuyến xe Kho Tổng.'
        );
    }

    private function authorizeManifestDispatch($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage') || $user->can('warehouse.handover'),
            403,
            'Bạn không có quyền xuất bến chuyến xe Kho Tổng.'
        );
    }

    private function canCreateManifest($user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage');
    }

    private function canDispatchManifest($user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage') || $user->can('warehouse.handover');
    }
}
