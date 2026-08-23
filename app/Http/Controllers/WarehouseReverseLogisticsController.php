<?php

namespace App\Http\Controllers;

use App\Models\InventoryQuarantine;
use App\Models\InventoryReturn;
use App\Models\SupplierClaim;
use App\Models\StockTransferRequest;
use App\Services\WarehouseReverseLogisticsService;
use App\Services\WarehouseStaffAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'canOperate' => $user->isOwner() || $user->isSuperAdmin() || $user->hasAnyRole(['warehouse_manager', 'warehouse_staff', 'manager']) || $user->can('warehouse.manage'),
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
            ->with(['branch:id,name', 'ingredient:id,name', 'batch:id,batch_code,batch_number,expiry_date,status'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
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
            ->with(['items.ingredient', 'items.batch', 'fromBranch:id,name', 'toBranch:id,name', 'supplier:id,name'])
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
            'to_branch_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
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
            foreach ($evidencePaths as $path) Storage::disk('local')->delete($path);
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
        $quarantine = InventoryQuarantine::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id);
        $path = $request->file('evidence')->store('warehouse/quarantine-disposals/'.now()->format('Y/m'), 'local');

        try {
            DB::transaction(function () use ($quarantine, $request, $data, $path): void {
                $locked = InventoryQuarantine::where('restaurant_id', $request->user()->restaurant_id)->lockForUpdate()->findOrFail($quarantine->id);
                if (! in_array($locked->status, ['open', 'return_requested'], true)) {
                    throw new \InvalidArgumentException('Lô cách ly đã được xử lý.');
                }
                if ($locked->batch) {
                    $locked->batch->update(['quantity_remaining' => 0, 'status' => 'depleted']);
                }
                $locked->update([
                    'status' => 'destroyed',
                    'disposition' => 'destroyed',
                    'disposition_reason' => $data['reason'],
                    'evidence_paths' => array_values(array_filter(array_merge($locked->evidence_paths ?? [], [$path]))),
                    'resolved_by' => $request->user()->id,
                    'resolved_at' => now(),
                ]);
                if ($locked->source_type === 'stock_transfer' && $locked->source_id) {
                    StockTransferRequest::where('restaurant_id', $request->user()->restaurant_id)
                        ->whereKey($locked->source_id)
                        ->update(['status' => 'destroyed', 'disposition' => 'destroyed', 'disposition_notes' => $data['reason'], 'disposition_evidence_path' => $path, 'disposition_by' => $request->user()->id, 'disposition_at' => now()]);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã ghi nhận tiêu hủy lô cách ly.']);
    }

    public function approveReturn(Request $request, int $id): JsonResponse
    {
        $this->assertOperate($request);
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
            'disposition' => ['required', 'in:central_quarantine,destroyed,supplier_confirmed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $return = $this->service->completeReturn(
                InventoryReturn::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id),
                $request->user(),
                $data['disposition'],
                $data['notes'] ?? null,
            );
        } catch (\Throwable $e) {
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
            'supplier_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'max:60'],
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
        $claim = $this->service->createClaim($request->user(), $data, $paths);

        return response()->json(['message' => 'Đã lập hồ sơ khiếu nại nhà cung cấp/vận chuyển.', 'claim' => $claim], 201);
    }

    public function resolveClaim(Request $request, int $id): JsonResponse
    {
        $this->assertOperate($request);
        $data = $request->validate(['response_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $claim = SupplierClaim::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id);
        $claim->update(['status' => 'resolved', 'response_notes' => $data['response_notes'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

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
}
