<?php

namespace App\Http\Controllers;

use App\Models\InventoryQuarantine;
use App\Models\InventoryReturn;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplierClaim;
use App\Services\WarehouseReverseLogisticsService;
use App\Services\WarehouseStaffAccessService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseReverseLogisticsController extends Controller
{
    public function __construct(
        protected WarehouseReverseLogisticsService $service,
        protected WarehouseStaffAccessService $staffAccess,
    ) {}

    public function page(Request $request): Response
    {
        $this->assertView($request);
        $user = $request->user();

        return Inertia::render('inventory/WarehouseReverseLogistics', [
            'canOperate' => $this->canOperate($user),
            'canApprove' => $this->canApprove($user),
            'canComplete' => $this->canApprove($user),
            'canDispose' => $this->canApprove($user),
            'canResolve' => $this->canResolve($user),
            'branches' => RestaurantBranch::where('restaurant_id', $user->restaurant_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'is_central_warehouse']),
            'suppliers' => Supplier::where('restaurant_id', $user->restaurant_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function quarantines(Request $request): JsonResponse
    {
        $this->assertView($request);
        $rows = InventoryQuarantine::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->when($request->user()->hasRole('warehouse_staff'), function ($query) use ($request) {
                $branchId = $this->staffAccess->centralWarehouseFor($request->user())?->id;
                $query->where('branch_id', $branchId ?: -1);
            })
            ->with(['branch:id,name', 'ingredient:id,name', 'batch:id,batch_number,expiry_date,status,quantity_remaining,supplier_id', 'returnItems.returnOrder:id,status'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $statuses = is_array($request->input('status'))
                    ? $request->input('status')
                    : explode(',', (string) $request->input('status'));
                $statuses = array_values(array_intersect($statuses, ['open', 'return_requested', 'returned', 'destroyed']));
                if ($statuses !== []) {
                    $query->whereIn('status', $statuses);
                }
            })
            ->latest('id')
            ->limit(300)
            ->get();

        return response()->json(['quarantines' => $rows]);
    }

    public function returns(Request $request): JsonResponse
    {
        $this->assertView($request);
        $rows = InventoryReturn::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->when($request->user()->hasRole('warehouse_staff'), function ($query) use ($request) {
                $branchId = $this->staffAccess->centralWarehouseFor($request->user())?->id;
                $query->where('from_branch_id', $branchId ?: -1);
            })
            ->with(['items.ingredient', 'items.batch', 'items.quarantine:id,quantity,status', 'fromBranch:id,name', 'toBranch:id,name', 'supplier:id,name', 'createdBy:id,name', 'approvedBy:id,name', 'receivedBy:id,name'])
            ->latest('id')
            ->limit(300)
            ->get();

        return response()->json(['returns' => $rows]);
    }

    public function requestReturn(Request $request, int $id): JsonResponse
    {
        $this->assertOperate($request);
        $data = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'to_branch_id' => ['nullable', 'integer', TenantRule::exists('restaurant_branches')],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $quarantine = InventoryQuarantine::where('restaurant_id', $request->user()->restaurant_id)
            ->when($request->user()->hasRole('warehouse_staff'), fn ($query) => $query->where('branch_id', $this->staffAccess->centralWarehouseFor($request->user())?->id ?: -1))
            ->findOrFail($id);
        $evidencePaths = [];
        if ($request->hasFile('evidence')) {
            $evidencePaths[] = $request->file('evidence')->store('warehouse/returns/'.now()->format('Y/m'), 'local');
        }

        try {
            $return = $this->service->createReturnFromQuarantine($quarantine, $request->user(), [
                ...$data,
                'evidence_paths' => $evidencePaths,
            ]);
        } catch (\Throwable $e) {
            foreach ($evidencePaths as $path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã lập phiếu hoàn trả và đưa lô vào trạng thái chờ duyệt.', 'return' => $return], 201);
    }

    public function destroyQuarantine(Request $request, int $id): JsonResponse
    {
        $this->assertOperate($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $quarantine = InventoryQuarantine::where('restaurant_id', $request->user()->restaurant_id)
            ->when($request->user()->hasRole('warehouse_staff'), fn ($query) => $query->where('branch_id', $this->staffAccess->centralWarehouseFor($request->user())?->id ?: -1))
            ->findOrFail($id);
        $path = $request->file('evidence')->store('warehouse/quarantine-disposals/'.now()->format('Y/m'), 'local');

        try {
            $this->service->destroyQuarantine($quarantine, $request->user(), $data['reason'], [$path]);

            return response()->json(['message' => 'Đã ghi nhận tiêu hủy lô cách ly.']);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => $e->getMessage()], 422);
        }

    }

    public function approveReturn(Request $request, int $id): JsonResponse
    {
        $this->assertApprove($request);
        try {
            $return = $this->service->approveReturn(
                InventoryReturn::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã duyệt hoàn trả và ghi nhận xuất khỏi tồn khả dụng.', 'return' => $return]);
    }

    public function completeReturn(Request $request, int $id): JsonResponse
    {
        $this->assertOperate($request);
        $data = $request->validate([
            'disposition' => ['required', 'in:central_quarantine,sender_quarantine,destroyed,supplier_confirmed'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $evidencePaths = [];
        if ($request->hasFile('evidence')) {
            $evidencePaths[] = $request->file('evidence')->store('warehouse/return-dispositions/'.now()->format('Y/m'), 'local');
        }

        try {
            $return = $this->service->completeReturn(
                InventoryReturn::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id),
                $request->user(),
                $data['disposition'],
                $data['notes'] ?? null,
                $evidencePaths,
            );
        } catch (\Throwable $e) {
            foreach ($evidencePaths as $path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã chốt xử lý phiếu hoàn trả.', 'return' => $return]);
    }

    public function claims(Request $request): JsonResponse
    {
        $this->assertView($request);

        return response()->json([
            'claims' => SupplierClaim::where('restaurant_id', $request->user()->restaurant_id)
                ->with(['supplier:id,name', 'createdBy:id,name', 'resolvedBy:id,name'])
                ->latest('id')->limit(300)->get(),
        ]);
    }

    public function storeClaim(Request $request): JsonResponse
    {
        $this->assertOperate($request);
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'source_type' => ['nullable', 'in:inventory_return,inventory_quarantine,stock_transfer,supply_request,warehouse_receiving_voucher'],
            'source_id' => ['nullable', 'integer'],
            'carrier_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'loss_amount' => ['nullable', 'numeric', 'min:0'],
            'requested_action' => ['nullable', 'in:replacement,credit,refund,penalty,investigate'],
            'due_at' => ['nullable', 'date'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $paths = [];
        foreach ($request->file('evidence', []) as $file) {
            $paths[] = $file->store('warehouse/claims/'.now()->format('Y/m'), 'local');
        }
        try {
            $claim = $this->service->createClaim($request->user(), $data, $paths);
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã lập hồ sơ khiếu nại nhà cung cấp/vận chuyển.', 'claim' => $claim], 201);
    }

    public function resolveClaim(Request $request, int $id): JsonResponse
    {
        $this->assertResolve($request);
        $data = $request->validate(['response_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        try {
            $claim = $this->service->resolveClaim(
                SupplierClaim::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id),
                $request->user(),
                $data['response_notes'],
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã đóng hồ sơ khiếu nại.', 'claim' => $claim->fresh()]);
    }

    private function assertView(Request $request): void
    {
        $user = $request->user();
        $this->staffAccess->assertCanAccessCentral($user);
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff', 'manager']) || $user->can('warehouse.view'), 403);
    }

    private function assertOperate(Request $request): void
    {
        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff', 'manager']) || $user->can('warehouse.manage'), 403);
    }

    private function assertApprove(Request $request): void
    {
        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        abort_unless($this->canApprove($user), 403, 'Chỉ quản lý kho, quản lý chi nhánh hoặc chủ nhà hàng được duyệt phiếu hoàn trả.');
    }

    private function assertResolve(Request $request): void
    {
        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        abort_unless($this->canResolve($user), 403, 'Bạn không có quyền đóng hồ sơ khiếu nại.');
    }

    private function canOperate($user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin() || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff', 'manager']) || $user->can('warehouse.manage');
    }

    private function canApprove($user): bool
    {
        return $user->isOwner() || $user->isSuperAdmin() || $user->hasAnyRole(['warehouse_manager', 'manager']) || $user->can('warehouse.manage');
    }

    private function canResolve($user): bool
    {
        return $this->canApprove($user);
    }
}
