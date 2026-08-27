<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\User;
use App\Notifications\StockTransferStageNotification;
use App\Services\WarehouseReverseLogisticsService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Điều chuyển hàng liên chi nhánh theo chu trình:
 * yêu cầu → định tuyến → xuất kho → nhận hàng → đối soát.
 *
 * Việc trừ kho chỉ xảy ra khi xuất, việc cộng kho chỉ xảy ra khi nhận.
 * Mọi bước thay đổi tồn kho đều khóa bản ghi điều chuyển và tồn kho trong
 * cùng một transaction để tránh xuất/nhận trùng khi người dùng bấm lại.
 */
class StockTransferRequestController extends Controller
{
    private function isRequestOnly(User $user): bool
    {
        return $user->isBranchManager();
    }

    private function assertManager(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'manager', 'warehouse_manager']),
            403,
            'Bạn không có quyền thao tác điều chuyển.',
        );
    }

    private function assertCanRoute(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']),
            403,
            'Chỉ Chủ hoặc Trưởng kho Tổng được định tuyến điều chuyển.',
        );
    }

    /**
     * Quản lý chi nhánh chỉ tạo và theo dõi yêu cầu. Việc xuất/nhận làm thay
     * đổi tồn kho phải do Chủ hoặc người được phân công ở Kho Tổng thực hiện.
     */
    private function assertCanExecute(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']),
            403,
            'Quản lý chi nhánh chỉ được gửi yêu cầu; không được trực tiếp xuất hoặc nhập kho điều chuyển.',
        );
    }

    private function assertTenantTransfer(User $user, StockTransferRequest $transfer): void
    {
        abort_if((int) $transfer->restaurant_id !== (int) $user->restaurant_id, 403);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->assertManager($user);
        $canViewAllBranches = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        $requestOnly = $this->isRequestOnly($user);
        $assignedBranchId = $user->assignedBranchId();

        $transfers = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when($requestOnly, fn ($query) => $query->where('requested_by', $user->id))
            ->when(! $canViewAllBranches, function ($query) use ($user): void {
                $query->where(function ($branchQuery) use ($user): void {
                    $branchQuery->where('to_branch_id', $user->assignedBranchId())
                        ->orWhere('from_branch_id', $user->assignedBranchId());
                });
            })
            ->with([
                'toBranch:id,name',
                'fromBranch:id,name',
                'ingredient:id,name,unit_id',
                'ingredient.unit:id,symbol',
                'requestedBy:id,name',
                'routedBy:id,name',
                'dispatchedBy:id,name',
                'receivedBy:id,name',
                'discrepancyResolvedBy:id,name',
            ])
            ->when(
                Schema::hasColumn('stock_transfer_requests', 'priority'),
                fn ($query) => $query->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END"),
            )
            ->latest('id')
            ->limit(300)
            ->get();

        $stocks = Inventory::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->get(['branch_id', 'ingredient_id', 'quantity_on_hand', 'last_cost'])
            ->keyBy(fn (Inventory $inventory): string => $inventory->branch_id.':'.$inventory->ingredient_id);

        $canRoute = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        $canExecute = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        $transfers = $transfers->map(function (StockTransferRequest $transfer) use ($user, $stocks, $canRoute, $canExecute, $requestOnly): array {
            $stock = $transfer->from_branch_id
                ? $stocks->get($transfer->from_branch_id.':'.$transfer->ingredient_id)
                : null;
            $isRequester = (int) $transfer->requested_by === (int) $user->id;

            $requestData = [
                'id' => $transfer->id,
                'status' => $transfer->status,
                'priority' => $transfer->priority ?? 'normal',
                'ingredient_id' => $transfer->ingredient_id,
                'ingredient' => $transfer->ingredient?->name,
                'unit' => $transfer->ingredient?->unit?->symbol ?? 'đơn vị',
                'to_branch_id' => $transfer->to_branch_id,
                'to_branch' => $transfer->toBranch?->name,
                'quantity_requested' => (float) $transfer->quantity_requested,
                'quantity_dispatched' => $transfer->quantity_dispatched !== null ? (float) $transfer->quantity_dispatched : null,
                'quantity_received' => $transfer->quantity_received !== null ? (float) $transfer->quantity_received : null,
                'reason' => $transfer->reason,
                'owner_note' => $transfer->owner_note,
                'reject_reason' => $transfer->reject_reason,
                'cancel_reason' => $transfer->cancel_reason,
                'created_at' => $transfer->created_at?->format('d/m/Y H:i'),
                'routed_at' => $transfer->routed_at?->format('d/m/Y H:i'),
                'dispatched_at' => $transfer->dispatched_at?->format('d/m/Y H:i'),
                'received_at' => $transfer->received_at?->format('d/m/Y H:i'),
                'requested_by' => $transfer->requestedBy?->name,
                'can_cancel' => in_array($transfer->status, ['requested', 'routed'], true)
                    && ($canRoute || ($requestOnly && $isRequester && $transfer->status === 'requested')),
            ];

            if ($requestOnly) {
                return $requestData;
            }

            return $requestData + [
                'from_branch_id' => $transfer->from_branch_id,
                'from_branch' => $transfer->fromBranch?->name,
                'quantity_remaining' => max(0, (float) $transfer->quantity_requested - (float) ($transfer->quantity_received ?? 0)),
                'discrepancy_quantity' => (float) ($transfer->discrepancy_quantity ?? 0),
                'source_available_quantity' => $stock ? (float) $stock->quantity_on_hand : 0,
                'source_unit_cost' => $stock ? (float) $stock->last_cost : 0,
                'dispatch_note' => $transfer->dispatch_note,
                'received_condition' => $transfer->received_condition,
                'quantity_received_good' => $transfer->quantity_received_good !== null ? (float) $transfer->quantity_received_good : null,
                'quantity_received_damaged' => (float) ($transfer->quantity_received_damaged ?? 0),
                'quantity_received_expired' => (float) ($transfer->quantity_received_expired ?? 0),
                'source_batch_id' => $transfer->source_batch_id,
                'destination_batch_id' => $transfer->destination_batch_id,
                'quarantine_id' => $transfer->quarantine_id,
                'transport_temperature_min_c' => $transfer->transport_temperature_min_c,
                'transport_temperature_max_c' => $transfer->transport_temperature_max_c,
                'carrier_name' => $transfer->carrier_name,
                'vehicle_number' => $transfer->vehicle_number,
                'received_note' => $transfer->received_note,
                'receiving_evidence_path' => $transfer->receiving_evidence_path,
                'discrepancy_reason' => $transfer->discrepancy_reason,
                'discrepancy_resolution' => $transfer->discrepancy_resolution,
                'handover_code' => $transfer->handover_code,
                'routed_by' => $transfer->routedBy?->name,
                'dispatched_by' => $transfer->dispatchedBy?->name,
                'received_by' => $transfer->receivedBy?->name,
                'discrepancy_resolved_by' => $transfer->discrepancyResolvedBy?->name,
                'can_route' => $canRoute && $transfer->status === 'requested',
                'can_dispatch' => $canExecute
                    && $transfer->status === 'routed'
                    && $transfer->from_branch_id
                    && $user->canAccessBranch((int) $transfer->from_branch_id),
                'can_receive' => $canExecute
                    && $transfer->status === 'dispatched'
                    && $transfer->to_branch_id
                    && $user->canAccessBranch((int) $transfer->to_branch_id)
                    && (int) $transfer->dispatched_by !== (int) $user->id,
                'can_resolve' => $canRoute && $transfer->status === 'discrepancy',
            ];
        });

        $branches = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when(! $canViewAllBranches, fn ($query) => $query->where('id', $assignedBranchId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchStock = $requestOnly
            ? []
            : $stocks->groupBy('branch_id')
                ->map(fn ($branchItems): array => $branchItems
                    ->mapWithKeys(fn (Inventory $inventory): array => [
                        (string) $inventory->ingredient_id => (float) $inventory->quantity_on_hand,
                    ])
                    ->all())
                ->all();

        $ingredients = Ingredient::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->when($requestOnly, function ($query) use ($assignedBranchId): void {
                $query->where(function ($branchQuery) use ($assignedBranchId): void {
                    $branchQuery->whereNull('branch_id')
                        ->orWhere('branch_id', $assignedBranchId);
                });
            })
            ->with('unit:id,symbol')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id', 'unit_id'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'branch_id' => $ingredient->branch_id,
                'unit' => $ingredient->unit?->symbol ?? 'đơn vị',
                'available_quantity' => $requestOnly && $assignedBranchId
                    ? (float) ($stocks->get($assignedBranchId.':'.$ingredient->id)?->quantity_on_hand ?? 0)
                    : null,
            ]);

        $page = $requestOnly ? 'inventory/TransferRequests' : 'inventory/Transfers';

        return Inertia::render($page, [
            'transfers' => $transfers->values(),
            'branches' => $branches,
            'branch_stock' => $branchStock,
            'ingredients' => $ingredients->values(),
            'permissions' => [
                'can_route' => $canRoute,
                'can_execute' => $canExecute,
                'can_create' => true,
                'request_only' => $requestOnly,
            ],
            'summary' => [
                'requested' => $transfers->where('status', 'requested')->count(),
                'routed' => $transfers->where('status', 'routed')->count(),
                'dispatched' => $transfers->where('status', 'dispatched')->count(),
                'discrepancy' => $transfers->where('status', 'discrepancy')->count(),
                'completed' => $transfers->where('status', 'received')->count(),
            ],
        ]);
    }

    /** Chi nhánh thiếu tạo yêu cầu điều chuyển. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $requestOnly = $this->isRequestOnly($user);
        $assignedBranchId = $user->assignedBranchId();

        $data = $request->validate([
            'to_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'ingredient_id' => ['required', TenantRule::exists('ingredients')],
            'quantity_requested' => ['required', 'numeric', 'min:0.001'],
            'priority' => ['sometimes', 'required', 'string', 'in:normal,urgent'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $data['idempotency_key'] = $data['idempotency_key'] ?? $request->header('Idempotency-Key');

        $toBranch = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail((int) $data['to_branch_id']);
        abort_unless($user->canAccessBranch($toBranch->id), 403, 'Bạn chỉ được yêu cầu cho chi nhánh thuộc phạm vi của mình.');
        abort_unless(
            ! $requestOnly || ($assignedBranchId !== null && (int) $toBranch->id === (int) $assignedBranchId),
            403,
            'Yêu cầu phải được lập cho đúng chi nhánh đang được phân công.',
        );

        if (! empty($data['idempotency_key'])) {
            $existing = StockTransferRequest::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return back()->with('success', 'YÃªu cáº§u Ä‘iá»u chuyá»ƒn Ä‘Ã£ Ä‘Æ°á»£c ghi nháº­n trÆ°á»›c Ä‘Ã³.');
            }
        }

        $ingredient = Ingredient::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->findOrFail((int) $data['ingredient_id']);
        if ($ingredient->branch_id !== null && (int) $ingredient->branch_id !== $toBranch->id) {
            return back()->withErrors(['ingredient_id' => 'Nguyên liệu này chỉ được dùng tại chi nhánh khác.']);
        }

        $hasOpenRequest = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('to_branch_id', $toBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->whereIn('status', ['requested', 'routed', 'dispatched', 'discrepancy', 'quarantined', 'return_requested'])
            ->exists();
        if ($hasOpenRequest) {
            return back()->withErrors(['ingredient_id' => 'Chi nhánh này đã có yêu cầu cùng nguyên liệu đang xử lý. Hãy theo dõi yêu cầu hiện tại hoặc ghi rõ nhu cầu bổ sung.']);
        }

        $transfer = StockTransferRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'to_branch_id' => $toBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity_requested' => $data['quantity_requested'],
            'priority' => $data['priority'] ?? ($requestOnly ? 'urgent' : 'normal'),
            'reason' => trim($data['reason']),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'status' => 'requested',
            'requested_by' => $user->id,
        ]);

        $this->notifyTransferParties($user, $transfer, 'requested');
        AuditLog::log('stock_transfer_requested', 'created', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã tạo yêu cầu điều chuyển và gửi vào hàng chờ định tuyến.');
    }

    /** Chủ hoặc Trưởng kho Tổng chọn nguồn cấp và duyệt định tuyến. */
    public function route(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $lockedTransfer = DB::transaction(function () use ($data, $transfer, $user): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'requested') {
                    throw new \RuntimeException('Yêu cầu này đã được định tuyến hoặc không còn chờ xử lý.');
                }

                $fromBranch = RestaurantBranch::query()
                    ->where('restaurant_id', $user->restaurant_id)
                    ->where('status', 'active')
                    ->findOrFail((int) $data['from_branch_id']);
                if ($fromBranch->id === (int) $lockedTransfer->to_branch_id) {
                    throw new \InvalidArgumentException('Chi nhánh cấp phải khác chi nhánh nhận.');
                }

                $sourceInventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $fromBranch->id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                if (! $sourceInventory || (float) $sourceInventory->quantity_available + 0.0005 < (float) $lockedTransfer->quantity_requested) {
                    throw new \RuntimeException('Kho nguồn không đủ tồn thực tế cho số lượng yêu cầu. Hãy chọn kho khác hoặc yêu cầu lại số lượng.');
                }

                $activeBatchQuantity = (float) InventoryBatch::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $fromBranch->id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->where('status', 'active')
                    ->where(function ($query): void {
                        $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
                    })
                    ->sum('quantity_remaining');
                $trackedBatchQuantity = (float) InventoryBatch::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $fromBranch->id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->whereIn('status', ['active', 'expired'])
                    ->sum('quantity_remaining');
                $unallocatedQuantity = max(0, (float) $sourceInventory->quantity_on_hand - $trackedBatchQuantity);
                $allocatableBatchQuantity = $activeBatchQuantity + $unallocatedQuantity;
                if ($allocatableBatchQuantity + 0.0005 < (float) $lockedTransfer->quantity_requested) {
                    throw new \RuntimeException('Các lô khả dụng tại kho nguồn không đủ số lượng yêu cầu. Hãy chọn kho khác hoặc kiểm tra lại tồn theo lô.');
                }

                $lockedTransfer->update([
                    'from_branch_id' => $fromBranch->id,
                    'owner_note' => isset($data['owner_note']) ? trim((string) $data['owner_note']) : null,
                    'status' => 'routed',
                    'routed_by' => $user->id,
                    'routed_at' => now(),
                    'handover_code' => strtoupper(Str::random(6)),
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['from_branch_id' => $e->getMessage()]);
        }

        $this->notifyTransferParties($user, $lockedTransfer, 'routed');
        AuditLog::log('stock_transfer_routed', 'updated', $lockedTransfer, null, ['from_branch_id' => $lockedTransfer->from_branch_id, 'by' => $user->name]);

        return back()->with('success', 'Đã định tuyến nguồn cấp và sinh mã giao nhận.');
    }

    /** Chi nhánh cấp xuất đủ số lượng yêu cầu, ghi giảm tồn nguồn. */
    public function dispatch(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanExecute($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'quantity_dispatched' => ['required', 'numeric', 'min:0.001'],
            'dispatch_note' => ['nullable', 'string', 'max:500'],
        ]);
        $qty = (float) $data['quantity_dispatched'];

        if (! $transfer->from_branch_id || ! $user->canAccessBranch((int) $transfer->from_branch_id)) {
            abort(403, 'Bạn không thuộc chi nhánh cấp hàng.');
        }

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $qty, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'routed') {
                    throw new \RuntimeException('Yêu cầu chưa được định tuyến hoặc đã xuất hàng.');
                }
                if (abs($qty - (float) $lockedTransfer->quantity_requested) > 0.0005) {
                    throw new \InvalidArgumentException('Phiên bản hiện tại yêu cầu xuất đủ số lượng đã yêu cầu. Nếu cần chia nhiều chuyến, hãy tạo yêu cầu bổ sung riêng.');
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $lockedTransfer->from_branch_id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                if (! $inventory || (float) $inventory->quantity_available + 0.0005 < $qty) {
                    throw new \RuntimeException('Chi nhánh cấp không đủ tồn thực tế để xuất hàng.');
                }

                $inventoryService = app(\App\Services\InventoryService::class);
                $inventoryService->ensureLegacyBatchForInventory($inventory);
                $ingredient = Ingredient::withoutGlobalScopes()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->findOrFail($lockedTransfer->ingredient_id);
                $batchConsumption = $inventoryService->allocateBatchesForTransfer(
                    (int) $lockedTransfer->restaurant_id,
                    (int) $lockedTransfer->from_branch_id,
                    (int) $lockedTransfer->ingredient_id,
                    $qty,
                    $ingredient->name,
                );
                if ((float) $batchConsumption['shortage_quantity'] > 0.0005) {
                    throw new \RuntimeException('FEFO batches do not contain enough transferable stock.');
                }
                $unitCost = $qty > 0 && (float) $batchConsumption['total_cost'] > 0
                    ? (float) $batchConsumption['total_cost'] / $qty
                    : (float) $inventory->last_cost;
                $sourceBatch = ! empty($batchConsumption['allocations'])
                    ? InventoryBatch::withoutGlobalScopes()->find($batchConsumption['allocations'][0]['batch_id'])
                    : null;
                if (! $sourceBatch) {
                    // Legacy inventory may not have a batch. Create a traceable
                    // opening batch for the exact quantity being transferred.
                    if (empty($batchConsumption['allocations'])) {
                        $legacyCode = 'LEGACY-TR-'.$lockedTransfer->id;
                        $sourceBatch = InventoryBatch::create([
                            'restaurant_id' => $lockedTransfer->restaurant_id,
                            'branch_id' => $lockedTransfer->from_branch_id,
                            'ingredient_id' => $lockedTransfer->ingredient_id,
                            'batch_number' => $legacyCode,
                            'quantity_remaining' => $qty,
                            'unit_cost' => $unitCost,
                            'purchased_at' => now()->toDateString(),
                            'status' => 'active',
                        ]);
                    } else {
                        throw new \RuntimeException('Lô FEFO tại chi nhánh cấp không đủ tồn để điều chuyển.');
                    }
                }
                $before = (float) $inventory->quantity_on_hand;
                $after = round($before - $qty, 3);
                $outTransaction = InventoryTransaction::createWithIdempotency([
                    'restaurant_id' => $lockedTransfer->restaurant_id,
                    'branch_id' => $lockedTransfer->from_branch_id,
                    'ingredient_id' => $lockedTransfer->ingredient_id,
                    'inventory_id' => $inventory->id,
                    'performed_by' => $user->id,
                    'type' => 'adjustment',
                    'direction' => 'out',
                    'quantity' => $qty,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $unitCost,
                    'total_cost' => $qty * $unitCost,
                    'source_type' => 'stock_transfer',
                    'source_id' => $lockedTransfer->id,
                    'idempotency_key' => 'stock_transfer_out_'.$lockedTransfer->id,
                    'reference_code' => 'TR-'.$lockedTransfer->id.'-OUT',
                    'notes' => 'Điều chuyển #'.$lockedTransfer->id.' sang '.$lockedTransfer->to_branch_id.' (mã '.$lockedTransfer->handover_code.')',
                    'occurred_at' => now(),
                ]);

                $inventory->update([
                    'quantity_on_hand' => $after,
                    'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $qty),
                    'updated_by' => $user->id,
                ]);
                foreach ($batchConsumption['allocations'] as $allocation) {
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $lockedTransfer->restaurant_id,
                        'branch_id' => $lockedTransfer->from_branch_id,
                        'inventory_batch_id' => $allocation['batch_id'],
                        'inventory_transaction_id' => $outTransaction->id,
                        'direction' => 'out',
                        'quantity' => $allocation['quantity'],
                        'unit_cost' => $allocation['unit_cost'],
                    ]);
                }
                $lockedTransfer->update([
                    'status' => 'dispatched',
                    'quantity_dispatched' => $qty,
                    'dispatch_unit_cost' => $unitCost,
                    'dispatched_by' => $user->id,
                    'dispatched_at' => now(),
                    'dispatch_note' => isset($data['dispatch_note']) ? trim((string) $data['dispatch_note']) : null,
                    'source_batch_id' => $sourceBatch?->id,
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xuất hàng: '.$e->getMessage());
        }

        $this->notifyTransferParties($user, $lockedTransfer, 'dispatched');
        app(WarehouseReverseLogisticsService::class)->recordShipmentEvent(
            (int) $lockedTransfer->restaurant_id,
            'stock_transfer',
            (int) $lockedTransfer->id,
            'dispatched',
            $user,
            ['branch_id' => $lockedTransfer->from_branch_id, 'notes' => $lockedTransfer->dispatch_note],
        );
        AuditLog::log('stock_transfer_dispatched', 'updated', $lockedTransfer, null, ['quantity' => $qty, 'by' => $user->name]);

        return back()->with('success', 'Đã xuất kho. Chi nhánh nhận cần kiểm đếm và nhập mã giao nhận.');
    }

    /** Chi nhánh nhận xác nhận số lượng thực nhận, có thể phát sinh chênh lệch. */
    public function receive(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanExecute($user);
        $this->assertTenantTransfer($user, $transfer);

        $data = $request->validate([
            'handover_code' => ['required', 'string', 'size:6'],
            'quantity_received' => ['nullable', 'numeric', 'min:0'],
            'quantity_received_good' => ['nullable', 'numeric', 'min:0'],
            'quantity_received_damaged' => ['nullable', 'numeric', 'min:0'],
            'quantity_received_expired' => ['nullable', 'numeric', 'min:0'],
            'received_condition' => ['nullable', 'string', 'in:good,damaged,shortage,mixed'],
            'received_note' => ['nullable', 'string', 'max:1000'],
            'transport_temperature_min_c' => ['nullable', 'numeric'],
            'transport_temperature_max_c' => ['nullable', 'numeric'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'carrier_name' => ['nullable', 'string', 'max:150'],
            'receiving_evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if (! $transfer->to_branch_id || ! $user->canAccessBranch((int) $transfer->to_branch_id)) {
            abort(403, 'Bạn không thuộc chi nhánh nhận hàng.');
        }

        $hasBreakdown = array_key_exists('quantity_received_good', $data)
            || array_key_exists('quantity_received_damaged', $data)
            || array_key_exists('quantity_received_expired', $data);
        $dispatchedQuantity = (float) $transfer->quantity_dispatched;
        if (! $hasBreakdown) {
            $data['quantity_received_good'] = array_key_exists('quantity_received', $data) && $data['quantity_received'] !== null
                ? (float) $data['quantity_received']
                : $dispatchedQuantity;
        }
        $data['quantity_received_damaged'] = (float) ($data['quantity_received_damaged'] ?? 0);
        $data['quantity_received_expired'] = (float) ($data['quantity_received_expired'] ?? 0);
        $breakdownTotal = round(
            (float) $data['quantity_received_good']
            + (float) $data['quantity_received_damaged']
            + (float) $data['quantity_received_expired'],
            3,
        );
        if (array_key_exists('quantity_received', $data) && $data['quantity_received'] !== null && abs($breakdownTotal - (float) $data['quantity_received']) > 0.0005) {
            return back()->withErrors(['quantity_received' => 'Tổng số lượng tốt/hỏng/hết hạn phải bằng số lượng thực nhận.']);
        }
        $data['quantity_received'] = $breakdownTotal;

        $evidencePath = null;
        if ($request->hasFile('receiving_evidence')) {
            $evidencePath = $request->file('receiving_evidence')->store('warehouse/stock-transfers/'.$user->restaurant_id, 'local');
        }
        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $data, $evidencePath): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'dispatched') {
                    throw new \RuntimeException('Hàng chưa được xuất hoặc đã được nhận trước đó.');
                }
                if (strtoupper(trim((string) $data['handover_code'])) !== strtoupper((string) $lockedTransfer->handover_code)) {
                    throw new \InvalidArgumentException('Mã giao nhận không đúng.');
                }
                if ((int) $lockedTransfer->dispatched_by === (int) $user->id) {
                    throw new \InvalidArgumentException('Người nhận phải khác người xuất hàng.');
                }

                $dispatchedQty = (float) $lockedTransfer->quantity_dispatched;
                $receivedQty = (float) $data['quantity_received'];
                $goodQty = (float) $data['quantity_received_good'];
                $damagedQty = (float) $data['quantity_received_damaged'];
                $expiredQty = (float) $data['quantity_received_expired'];
                $receivedCondition = $data['received_condition'] ?? 'good';
                if ($receivedQty > $dispatchedQty + 0.0005) {
                    throw new \InvalidArgumentException('Số lượng thực nhận không được lớn hơn số lượng đã xuất.');
                }
                $difference = round($dispatchedQty - $receivedQty, 3);
                $badQty = $damagedQty + $expiredQty;
                if (($difference > 0 || $badQty > 0) && mb_strlen(trim((string) ($data['received_note'] ?? ''))) < 5) {
                    throw new \InvalidArgumentException('Khi nhận thiếu hoặc hỏng, bắt buộc ghi rõ biên bản chênh lệch.');
                }
                if (($difference > 0 || $badQty > 0 || $receivedCondition !== 'good') && ! $evidencePath) {
                    throw new \InvalidArgumentException('Khi nhận thiếu hoặc hàng không đạt, bắt buộc đính kèm ảnh/PDF bằng chứng.');
                }
                if (abs($goodQty + $badQty - $receivedQty) > 0.0005) {
                    throw new \InvalidArgumentException('Tổng số lượng tốt/hỏng/hết hạn không khớp số lượng thực nhận.');
                }

                $unitCost = (float) ($lockedTransfer->dispatch_unit_cost ?? 0);
                $reverseLogistics = app(WarehouseReverseLogisticsService::class);
                $destinationBatchId = null;
                if ($goodQty > 0) {
                    $inventory = Inventory::query()
                        ->where('restaurant_id', $lockedTransfer->restaurant_id)
                        ->where('branch_id', $lockedTransfer->to_branch_id)
                        ->where('ingredient_id', $lockedTransfer->ingredient_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $inventory) {
                        $inventory = Inventory::create([
                            'restaurant_id' => $lockedTransfer->restaurant_id,
                            'branch_id' => $lockedTransfer->to_branch_id,
                            'ingredient_id' => $lockedTransfer->ingredient_id,
                            'quantity_on_hand' => 0,
                            'theoretical_quantity' => 0,
                            'last_cost' => $unitCost,
                        ]);
                        $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
                    }
                    $unitCost = $unitCost > 0 ? $unitCost : (float) $inventory->last_cost;
                    $before = (float) $inventory->quantity_on_hand;
                    $after = round($before + $goodQty, 3);
                    $transaction = InventoryTransaction::createWithIdempotency([
                        'restaurant_id' => $lockedTransfer->restaurant_id,
                        'branch_id' => $lockedTransfer->to_branch_id,
                        'ingredient_id' => $lockedTransfer->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $user->id,
                        'type' => 'adjustment',
                        'direction' => 'in',
                        'quantity' => $goodQty,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'unit_cost' => $unitCost,
                        'total_cost' => $goodQty * $unitCost,
                        'source_type' => 'stock_transfer',
                        'source_id' => $lockedTransfer->id,
                        'idempotency_key' => 'stock_transfer_in_'.$lockedTransfer->id,
                        'reference_code' => 'TR-'.$lockedTransfer->id.'-IN',
                        'notes' => 'Điều chuyển #'.$lockedTransfer->id.' nhận từ '.$lockedTransfer->from_branch_id.' (mã '.$lockedTransfer->handover_code.')',
                        'occurred_at' => now(),
                    ]);

                    $inventory->update([
                        'quantity_on_hand' => $after,
                        'theoretical_quantity' => (float) $inventory->theoretical_quantity + $goodQty,
                        'last_cost' => $unitCost,
                        'updated_by' => $user->id,
                    ]);
                    $sourceBatch = $lockedTransfer->source_batch_id
                        ? InventoryBatch::query()->where('restaurant_id', $lockedTransfer->restaurant_id)->whereKey($lockedTransfer->source_batch_id)->first()
                        : null;
                    $destinationBatch = $reverseLogistics->createDestinationBatch(
                        (int) $lockedTransfer->restaurant_id,
                        (int) $lockedTransfer->to_branch_id,
                        (int) $lockedTransfer->ingredient_id,
                        $goodQty,
                        $unitCost,
                        $user,
                        $sourceBatch,
                    );
                    $destinationBatchId = $destinationBatch?->id;
                    if ($destinationBatch) {
                        InventoryBatchAllocation::create([
                            'restaurant_id' => $lockedTransfer->restaurant_id,
                            'branch_id' => $lockedTransfer->to_branch_id,
                            'inventory_batch_id' => $destinationBatch->id,
                            'inventory_transaction_id' => $transaction->id,
                            'direction' => 'in',
                            'quantity' => $goodQty,
                            'unit_cost' => $unitCost,
                        ]);
                    }
                }

                $quarantineId = null;
                if ($badQty > 0) {
                    $sourceBatch = $lockedTransfer->source_batch_id
                        ? InventoryBatch::query()->where('restaurant_id', $lockedTransfer->restaurant_id)->whereKey($lockedTransfer->source_batch_id)->first()
                        : null;
                    $lockedBatch = $reverseLogistics->createDestinationBatch(
                        (int) $lockedTransfer->restaurant_id,
                        (int) $lockedTransfer->to_branch_id,
                        (int) $lockedTransfer->ingredient_id,
                        $badQty,
                        $unitCost,
                        $user,
                        $sourceBatch,
                        true,
                        'Hàng điều chuyển bị hỏng hoặc hết hạn, chờ hoàn trả/tiêu hủy.',
                    );
                    $quarantine = $reverseLogistics->createQuarantine(
                        (int) $lockedTransfer->restaurant_id,
                        (int) $lockedTransfer->to_branch_id,
                        (int) $lockedTransfer->ingredient_id,
                        $badQty,
                        $expiredQty > 0 ? 'expired' : 'damaged',
                        trim((string) ($data['received_note'] ?? 'Hàng không đạt khi nhận điều chuyển.')),
                        $user,
                        $lockedBatch,
                        'stock_transfer',
                        $lockedTransfer->id,
                        null,
                        array_filter([$evidencePath]),
                        $data['received_note'] ?? null,
                    );
                    $quarantineId = $quarantine->id;
                }

                $lossQuantity = round($difference + $badQty, 3);
                $nextStatus = $badQty > 0 ? 'quarantined' : ($difference > 0 ? 'discrepancy' : 'received');
                $lockedTransfer->update([
                    'status' => $nextStatus,
                    'quantity_received' => $receivedQty,
                    'quantity_received_good' => $goodQty,
                    'quantity_received_damaged' => $damagedQty,
                    'quantity_received_expired' => $expiredQty,
                    'discrepancy_quantity' => $lossQuantity,
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'received_condition' => $receivedCondition,
                    'received_note' => isset($data['received_note']) ? trim((string) $data['received_note']) : null,
                    'receiving_evidence_path' => $evidencePath,
                    'discrepancy_reason' => $lossQuantity > 0 ? trim((string) $data['received_note']) : null,
                    'destination_batch_id' => $destinationBatchId,
                    'quarantine_id' => $quarantineId,
                    'transport_temperature_min_c' => $data['transport_temperature_min_c'] ?? null,
                    'transport_temperature_max_c' => $data['transport_temperature_max_c'] ?? null,
                    'vehicle_number' => $data['vehicle_number'] ?? null,
                    'carrier_name' => $data['carrier_name'] ?? null,
                ]);

                $reverseLogistics->recordShipmentEvent(
                    (int) $lockedTransfer->restaurant_id,
                    'stock_transfer',
                    (int) $lockedTransfer->id,
                    $nextStatus === 'received' ? 'received' : 'received_with_discrepancy',
                    $user,
                    [
                        'branch_id' => $lockedTransfer->to_branch_id,
                        'vehicle_number' => $data['vehicle_number'] ?? null,
                        'carrier_name' => $data['carrier_name'] ?? null,
                        'temperature_min_c' => $data['transport_temperature_min_c'] ?? null,
                        'temperature_max_c' => $data['transport_temperature_max_c'] ?? null,
                        'notes' => $data['received_note'] ?? null,
                    ],
                );

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            if ($evidencePath) {
                Storage::disk('local')->delete($evidencePath);
            }

            if ($e instanceof \InvalidArgumentException) {
                $message = $e->getMessage();
                $field = str_contains($message, 'Mã giao nhận') || str_contains($message, 'Người nhận')
                    ? 'handover_code'
                    : (str_contains($message, 'Số lượng') ? 'quantity_received' : 'received_note');

                return back()->withErrors([$field => $message]);
            }

            return back()->with('error', 'Không thể nhận hàng: '.$e->getMessage());
        }

        $stage = in_array($lockedTransfer->status, ['discrepancy', 'quarantined'], true) ? 'discrepancy' : 'received';
        $this->notifyTransferParties($user, $lockedTransfer, $stage);
        AuditLog::log('stock_transfer_received', 'updated', $lockedTransfer, null, [
            'quantity_received' => (float) $lockedTransfer->quantity_received,
            'discrepancy_quantity' => (float) $lockedTransfer->discrepancy_quantity,
            'by' => $user->name,
        ]);

        return back()->with('success', $stage === 'discrepancy'
            ? 'Đã nhập phần hàng thực nhận. Yêu cầu đang chờ xử lý chênh lệch.'
            : 'Đã nhận đủ hàng đạt chất lượng và cộng tồn kho chi nhánh.');
    }

    /** Chủ hoặc Trưởng kho Tổng xác nhận hướng xử lý phần thiếu/hỏng. */
    public function resolveDiscrepancy(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate([
            'discrepancy_resolution' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'discrepancy_resolution.required' => 'Vui lòng nhập phương án / biên bản xử lý chênh lệch.',
            'discrepancy_resolution.min' => 'Hướng xử lý phải có ít nhất 2 ký tự.',
            'discrepancy_resolution.max' => 'Hướng xử lý không vượt quá 1000 ký tự.',
        ]);

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'discrepancy') {
                    throw new \RuntimeException('Yêu cầu không còn ở trạng thái chờ xử lý chênh lệch.');
                }
                $lockedTransfer->update([
                    'status' => 'received',
                    'discrepancy_resolution' => trim($data['discrepancy_resolution']),
                    'discrepancy_resolved_by' => $user->id,
                    'discrepancy_resolved_at' => now(),
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể chốt chênh lệch: '.$e->getMessage());
        }

        AuditLog::log('stock_transfer_discrepancy_resolved', 'updated', $lockedTransfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã ghi nhận hướng xử lý và đóng chênh lệch điều chuyển.');
    }

    /** Hủy yêu cầu trước khi xuất hàng. */
    public function cancel(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:500']]);

        $isRequestOnly = $this->isRequestOnly($user);
        $canCancel = $user->isSuperAdmin()
            || $user->hasAnyRole(['owner', 'warehouse_manager'])
            || ($isRequestOnly
                && (int) $transfer->requested_by === (int) $user->id
                && $transfer->status === 'requested');
        abort_unless($canCancel, 403, 'Bạn không có quyền hủy yêu cầu này.');
        if (! in_array($transfer->status, ['requested', 'routed'], true)) {
            return back()->with('error', 'Chỉ được hủy yêu cầu trước khi xuất hàng.');
        }

        try {
            $transfer = DB::transaction(function () use ($transfer, $data, $user): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if (! in_array($lockedTransfer->status, ['requested', 'routed'], true)) {
                    throw new \RuntimeException('Transfer can only be cancelled before dispatch.');
                }
                $lockedTransfer->update([
                    'status' => 'cancelled',
                    'cancel_reason' => trim($data['cancel_reason']),
                    'cancelled_by' => $user->id,
                    'cancelled_at' => now(),
                ]);
                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->notifyTransferParties($user, $transfer, 'cancelled');
        AuditLog::log('stock_transfer_cancelled', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã hủy yêu cầu điều chuyển.');
    }

    /** Chủ hoặc Trưởng kho Tổng từ chối yêu cầu trước khi xuất. */
    public function reject(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['reject_reason' => ['required', 'string', 'min:5', 'max:500']]);

        if (! in_array($transfer->status, ['requested', 'routed'], true)) {
            return back()->with('error', 'Chỉ từ chối được yêu cầu chưa xuất hàng.');
        }
        try {
            $transfer = DB::transaction(function () use ($transfer, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if (! in_array($lockedTransfer->status, ['requested', 'routed'], true)) {
                    throw new \RuntimeException('Transfer can only be rejected before dispatch.');
                }
                $lockedTransfer->update(['status' => 'rejected', 'reject_reason' => trim($data['reject_reason'])]);
                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->notifyTransferParties($user, $transfer, 'rejected');
        AuditLog::log('stock_transfer_rejected', 'updated', $transfer, null, ['by' => $user->name]);

        return back()->with('success', 'Đã từ chối yêu cầu điều chuyển.');
    }

    private function notifyOwners(User $actor, StockTransferRequest $transfer, string $stage): void
    {
        User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('id', '!=', $actor->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'warehouse_manager']))
            ->get()
            ->each(fn (User $user) => $user->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }

    private function notifyTransferParties(User $actor, StockTransferRequest $transfer, string $stage): void
    {
        $branchIds = array_values(array_filter([(int) $transfer->from_branch_id, (int) $transfer->to_branch_id]));
        $recipientIds = User::where('restaurant_id', $actor->restaurant_id)
            ->where(function ($query) use ($transfer, $branchIds) {
                $query->whereKey($transfer->requested_by)
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['owner', 'warehouse_manager']))
                    ->orWhere(function ($manager) use ($branchIds) {
                        $manager->whereIn('branch_id', $branchIds)
                            ->whereHas('roles', fn ($roles) => $roles->where('name', 'manager'));
                    });
            })
            ->where('id', '!=', $actor->id)
            ->pluck('id');

        User::whereIn('id', $recipientIds->unique())
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }

    private function notifyBranchManagers(?int $branchId, User $actor, StockTransferRequest $transfer, string $stage): void
    {
        if (! $branchId) {
            return;
        }

        User::query()
            ->where('restaurant_id', $actor->restaurant_id)
            ->where('id', '!=', $actor->id)
            ->where(function ($query) use ($branchId): void {
                $query->where(function ($branchQuery) use ($branchId): void {
                    $branchQuery->where('branch_id', $branchId)
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'manager'));
                })->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'warehouse_manager'));
            })
            ->get()
            ->each(fn (User $user) => $user->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }
}
