<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryQuarantine;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\StockTransferRequest;
use App\Models\StockTransferBatchLineage;
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
        return false;
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
     * Chủ doanh nghiệp, Trưởng kho Tổng hoặc Quản lý chi nhánh thuộc kho cấp/nhận
     * được thực hiện xuất/nhận kho điều chuyển theo thẩm quyền chi nhánh.
     */
    private function assertCanExecute(User $user): void
    {
        abort_unless(
            $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager', 'manager']),
            403,
            'Bạn không có quyền thực hiện xuất hoặc nhập kho điều chuyển.',
        );
    }

    private function assertTenantTransfer(User $user, StockTransferRequest $transfer): void
    {
        abort_if((int) $transfer->restaurant_id !== (int) $user->restaurant_id, 403);
    }

    private function generateTransferDocumentCode(int $restaurantId): string
    {
        do {
            $code = 'DC-NL/'.now()->format('Y').'/'.Str::upper(Str::random(10));
        } while (StockTransferRequest::query()
            ->where('restaurant_id', $restaurantId)
            ->where('document_code', $code)
            ->exists());

        return $code;
    }

    private function findActiveBusinessBranch(User $user, int $branchId): RestaurantBranch
    {
        return RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('is_central_warehouse', false)
                    ->orWhereNull('is_central_warehouse');
            })
            ->where(function ($query): void {
                $query->where('warehouse_type', '!=', 'central')
                    ->orWhereNull('warehouse_type');
            })
            ->where('name', 'not like', '%Kho Tổng%')
            ->where('name', 'not like', '%Tổng kho%')
            ->findOrFail($branchId);
    }

    /**
     * Return the actual source lots consumed by dispatch. The old singular
     * source_batch_id remains a compatibility fallback for legacy transfers.
     *
     * @return array<int, array{batch: ?InventoryBatch, quantity: float, unit_cost: float, remaining: float}>
     */
    private function sourceBatchRows(StockTransferRequest $transfer): array
    {
        $outTransaction = InventoryTransaction::query()
            ->where('restaurant_id', $transfer->restaurant_id)
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transfer->id)
            ->where('direction', 'out')
            ->with('batchAllocations')
            ->latest('id')
            ->first();

        $rows = [];
        foreach ($outTransaction?->batchAllocations ?? [] as $allocation) {
            $batch = InventoryBatch::withoutGlobalScopes()
                ->where('restaurant_id', $transfer->restaurant_id)
                ->where('branch_id', $transfer->from_branch_id)
                ->where('ingredient_id', $transfer->ingredient_id)
                ->find($allocation->inventory_batch_id);
            if (! $batch) {
                throw new \RuntimeException('Không còn tìm thấy lô nguồn của giao dịch xuất kho.');
            }

            $quantity = (float) $allocation->quantity;
            $rows[] = [
                'batch' => $batch,
                'quantity' => $quantity,
                'unit_cost' => (float) $allocation->unit_cost,
                'remaining' => $quantity,
            ];
        }

        if ($rows === [] && $transfer->source_batch_id) {
            $batch = InventoryBatch::withoutGlobalScopes()
                ->where('restaurant_id', $transfer->restaurant_id)
                ->where('branch_id', $transfer->from_branch_id)
                ->where('ingredient_id', $transfer->ingredient_id)
                ->find($transfer->source_batch_id);
            if (! $batch) {
                throw new \RuntimeException('Không còn tìm thấy lô nguồn của phiếu điều chuyển.');
            }
            $quantity = (float) ($transfer->quantity_dispatched ?? 0);
            $rows[] = [
                'batch' => $batch,
                'quantity' => $quantity,
                'unit_cost' => (float) $batch->unit_cost,
                'remaining' => $quantity,
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'batch' => null,
                'quantity' => (float) ($transfer->quantity_dispatched ?? 0),
                'unit_cost' => (float) ($transfer->dispatch_unit_cost ?? 0),
                'remaining' => (float) ($transfer->quantity_dispatched ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Consume received quantities against source lots so good, damaged and
     * expired quantities keep their own source-lot lineage.
     *
     * @param array<int, array{batch: ?InventoryBatch, quantity: float, unit_cost: float, remaining: float}> $rows
     * @return array<int, array{batch: ?InventoryBatch, quantity: float, unit_cost: float}>
     */
    private function consumeSourceBatchRows(array &$rows, float $quantity): array
    {
        $remaining = $quantity;
        $portions = [];
        foreach ($rows as &$row) {
            if ($remaining <= 0.0005) {
                break;
            }
            $take = min($remaining, (float) $row['remaining']);
            if ($take <= 0.0005) {
                continue;
            }
            $row['remaining'] = round((float) $row['remaining'] - $take, 3);
            $portions[] = [
                'batch' => $row['batch'],
                'quantity' => round($take, 3),
                'unit_cost' => (float) $row['unit_cost'],
            ];
            $remaining = round($remaining - $take, 3);
        }
        unset($row);

        if ($remaining > 0.0005) {
            throw new \InvalidArgumentException('Phân bổ lô nguồn không đủ cho số lượng thực nhận.');
        }

        return $portions;
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
                'sourceBatch:id,batch_number,expiry_date,purchased_at,unit_cost',
                'batchLineages.sourceBatch:id,batch_number,expiry_date,purchased_at,unit_cost',
                'batchLineages.destinationBatch:id,batch_number,expiry_date,purchased_at,unit_cost',
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

        $activeBatches = InventoryBatch::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0.0005)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC, purchased_at ASC, id ASC')
            ->get(['id', 'branch_id', 'ingredient_id', 'batch_number', 'expiry_date', 'purchased_at', 'quantity_remaining', 'unit_cost'])
            ->groupBy(fn (InventoryBatch $b): string => $b->branch_id.':'.$b->ingredient_id);

        $canRoute = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        $canExecute = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager', 'manager']);
        $groupSizes = $transfers
            ->filter(fn (StockTransferRequest $transfer): bool => $transfer->request_group_id !== null)
            ->countBy('request_group_id');
        $transfers = $transfers->map(function (StockTransferRequest $transfer) use ($user, $stocks, $activeBatches, $canRoute, $canExecute, $requestOnly, $groupSizes): array {
            $stock = $transfer->from_branch_id
                ? $stocks->get($transfer->from_branch_id.':'.$transfer->ingredient_id)
                : null;
            $isRequester = (int) $transfer->requested_by === (int) $user->id;

            $requestData = [
                'id' => $transfer->id,
                'request_group_id' => $transfer->request_group_id,
                'request_group_size' => $transfer->request_group_id
                    ? (int) ($groupSizes[$transfer->request_group_id] ?? 1)
                    : 1,
                'status' => $transfer->status,
                'priority' => $transfer->priority ?? 'normal',
                'ingredient_id' => $transfer->ingredient_id,
                'ingredient' => $transfer->ingredient?->name,
                'unit' => $transfer->ingredient?->unit?->symbol ?? 'đơn vị',
                'to_branch_id' => $transfer->to_branch_id,
                'to_branch' => $transfer->toBranch?->name,
                'quantity_requested' => (float) $transfer->quantity_requested,
                'backorder_quantity' => (float) ($transfer->backorder_quantity ?? 0),
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
                    && ($canRoute || ($isRequester && $transfer->status === 'requested') || ($user->assignedBranchId() !== null && (int) $transfer->to_branch_id === (int) $user->assignedBranchId() && $transfer->status === 'requested')),
            ];

            if ($requestOnly) {
                return $requestData;
            }

            return $requestData + [
                'from_branch_id' => $transfer->from_branch_id,
                'from_branch' => $transfer->fromBranch?->name,
                'quantity_remaining' => max(0, (float) $transfer->quantity_requested - (float) ($transfer->quantity_received ?? 0)),
                'discrepancy_quantity' => (float) ($transfer->discrepancy_quantity ?? 0),
                'shortage_quantity' => (float) ($transfer->shortage_quantity ?? 0),
                'shortage_action' => $transfer->shortage_action,
                'shortage_resolution' => $transfer->shortage_resolution,
                'shortage_resolved_at' => $transfer->shortage_resolved_at?->format('d/m/Y H:i'),
                'source_available_quantity' => $stock ? (float) $stock->quantity_on_hand : 0,
                'source_unit_cost' => $stock ? (float) $stock->last_cost : 0,
                'dispatch_note' => $transfer->dispatch_note,
                'dispatch_evidence_path' => $transfer->dispatch_evidence_path,
                'document_code' => $transfer->document_code,
                'received_condition' => $transfer->received_condition,
                'quantity_received_good' => $transfer->quantity_received_good !== null ? (float) $transfer->quantity_received_good : null,
                'quantity_received_damaged' => (float) ($transfer->quantity_received_damaged ?? 0),
                'quantity_received_expired' => (float) ($transfer->quantity_received_expired ?? 0),
                'source_batch_id' => $transfer->source_batch_id,
                'batch_lineages' => $transfer->batchLineages->map(fn (StockTransferBatchLineage $lineage): array => [
                    'quality' => $lineage->quality,
                    'quantity' => (float) $lineage->quantity,
                    'unit_cost' => (float) $lineage->unit_cost,
                    'source_batch' => $lineage->sourceBatch?->batch_number,
                    'destination_batch' => $lineage->destinationBatch?->batch_number,
                ])->values()->all(),
                'source_batch' => $transfer->sourceBatch ? [
                    'id' => $transfer->sourceBatch->id,
                    'batch_number' => $transfer->sourceBatch->batch_number,
                    'expiry_date' => $transfer->sourceBatch->expiry_date?->format('d/m/Y'),
                    'purchased_at' => $transfer->sourceBatch->purchased_at?->format('d/m/Y'),
                    'unit_cost' => (float) $transfer->sourceBatch->unit_cost,
                ] : null,
                'available_batches' => $transfer->from_branch_id
                    ? ($activeBatches->get($transfer->from_branch_id.':'.$transfer->ingredient_id) ?? collect())->map(fn (InventoryBatch $b): array => [
                        'id' => $b->id,
                        'batch_number' => $b->batch_number,
                        'expiry_date' => $b->expiry_date?->format('d/m/Y'),
                        'purchased_at' => $b->purchased_at?->format('d/m/Y'),
                        'quantity_remaining' => (float) $b->quantity_remaining,
                        'unit_cost' => (float) $b->unit_cost,
                    ])->values()->all()
                    : [],
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
                    && ((int) $transfer->dispatched_by !== (int) $user->id || $user->hasAnyRole(['owner', 'warehouse_manager'])),
                'can_resolve' => in_array($transfer->status, ['discrepancy', 'quarantined'], true)
                    && ((float) ($transfer->shortage_quantity ?? 0) + (float) ($transfer->backorder_quantity ?? 0)) > 0.0005
                    && ! $transfer->shortage_resolved_at
                    && ($canRoute || ($user->assignedBranchId() !== null && (int) $transfer->to_branch_id === (int) $user->assignedBranchId())),
            ];
        });

        $branches = RestaurantBranch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('is_central_warehouse', false)
                    ->orWhereNull('is_central_warehouse');
            })
            ->where(function ($q) {
                $q->where('warehouse_type', '!=', 'central')
                    ->orWhereNull('warehouse_type');
            })
            ->where('name', 'not like', '%Kho Tổng%')
            ->where('name', 'not like', '%Tổng kho%')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'phone', 'is_central_warehouse', 'warehouse_type']);

        $branchStock = $stocks->groupBy('branch_id')
            ->map(fn ($branchItems): array => $branchItems
                ->mapWithKeys(fn (Inventory $inventory): array => [
                    (string) $inventory->ingredient_id => (float) $inventory->quantity_on_hand,
                ])
                ->all())
            ->all();

        $branchBatches = $activeBatches->map(function ($batches) {
            return $batches->map(fn (InventoryBatch $b): array => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->format('Y-m-d'),
                'purchased_at' => $b->purchased_at?->format('Y-m-d'),
                'quantity_remaining' => (float) $b->quantity_remaining,
            ])->values()->all();
        })->all();

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
            ->get(['id', 'name', 'branch_id', 'unit_id', 'min_stock_level', 'safety_stock_quantity'])
            ->map(fn (Ingredient $ingredient): array => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'branch_id' => $ingredient->branch_id,
                'unit' => $ingredient->unit?->symbol ?? 'đơn vị',
                'min_stock_level' => (float) ($ingredient->min_stock_level ?? 0),
                'safety_stock_quantity' => (float) ($ingredient->safety_stock_quantity ?? 0),
                'available_quantity' => $requestOnly && $assignedBranchId
                    ? (float) ($stocks->get($assignedBranchId.':'.$ingredient->id)?->quantity_on_hand ?? 0)
                    : null,
            ]);

        $page = $requestOnly ? 'inventory/TransferRequests' : 'inventory/Transfers';

        return Inertia::render($page, [
            'transfers' => $transfers->values(),
            'branches' => $branches,
            'branch_stock' => $branchStock,
            'branch_batches' => $branchBatches,
            'ingredients' => $ingredients->values(),
            'assigned_branch_id' => $assignedBranchId,
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
            'from_branch_id' => ['nullable', TenantRule::exists('restaurant_branches')],
            'items' => ['nullable', 'array', 'min:1', 'max:50'],
            'items.*.ingredient_id' => ['required', 'integer', TenantRule::exists('ingredients'), 'distinct'],
            'items.*.quantity_requested' => ['required', 'numeric', 'min:0.001'],
            'ingredient_id' => ['nullable', 'integer', 'required_without:items', TenantRule::exists('ingredients')],
            'quantity_requested' => ['nullable', 'numeric', 'min:0.001', 'required_without:items'],
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

        $isGlobalAdmin = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);
        if (! $isGlobalAdmin && $assignedBranchId !== null) {
            abort_if(
                (int) $data['to_branch_id'] !== (int) $assignedBranchId,
                403,
                'Quản lý chi nhánh chỉ được tạo yêu cầu nhận hàng cho chi nhánh của mình, không được tạo hộ chi nhánh khác.'
            );
        }

        abort_unless(
            ! $requestOnly || ($assignedBranchId !== null && (int) $toBranch->id === (int) $assignedBranchId),
            403,
            'Yêu cầu phải được lập cho đúng chi nhánh đang được phân công.',
        );

        abort_if(
            $toBranch->is_central_warehouse || $toBranch->warehouse_type === 'central',
            422,
            'Điều chuyển chỉ áp dụng giữa các chi nhánh kinh doanh, không áp dụng cho Tổng kho.',
        );

        $fromBranchId = ! empty($data['from_branch_id']) ? (int) $data['from_branch_id'] : null;
        if ($fromBranchId) {
            abort_unless(
                $isGlobalAdmin,
                403,
                'Quản lý chi nhánh không được tự chọn kho cấp; hãy tạo yêu cầu để Chủ hoặc Trưởng kho định tuyến.',
            );
            $this->findActiveBusinessBranch($user, $fromBranchId);
            abort_if($fromBranchId === (int) $toBranch->id, 422, 'Chi nhánh cấp và chi nhánh nhận không được trùng nhau.');
        }

        if (! empty($data['idempotency_key'])) {
            $existing = StockTransferRequest::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->where(function ($query) use ($data): void {
                    $query->where('idempotency_key', $data['idempotency_key']);
                    if (! empty($data['items'])) {
                        $prefix = Str::limit((string) $data['idempotency_key'], 95, '');
                        $query->orWhere('idempotency_key', 'like', $prefix.'-%');
                    }
                })
                ->first();
            if ($existing) {
                return back()->with('success', 'Yêu cầu điều chuyển đã được ghi nhận trước đó.');
            }
        }

        if (! empty($data['items'])) {
            return $this->storeMultipleItems($request, $user, $toBranch, $data, $requestOnly);
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

        $isDirectRouted = $fromBranchId !== null;
        $transfer = StockTransferRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'to_branch_id' => $toBranch->id,
            'from_branch_id' => $fromBranchId,
            'ingredient_id' => $ingredient->id,
            'quantity_requested' => $data['quantity_requested'],
            'backorder_quantity' => 0,
            'priority' => $data['priority'] ?? ($user->isBranchManager() ? 'urgent' : 'normal'),
            'reason' => trim($data['reason']),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'document_code' => $this->generateTransferDocumentCode((int) $user->restaurant_id),
            'status' => $isDirectRouted ? 'routed' : 'requested',
            'requested_by' => $user->id,
            'routed_by' => $isDirectRouted ? $user->id : null,
            'routed_at' => $isDirectRouted ? now() : null,
            'handover_code' => $isDirectRouted ? strtoupper(Str::random(6)) : null,
        ]);

        $this->notifyTransferParties($user, $transfer, $isDirectRouted ? 'routed' : 'requested');
        AuditLog::log('stock_transfer_requested', 'created', $transfer, null, [
            'by' => $user->name,
            'direct_routed' => $isDirectRouted,
        ]);

        return back()->with('success', $isDirectRouted
            ? 'Đã tạo yêu cầu điều chuyển và gửi trực tiếp tới chi nhánh cấp hàng.'
            : 'Đã tạo yêu cầu điều chuyển và gửi vào hàng chờ định tuyến.');
    }

    /** Chủ hoặc Trưởng kho Tổng chọn nguồn cấp và duyệt định tuyến. */
    /**
     * Create multiple transfer lines in one submission. Each ingredient stays
     * as an independent operational record so FEFO, dispatch, receiving and
     * reconciliation remain precise; the group ID links the lines together.
     */
    private function storeMultipleItems(
        Request $request,
        User $user,
        RestaurantBranch $toBranch,
        array $data,
        bool $requestOnly,
    ): RedirectResponse {
        $fromBranchId = ! empty($data['from_branch_id']) ? (int) $data['from_branch_id'] : null;
        $isDirectRouted = $fromBranchId !== null;
        if ($isDirectRouted) {
            abort_unless(
                $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']),
                403,
                'Chỉ Chủ hoặc Trưởng kho được định tuyến trực tiếp nhiều nguyên liệu.',
            );
            $this->findActiveBusinessBranch($user, $fromBranchId);
        }
        $items = array_values($data['items']);
        $ingredientIds = collect($items)
            ->pluck('ingredient_id')
            ->map(static fn ($id): int => (int) $id)
            ->values();
        $ingredients = Ingredient::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereIn('id', $ingredientIds)
            ->get()
            ->keyBy('id');

        $normalizedItems = [];
        foreach ($items as $index => $item) {
            $ingredient = $ingredients->get((int) $item['ingredient_id']);
            $errorKey = "items.{$index}.ingredient_id";

            if (! $ingredient) {
                return back()->withErrors([$errorKey => 'Nguyên liệu không tồn tại hoặc đã ngừng sử dụng.']);
            }

            if ($ingredient->branch_id !== null && (int) $ingredient->branch_id !== (int) $toBranch->id) {
                return back()->withErrors([$errorKey => 'Nguyên liệu này chỉ được dùng tại chi nhánh khác.']);
            }

            $hasOpenRequest = StockTransferRequest::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->where('to_branch_id', $toBranch->id)
                ->where('ingredient_id', $ingredient->id)
                ->whereIn('status', ['requested', 'routed', 'dispatched', 'discrepancy', 'quarantined', 'return_requested'])
                ->exists();
            if ($hasOpenRequest) {
                return back()->withErrors([
                    $errorKey => 'Chi nhánh này đã có yêu cầu cùng nguyên liệu đang xử lý. Hãy theo dõi yêu cầu hiện tại hoặc ghi rõ nhu cầu bổ sung.',
                ]);
            }

            $normalizedItems[] = [
                'ingredient_id' => $ingredient->id,
                'quantity_requested' => $item['quantity_requested'],
            ];
        }

        $requestGroupId = (string) Str::uuid();
        $baseIdempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        $documentCode = $this->generateTransferDocumentCode((int) $user->restaurant_id);
        $transfers = DB::transaction(function () use (
            $normalizedItems,
            $requestGroupId,
            $baseIdempotencyKey,
            $user,
            $toBranch,
            $fromBranchId,
            $isDirectRouted,
            $data,
            $requestOnly,
            $documentCode,
        ): array {
            $created = [];
            $totalItems = count($normalizedItems);

            foreach ($normalizedItems as $index => $item) {
                $lineIdempotencyKey = null;
                if ($baseIdempotencyKey !== '') {
                    $lineIdempotencyKey = $totalItems === 1
                        ? $baseIdempotencyKey
                        : Str::limit($baseIdempotencyKey, 95, '').'-'.($index + 1);
                }

                $created[] = StockTransferRequest::create([
                    'restaurant_id' => $user->restaurant_id,
                    'request_group_id' => $requestGroupId,
                    'to_branch_id' => $toBranch->id,
                    'from_branch_id' => $fromBranchId,
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'backorder_quantity' => 0,
                    'priority' => $data['priority'] ?? ($user->isBranchManager() ? 'urgent' : 'normal'),
                    'reason' => trim($data['reason']),
                    'idempotency_key' => $lineIdempotencyKey,
                    'document_code' => $documentCode,
                    'status' => $isDirectRouted ? 'routed' : 'requested',
                    'requested_by' => $user->id,
                    'routed_by' => $isDirectRouted ? $user->id : null,
                    'routed_at' => $isDirectRouted ? now() : null,
                    'handover_code' => $isDirectRouted ? strtoupper(Str::random(6)) : null,
                ]);
            }

            return $created;
        });

        foreach ($transfers as $transfer) {
            $this->notifyTransferParties($user, $transfer, $isDirectRouted ? 'routed' : 'requested');
            AuditLog::log('stock_transfer_requested', 'created', $transfer, null, [
                'by' => $user->name,
                'request_group_id' => $requestGroupId,
                'request_group_size' => count($transfers),
                'direct_routed' => $isDirectRouted,
            ]);
        }

        return back()->with('success', $isDirectRouted
            ? 'Đã tạo phiếu điều chuyển gồm '.count($transfers).' nguyên liệu và gửi trực tiếp tới chi nhánh cấp hàng.'
            : 'Đã tạo phiếu điều chuyển gồm '.count($transfers).' nguyên liệu và gửi vào hàng chờ định tuyến.');
    }

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

                $fromBranch = $this->findActiveBusinessBranch($user, (int) $data['from_branch_id']);
                if ($fromBranch->id === (int) $lockedTransfer->to_branch_id) {
                    throw new \InvalidArgumentException('Chi nhánh cấp phải khác chi nhánh nhận.');
                }

                $sourceInventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $fromBranch->id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();
                if (! $sourceInventory || (float) $sourceInventory->quantity_available < 0.001) {
                    throw new \RuntimeException('Kho nguồn không còn tồn thực tế khả dụng để điều chuyển. Hãy chọn kho khác.');
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

    /** Chủ hoặc Trưởng kho Tổng định tuyến toàn bộ nguyên liệu trong cùng một phiếu/đợt yêu cầu. */
    public function batchRoute(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);

        $data = $request->validate([
            'request_group_id' => ['nullable', 'string', 'max:100'],
            'transfer_ids' => ['nullable', 'array', 'min:1'],
            'transfer_ids.*' => ['integer', TenantRule::exists('stock_transfer_requests', 'id')],
            'from_branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'owner_note' => ['nullable', 'string', 'max:500'],
        ]);

        $query = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'requested')
            ->with(['ingredient', 'toBranch']);

        if (! empty($data['request_group_id'])) {
            $query->where('request_group_id', $data['request_group_id']);
        } elseif (! empty($data['transfer_ids'])) {
            $query->whereIn('id', $data['transfer_ids']);
        } else {
            return back()->withErrors(['from_branch_id' => 'Không tìm thấy danh sách yêu cầu cần định tuyến.']);
        }

        $transfers = $query->get();
        if ($transfers->isEmpty()) {
            return back()->withErrors(['from_branch_id' => 'Không có yêu cầu nào đang ở trạng thái chờ định tuyến.']);
        }

        $fromBranch = $this->findActiveBusinessBranch($user, (int) $data['from_branch_id']);

        try {
            $routedTransfers = DB::transaction(function () use ($transfers, $fromBranch, $data, $user): array {
                $handoverCode = strtoupper(Str::random(6));
                $routed = [];

                foreach ($transfers as $item) {
                    $lockedTransfer = StockTransferRequest::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if ($lockedTransfer->status !== 'requested') {
                        continue;
                    }

                    if ($fromBranch->id === (int) $lockedTransfer->to_branch_id) {
                        throw new \InvalidArgumentException('Chi nhánh cấp phải khác chi nhánh nhận.');
                    }

                    $sourceInventory = Inventory::query()
                        ->where('restaurant_id', $lockedTransfer->restaurant_id)
                        ->where('branch_id', $fromBranch->id)
                        ->where('ingredient_id', $lockedTransfer->ingredient_id)
                        ->lockForUpdate()
                        ->first();

                    $ingredientName = $item->ingredient?->name ?? ('Nguyên liệu #' . $lockedTransfer->ingredient_id);

                    if (! $sourceInventory || (float) $sourceInventory->quantity_available < 0.001) {
                        throw new \RuntimeException("Kho {$fromBranch->name} không còn tồn khả dụng cho {$ingredientName}.");
                    }

                    $lockedTransfer->update([
                        'from_branch_id' => $fromBranch->id,
                        'owner_note' => isset($data['owner_note']) ? trim((string) $data['owner_note']) : null,
                        'status' => 'routed',
                        'routed_by' => $user->id,
                        'routed_at' => now(),
                        'handover_code' => $handoverCode,
                    ]);

                    $routed[] = $lockedTransfer->fresh();
                }

                return $routed;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['from_branch_id' => $e->getMessage()]);
        }

        foreach ($routedTransfers as $transfer) {
            $this->notifyTransferParties($user, $transfer, 'routed');
            AuditLog::log('stock_transfer_routed', 'updated', $transfer, null, [
                'from_branch_id' => $transfer->from_branch_id,
                'by' => $user->name,
                'batch_action' => true,
            ]);
        }

        $code = $routedTransfers[0]->handover_code ?? '';
        return back()->with('success', 'Đã định tuyến thành công ' . count($routedTransfers) . " nguyên liệu trong phiếu sang {$fromBranch->name}. Mã giao nhận chung: {$code}");
    }

    /** Chủ hoặc Trưởng kho Tổng từ chối toàn bộ nguyên liệu trong cùng một phiếu/đợt yêu cầu. */
    public function batchReject(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->assertCanRoute($user);

        $data = $request->validate([
            'request_group_id' => ['nullable', 'string', 'max:100'],
            'transfer_ids' => ['nullable', 'array', 'min:1'],
            'transfer_ids.*' => ['integer', TenantRule::exists('stock_transfer_requests', 'id')],
            'reject_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $query = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', ['requested', 'routed']);

        if (! empty($data['request_group_id'])) {
            $query->where('request_group_id', $data['request_group_id']);
        } elseif (! empty($data['transfer_ids'])) {
            $query->whereIn('id', $data['transfer_ids']);
        } else {
            return back()->withErrors(['reject_reason' => 'Không tìm thấy danh sách yêu cầu cần từ chối.']);
        }

        $transfers = $query->get();
        if ($transfers->isEmpty()) {
            return back()->with('error', 'Không có yêu cầu nào hợp lệ để từ chối.');
        }

        try {
            $rejectedTransfers = DB::transaction(function () use ($transfers, $data): array {
                $rejected = [];
                foreach ($transfers as $item) {
                    $locked = StockTransferRequest::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->status, ['requested', 'routed'], true)) {
                        continue;
                    }
                    $locked->update([
                        'status' => 'rejected',
                        'reject_reason' => trim($data['reject_reason']),
                    ]);
                    $rejected[] = $locked->fresh();
                }
                return $rejected;
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        foreach ($rejectedTransfers as $transfer) {
            $this->notifyTransferParties($user, $transfer, 'rejected');
            AuditLog::log('stock_transfer_rejected', 'updated', $transfer, null, [
                'by' => $user->name,
                'batch_action' => true,
            ]);
        }

        return back()->with('success', 'Đã từ chối ' . count($rejectedTransfers) . ' nguyên liệu trong phiếu.');
    }

    /** Hủy toàn bộ nguyên liệu trong cùng một phiếu/đợt yêu cầu. */
    public function batchCancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'request_group_id' => ['nullable', 'string', 'max:100'],
            'transfer_ids' => ['nullable', 'array', 'min:1'],
            'transfer_ids.*' => ['integer', TenantRule::exists('stock_transfer_requests', 'id')],
            'cancel_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $query = StockTransferRequest::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', ['requested', 'routed']);

        if (! empty($data['request_group_id'])) {
            $query->where('request_group_id', $data['request_group_id']);
        } elseif (! empty($data['transfer_ids'])) {
            $query->whereIn('id', $data['transfer_ids']);
        } else {
            return back()->withErrors(['cancel_reason' => 'Không tìm thấy danh sách yêu cầu cần hủy.']);
        }

        $transfers = $query->get();
        if ($transfers->isEmpty()) {
            return back()->with('error', 'Không có yêu cầu nào hợp lệ để hủy.');
        }

        $canCancelAll = $user->isSuperAdmin() || $user->hasAnyRole(['owner', 'warehouse_manager']);

        try {
            $cancelledTransfers = DB::transaction(function () use ($transfers, $data, $user, $canCancelAll): array {
                $cancelled = [];
                foreach ($transfers as $item) {
                    $locked = StockTransferRequest::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($locked->status, ['requested', 'routed'], true)) {
                        continue;
                    }
                    $canCancel = $canCancelAll
                        || ((int) $locked->requested_by === (int) $user->id && in_array($locked->status, ['requested', 'routed'], true))
                        || ($user->assignedBranchId() !== null && (int) $locked->to_branch_id === (int) $user->assignedBranchId() && $locked->status === 'requested');
                    if (! $canCancel) {
                        continue;
                    }

                    $locked->update([
                        'status' => 'cancelled',
                        'cancel_reason' => trim($data['cancel_reason']),
                        'cancelled_by' => $user->id,
                        'cancelled_at' => now(),
                    ]);
                    $cancelled[] = $locked->fresh();
                }
                return $cancelled;
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        foreach ($cancelledTransfers as $transfer) {
            $this->notifyTransferParties($user, $transfer, 'cancelled');
            AuditLog::log('stock_transfer_cancelled', 'updated', $transfer, null, [
                'by' => $user->name,
                'batch_action' => true,
            ]);
        }

        return back()->with('success', 'Đã hủy ' . count($cancelledTransfers) . ' nguyên liệu trong phiếu.');
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
            'batch_id' => ['nullable', 'integer'],
            'dispatch_evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $qty = (float) $data['quantity_dispatched'];

        if (! $transfer->from_branch_id || ! $user->canAccessBranch((int) $transfer->from_branch_id)) {
            abort(403, 'Bạn không thuộc chi nhánh cấp hàng.');
        }

        $dispatchEvidencePath = null;
        if ($request->hasFile('dispatch_evidence')) {
            $dispatchEvidencePath = $request->file('dispatch_evidence')->store('warehouse/stock-transfers/'.$user->restaurant_id, 'local');
        }

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $qty, $data, $dispatchEvidencePath): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if ($lockedTransfer->status !== 'routed') {
                    throw new \RuntimeException('Yêu cầu chưa được định tuyến hoặc đã xuất hàng.');
                }

                $inventory = Inventory::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('branch_id', $lockedTransfer->from_branch_id)
                    ->where('ingredient_id', $lockedTransfer->ingredient_id)
                    ->lockForUpdate()
                    ->first();

                $availableStock = $inventory ? (float) ($inventory->quantity_available ?? $inventory->quantity_on_hand) : 0.0;
                $requestedQty = (float) $lockedTransfer->quantity_requested;

                if ($availableStock <= 0.0005) {
                    throw new \RuntimeException('Chi nhánh cấp hiện không còn tồn kho khả dụng để xuất hàng.');
                }

                $ingredient = Ingredient::withoutGlobalScopes()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->findOrFail($lockedTransfer->ingredient_id);
                $unitName = $ingredient->unit?->symbol ?? 'đơn vị';

                // Quy tắc chia sẻ tồn kho:
                // 1. Khi tồn kho đủ đáp ứng (>= requestedQty): Cho phép xuất tối đa requestedQty.
                // 2. Khi tồn kho thiếu (< requestedQty): Cho phép xuất tối đa 2/3 tồn kho khả dụng (giữ lại 1/3 cho vận hành tại chỗ).
                $maxAllowedQty = $availableStock >= $requestedQty
                    ? $requestedQty
                    : round($availableStock * (2 / 3), 3);

                if ($availableStock >= $requestedQty && $qty + 0.0005 < $requestedQty) {
                    throw new \InvalidArgumentException('Kho đã đủ hàng; số lượng xuất phải bằng đủ số lượng yêu cầu. Nếu muốn giao thiếu, cần tạo phiếu bổ sung hoặc được ghi nhận chênh lệch riêng.');
                }

                if ($qty > $maxAllowedQty + 0.0005) {
                    if ($availableStock < $requestedQty) {
                        throw new \InvalidArgumentException("Tồn kho hiện có ({$availableStock} {$unitName}) không đủ số lượng yêu cầu ({$requestedQty} {$unitName}). Hệ thống cho phép xuất tối đa 2/3 tồn kho ({$maxAllowedQty} {$unitName}) để đảm bảo kho cấp vẫn đủ nguyên liệu vận hành.");
                    } else {
                        throw new \InvalidArgumentException("Số lượng xuất ({$qty} {$unitName}) không được vượt quá số lượng yêu cầu ({$requestedQty} {$unitName}).");
                    }
                }

                if ($qty < 0.001) {
                    throw new \InvalidArgumentException('Số lượng xuất phải lớn hơn 0.');
                }

                if (! $inventory || (float) $inventory->quantity_available + 0.0005 < $qty) {
                    throw new \RuntimeException('Chi nhánh cấp không đủ tồn thực tế để xuất hàng.');
                }

                $inventoryService = app(\App\Services\InventoryService::class);
                if ($ingredient->batch_tracking_required) {
                    $hasTransferableBatch = InventoryBatch::withoutGlobalScopes()
                        ->where('restaurant_id', $lockedTransfer->restaurant_id)
                        ->where('branch_id', $lockedTransfer->from_branch_id)
                        ->where('ingredient_id', $lockedTransfer->ingredient_id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0.0005)
                        ->where(function ($query): void {
                            $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
                        })
                        ->exists();
                    if (! $hasTransferableBatch) {
                        throw new \RuntimeException('Nguyên liệu này bắt buộc quản lý theo lô và hiện không có lô còn hạn để điều chuyển.');
                    }
                }
                $inventoryService->ensureLegacyBatchForInventory($inventory);

                $chosenBatch = ! empty($data['batch_id'])
                    ? InventoryBatch::withoutGlobalScopes()
                        ->where('restaurant_id', $lockedTransfer->restaurant_id)
                        ->where('branch_id', $lockedTransfer->from_branch_id)
                        ->where('ingredient_id', $lockedTransfer->ingredient_id)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0.0005)
                        ->where(function ($query): void {
                            $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
                        })
                        ->lockForUpdate()
                        ->find($data['batch_id'])
                    : null;

                if (! empty($data['batch_id']) && ! $chosenBatch) {
                    throw new \InvalidArgumentException('Lô được chọn không tồn tại, đã hết hạn, đã khóa hoặc không thuộc nguyên liệu/chi nhánh cấp.');
                }

                if ($chosenBatch) {
                    $allocQty = min($qty, (float) $chosenBatch->quantity_remaining);
                    $chosenBatch->update([
                        'quantity_remaining' => round((float) $chosenBatch->quantity_remaining - $allocQty, 3),
                    ]);
                    $allocations = [[
                        'batch_id' => $chosenBatch->id,
                        'quantity' => $allocQty,
                        'unit_cost' => (float) $chosenBatch->unit_cost,
                    ]];
                    $totalCost = $allocQty * (float) $chosenBatch->unit_cost;
                    $remainingNeeded = round($qty - $allocQty, 3);
                    if ($remainingNeeded > 0.0005) {
                        $subConsumption = $inventoryService->allocateBatchesForTransfer(
                            (int) $lockedTransfer->restaurant_id,
                            (int) $lockedTransfer->from_branch_id,
                            (int) $lockedTransfer->ingredient_id,
                            $remainingNeeded,
                            $ingredient->name,
                        );
                        if ((float) $subConsumption['shortage_quantity'] > 0.0005) {
                            throw new \RuntimeException('Lô hàng đã chọn và các lô FEFO khác không đủ tồn để xuất.');
                        }
                        $allocations = array_merge($allocations, $subConsumption['allocations']);
                        $totalCost += (float) $subConsumption['total_cost'];
                    }
                    $batchConsumption = [
                        'allocations' => $allocations,
                        'total_cost' => $totalCost,
                        'shortage_quantity' => 0.0,
                    ];
                    $sourceBatch = $chosenBatch;
                } else {
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
                    $sourceBatch = ! empty($batchConsumption['allocations'])
                        ? InventoryBatch::withoutGlobalScopes()->find($batchConsumption['allocations'][0]['batch_id'])
                        : null;
                }

                $unitCost = $qty > 0 && (float) $batchConsumption['total_cost'] > 0
                    ? (float) $batchConsumption['total_cost'] / $qty
                    : (float) $inventory->last_cost;
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
                    'backorder_quantity' => max(0, round($requestedQty - $qty, 3)),
                    'quantity_dispatched' => $qty,
                    'dispatch_unit_cost' => $unitCost,
                    'dispatched_by' => $user->id,
                    'dispatched_at' => now(),
                    'dispatch_note' => isset($data['dispatch_note']) ? trim((string) $data['dispatch_note']) : null,
                    'dispatch_evidence_path' => $dispatchEvidencePath,
                    'source_batch_id' => $sourceBatch?->id,
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            if ($dispatchEvidencePath) {
                Storage::disk('local')->delete($dispatchEvidencePath);
            }
            $message = 'Không thể xuất hàng: '.$e->getMessage();
            return back()->with('error', $message)->withErrors(['quantity_dispatched' => $message]);
        }

        $this->notifyTransferParties($user, $lockedTransfer, 'dispatched');
        app(WarehouseReverseLogisticsService::class)->recordShipmentEvent(
            (int) $lockedTransfer->restaurant_id,
            'stock_transfer',
            (int) $lockedTransfer->id,
            'dispatched',
            $user,
            [
                'branch_id' => $lockedTransfer->from_branch_id,
                'evidence_path' => $lockedTransfer->dispatch_evidence_path,
                'notes' => $lockedTransfer->dispatch_note,
            ],
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
                $expectedHandoverCode = strtoupper(trim((string) $lockedTransfer->handover_code));
                $providedHandoverCode = strtoupper(trim((string) ($data['handover_code'] ?? '')));
                if ($expectedHandoverCode === '' || $providedHandoverCode === '' || ! hash_equals($expectedHandoverCode, $providedHandoverCode)) {
                    throw new \InvalidArgumentException('Mã giao nhận không khớp với phiếu điều chuyển.');
                }
                if ((int) $lockedTransfer->dispatched_by === (int) $user->id) {
                    throw new \InvalidArgumentException('Người nhận phải khác người xuất hàng.');
                }

                $ingredient = Ingredient::withoutGlobalScopes()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->findOrFail($lockedTransfer->ingredient_id);
                $temperatureMin = array_key_exists('transport_temperature_min_c', $data) && $data['transport_temperature_min_c'] !== null
                    ? (float) $data['transport_temperature_min_c']
                    : null;
                $temperatureMax = array_key_exists('transport_temperature_max_c', $data) && $data['transport_temperature_max_c'] !== null
                    ? (float) $data['transport_temperature_max_c']
                    : null;
                if (($temperatureMin === null) !== ($temperatureMax === null)) {
                    throw new \InvalidArgumentException('Phải nhập đồng thời nhiệt độ thấp nhất và cao nhất.');
                }
                if ($temperatureMin !== null && $temperatureMax !== null && $temperatureMin > $temperatureMax) {
                    throw new \InvalidArgumentException('Nhiệt độ thấp nhất không được lớn hơn nhiệt độ cao nhất.');
                }
                $temperatureBreach = ($temperatureMin !== null && $ingredient->storage_temperature_min_c !== null && $temperatureMin < (float) $ingredient->storage_temperature_min_c)
                    || ($temperatureMax !== null && $ingredient->storage_temperature_max_c !== null && $temperatureMax > (float) $ingredient->storage_temperature_max_c);

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
                if ($temperatureBreach && $goodQty > 0.0005) {
                    throw new \InvalidArgumentException('Nhiệt độ vận chuyển vượt giới hạn bảo quản; phần bị ảnh hưởng phải được phân loại vào hàng hỏng hoặc hết hạn, không được nhập như hàng tốt.');
                }
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
                $sourceBatchRows = $this->sourceBatchRows($lockedTransfer);
                if ($ingredient->batch_tracking_required && collect($sourceBatchRows)->contains(fn (array $row): bool => $row['batch'] === null)) {
                    throw new \RuntimeException('Nguyên liệu này bắt buộc truy xuất theo lô nhưng phiếu xuất không có lô nguồn hợp lệ.');
                }
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
                    $goodPortions = $this->consumeSourceBatchRows($sourceBatchRows, $goodQty);
                    foreach ($goodPortions as $portion) {
                        $destinationBatch = $reverseLogistics->createDestinationBatch(
                            (int) $lockedTransfer->restaurant_id,
                            (int) $lockedTransfer->to_branch_id,
                            (int) $lockedTransfer->ingredient_id,
                            (float) $portion['quantity'],
                            (float) $portion['unit_cost'],
                            $user,
                            $portion['batch'],
                        );
                        if (! $destinationBatch) {
                            continue;
                        }
                        $destinationBatchId ??= $destinationBatch->id;
                        InventoryBatchAllocation::create([
                            'restaurant_id' => $lockedTransfer->restaurant_id,
                            'branch_id' => $lockedTransfer->to_branch_id,
                            'inventory_batch_id' => $destinationBatch->id,
                            'inventory_transaction_id' => $transaction->id,
                            'direction' => 'in',
                            'quantity' => $portion['quantity'],
                            'unit_cost' => $portion['unit_cost'],
                        ]);
                        StockTransferBatchLineage::create([
                            'restaurant_id' => $lockedTransfer->restaurant_id,
                            'stock_transfer_request_id' => $lockedTransfer->id,
                            'source_batch_id' => $portion['batch']?->id,
                            'destination_batch_id' => $destinationBatch->id,
                            'inventory_transaction_id' => $transaction->id,
                            'quality' => 'good',
                            'quantity' => $portion['quantity'],
                            'unit_cost' => $portion['unit_cost'],
                        ]);
                    }
                }

                $quarantineId = null;
                if ($badQty > 0) {
                    $damagedPortions = $this->consumeSourceBatchRows($sourceBatchRows, $damagedQty);
                    $expiredPortions = $this->consumeSourceBatchRows($sourceBatchRows, $expiredQty);
                    $quarantineSequence = 0;
                    foreach ([
                        ['condition' => 'damaged', 'portions' => $damagedPortions],
                        ['condition' => 'expired', 'portions' => $expiredPortions],
                    ] as $qualityGroup) {
                        foreach ($qualityGroup['portions'] as $portion) {
                            $quarantineSequence++;
                            $lockedBatch = $reverseLogistics->createDestinationBatch(
                                (int) $lockedTransfer->restaurant_id,
                                (int) $lockedTransfer->to_branch_id,
                                (int) $lockedTransfer->ingredient_id,
                                (float) $portion['quantity'],
                                (float) $portion['unit_cost'],
                                $user,
                                $portion['batch'],
                                true,
                                'Hàng điều chuyển bị hỏng hoặc hết hạn, chờ hoàn trả/tiêu hủy.',
                            );
                            $quarantine = $reverseLogistics->createQuarantine(
                                (int) $lockedTransfer->restaurant_id,
                                (int) $lockedTransfer->to_branch_id,
                                (int) $lockedTransfer->ingredient_id,
                                (float) $portion['quantity'],
                                $qualityGroup['condition'],
                                trim((string) ($data['received_note'] ?? 'Hàng không đạt khi nhận điều chuyển.')),
                                $user,
                                $lockedBatch,
                                'stock_transfer',
                                $lockedTransfer->id,
                                $quarantineSequence,
                                array_filter([$evidencePath]),
                                $data['received_note'] ?? null,
                            );
                            StockTransferBatchLineage::create([
                                'restaurant_id' => $lockedTransfer->restaurant_id,
                                'stock_transfer_request_id' => $lockedTransfer->id,
                                'source_batch_id' => $portion['batch']?->id,
                                'destination_batch_id' => $lockedBatch?->id,
                                'quarantine_id' => $quarantine->id,
                                'quality' => $qualityGroup['condition'],
                                'quantity' => $portion['quantity'],
                                'unit_cost' => $portion['unit_cost'],
                            ]);
                            $quarantineId ??= $quarantine->id;
                        }
                    }
                }

                $backorderQuantity = max(0, round((float) $lockedTransfer->quantity_requested - $dispatchedQty, 3));
                $shortageQuantity = $difference;
                $lossQuantity = round($shortageQuantity + $badQty + $backorderQuantity, 3);
                $nextStatus = $badQty > 0
                    ? 'quarantined'
                    : (($shortageQuantity > 0.0005 || $backorderQuantity > 0.0005) ? 'discrepancy' : 'received');
                $lockedTransfer->update([
                    'status' => $nextStatus,
                    'backorder_quantity' => $backorderQuantity,
                    'quantity_received' => $receivedQty,
                    'quantity_received_good' => $goodQty,
                    'quantity_received_damaged' => $damagedQty,
                    'quantity_received_expired' => $expiredQty,
                    'discrepancy_quantity' => $lossQuantity,
                    'shortage_quantity' => $shortageQuantity,
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'received_condition' => $receivedCondition,
                    'received_note' => isset($data['received_note']) ? trim((string) $data['received_note']) : null,
                    'receiving_evidence_path' => $evidencePath,
                    'discrepancy_reason' => $lossQuantity > 0 ? trim((string) $data['received_note']) : null,
                    'destination_batch_id' => $destinationBatchId,
                    'quarantine_id' => $quarantineId,
                    'transport_temperature_min_c' => $temperatureMin,
                    'transport_temperature_max_c' => $temperatureMax,
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
                        'temperature_min_c' => $temperatureMin,
                        'temperature_max_c' => $temperatureMax,
                        'notes' => $data['received_note'] ?? null,
                    ],
                );

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            if ($evidencePath) {
                Storage::disk('local')->delete($evidencePath);
            }

            if ($e instanceof \InvalidArgumentException && str_contains(strtolower($e->getMessage()), 'giao')) {
                return back()->withErrors(['handover_code' => $e->getMessage()]);
            }

            if ($e instanceof \InvalidArgumentException) {
                $message = $e->getMessage();
                $field = str_contains($message, 'Mã giao nhận') || str_contains($message, 'Người nhận')
                    ? 'handover_code'
                    : (str_contains($message, 'Số lượng') ? 'quantity_received' : 'received_note');

                return back()->withErrors([$field => $message]);
            }

            $message = 'Không thể nhận hàng: '.$e->getMessage();
            return back()->with('error', $message)->withErrors(['received_note' => $message]);
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

    /** Xác nhận hướng xử lý phần thiếu/hỏng và đóng hồ sơ chênh lệch. */
    public function resolveDiscrepancy(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);
        $canResolve = $user->isSuperAdmin()
            || $user->hasAnyRole(['owner', 'warehouse_manager'])
            || ($transfer->to_branch_id && $user->canAccessBranch((int) $transfer->to_branch_id));
        abort_unless($canResolve, 403, 'Bạn không có quyền chốt xử lý chênh lệch này.');

        $data = $request->validate([
            'discrepancy_resolution' => ['required', 'string', 'min:2', 'max:1000'],
            'shortage_action' => ['nullable', 'string', 'in:reship,accepted_loss,claim_supplier,claim_carrier,adjustment'],
        ], [
            'discrepancy_resolution.required' => 'Vui lòng nhập phương án / biên bản xử lý chênh lệch.',
            'discrepancy_resolution.min' => 'Hướng xử lý phải có ít nhất 2 ký tự.',
            'discrepancy_resolution.max' => 'Hướng xử lý không vượt quá 1000 ký tự.',
        ]);

        try {
            $lockedTransfer = DB::transaction(function () use ($transfer, $user, $data): StockTransferRequest {
                $lockedTransfer = StockTransferRequest::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                if (! in_array($lockedTransfer->status, ['discrepancy', 'quarantined'], true)) {
                    throw new \RuntimeException('Yêu cầu không còn ở trạng thái chờ xử lý chênh lệch.');
                }
                $shortageQuantity = (float) ($lockedTransfer->shortage_quantity ?? 0);
                $backorderQuantity = (float) ($lockedTransfer->backorder_quantity ?? 0);
                $pendingShortage = $shortageQuantity + $backorderQuantity > 0.0005 && ! $lockedTransfer->shortage_resolved_at;
                $shortageAction = $data['shortage_action'] ?? ($pendingShortage ? 'accepted_loss' : null);

                if ($pendingShortage && ! $shortageAction) {
                    throw new \InvalidArgumentException('Phải chọn hướng xử lý phần thiếu hoặc phần chưa được đáp ứng.');
                }

                if ($shortageAction === 'claim_supplier') {
                    $supplierId = Ingredient::withoutGlobalScopes()
                        ->where('restaurant_id', $lockedTransfer->restaurant_id)
                        ->whereKey($lockedTransfer->ingredient_id)
                        ->value('supplier_id');
                    if (! $supplierId) {
                        throw new \InvalidArgumentException('Không thể khiếu nại nhà cung cấp vì nguyên liệu chưa có nhà cung cấp mặc định.');
                    }
                    $reverseLogistics = app(WarehouseReverseLogisticsService::class);
                    $reverseLogistics->createClaim($user, [
                        'supplier_id' => $supplierId,
                        'source_type' => 'stock_transfer',
                        'source_id' => $lockedTransfer->id,
                        'carrier_name' => $lockedTransfer->carrier_name,
                        'reason' => 'Khiếu nại phần thiếu của phiếu điều chuyển '.$lockedTransfer->document_code,
                        'loss_amount' => ($shortageQuantity + $backorderQuantity) * (float) ($lockedTransfer->dispatch_unit_cost ?? 0),
                        'requested_action' => 'replacement',
                    ]);
                }

                if ($shortageAction === 'claim_carrier' && blank($lockedTransfer->carrier_name)) {
                    throw new \InvalidArgumentException('Không thể khiếu nại đơn vị vận chuyển vì phiếu chưa có tên đơn vị vận chuyển.');
                }

                if ($shortageAction === 'claim_carrier') {
                    app(WarehouseReverseLogisticsService::class)->createClaim($user, [
                        'source_type' => 'stock_transfer',
                        'source_id' => $lockedTransfer->id,
                        'carrier_name' => $lockedTransfer->carrier_name,
                        'reason' => 'Khiếu nại phần thiếu của phiếu điều chuyển '.$lockedTransfer->document_code,
                        'loss_amount' => ($shortageQuantity + $backorderQuantity) * (float) ($lockedTransfer->dispatch_unit_cost ?? 0),
                        'requested_action' => 'replacement',
                    ]);
                }

                if ($shortageAction === 'reship' && $pendingShortage) {
                    StockTransferRequest::create([
                        'restaurant_id' => $lockedTransfer->restaurant_id,
                        'to_branch_id' => $lockedTransfer->to_branch_id,
                        'from_branch_id' => null,
                        'ingredient_id' => $lockedTransfer->ingredient_id,
                        'quantity_requested' => round($shortageQuantity + $backorderQuantity, 3),
                        'backorder_quantity' => 0,
                        'priority' => 'urgent',
                        'reason' => 'Xuất bổ sung phần thiếu từ phiếu '.$lockedTransfer->document_code,
                        'status' => 'requested',
                        'requested_by' => $user->id,
                        'document_code' => $this->generateTransferDocumentCode((int) $lockedTransfer->restaurant_id),
                        'idempotency_key' => 'stock_transfer_reship_'.$lockedTransfer->id,
                    ]);
                }

                $openQuarantine = InventoryQuarantine::query()
                    ->where('restaurant_id', $lockedTransfer->restaurant_id)
                    ->where('source_type', 'stock_transfer')
                    ->where('source_id', $lockedTransfer->id)
                    ->whereNotIn('status', ['returned', 'destroyed'])
                    ->exists();
                $lockedTransfer->update([
                    'status' => $openQuarantine
                        ? 'quarantined'
                        : 'received',
                    'discrepancy_resolution' => trim($data['discrepancy_resolution']),
                    'discrepancy_resolved_by' => $user->id,
                    'discrepancy_resolved_at' => now(),
                    'shortage_action' => $shortageAction,
                    'shortage_resolution' => trim($data['discrepancy_resolution']),
                    'shortage_resolved_by' => $pendingShortage ? $user->id : $lockedTransfer->shortage_resolved_by,
                    'shortage_resolved_at' => $pendingShortage ? now() : $lockedTransfer->shortage_resolved_at,
                ]);

                return $lockedTransfer->fresh();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể chốt chênh lệch: '.$e->getMessage());
        }

        $this->notifyTransferParties($user, $lockedTransfer, $lockedTransfer->status === 'quarantined' ? 'discrepancy' : 'received');
        AuditLog::log('stock_transfer_discrepancy_resolved', 'updated', $lockedTransfer, null, [
            'by' => $user->name,
            'shortage_action' => $lockedTransfer->shortage_action,
            'status' => $lockedTransfer->status,
        ]);

        return back()->with('success', 'Đã ghi nhận hướng xử lý và đóng chênh lệch điều chuyển.');
    }

    /** Hủy yêu cầu trước khi xuất hàng. */
    public function cancel(Request $request, StockTransferRequest $transfer): RedirectResponse
    {
        $user = $request->user();
        $this->assertManager($user);
        $this->assertTenantTransfer($user, $transfer);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:500']]);

        $canCancel = $user->isSuperAdmin()
            || $user->hasAnyRole(['owner', 'warehouse_manager'])
            || ((int) $transfer->requested_by === (int) $user->id && in_array($transfer->status, ['requested', 'routed'], true))
            || ($user->assignedBranchId() !== null && (int) $transfer->to_branch_id === (int) $user->assignedBranchId() && $transfer->status === 'requested');
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
                        $manager->where(function ($b) use ($branchIds) {
                            $b->whereIn('branch_id', $branchIds)
                              ->orWhereHas('employee', fn ($emp) => $emp->whereIn('branch_id', $branchIds));
                        })->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['manager', 'quản lý', 'quan_ly', 'quanly']));
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
                        ->orWhereHas('employee', fn ($emp) => $emp->where('branch_id', $branchId));
                })->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['manager', 'quản lý', 'quan_ly', 'quanly']))
                ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'warehouse_manager'));
            })
            ->get()
            ->each(fn (User $user) => $user->notify(new StockTransferStageNotification($transfer, $stage, $actor->name)));
    }
}
