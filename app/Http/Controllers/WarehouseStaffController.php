<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryBatch;
use App\Models\InventoryCountSession;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingVoucher;
use App\Models\WarehouseReceivingVoucherItem;
use App\Models\WarehouseShiftHandover;
use App\Models\WarehouseTaskAssignment;
use App\Models\User;
use App\Services\CentralWarehouseService;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseStaffController extends Controller
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
    ) {}

    // ── 1. Trang Portal Nhân Viên Kho ────────────────────────────────────────

    /**
     * Render trang portal dành riêng cho nhân viên kho (mobile-first).
     * Chỉ trả dữ liệu liên quan đến người dùng hiện tại.
     */
    public function staffPortalPage(Request $request): Response
    {
        $user         = $request->user();
        $restaurantId = $user->restaurant_id;
        $userId       = $user->id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        // Task của tôi hôm nay + pending
        $myTasks = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->myTasks($userId)
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['supplyRequest.toBranch', 'receivingVoucher', 'assignee'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $taskSummary = [
            'total'       => $myTasks->count(),
            'pending'     => $myTasks->whereIn('status', ['assigned'])->count(),
            'in_progress' => $myTasks->where('status', 'in_progress')->count(),
            'completed_today' => $myTasks->where('status', 'completed')
                ->filter(fn ($t) => $t->completed_at?->isToday())
                ->count(),
            'overdue'     => $myTasks->filter(fn ($t) => $t->isOverdue())->count(),
        ];

        // Phiếu nhận hàng gần đây do tôi thực hiện
        $myVouchers = WarehouseReceivingVoucher::where('restaurant_id', $restaurantId)
            ->where('received_by', $userId)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id))
            ->with(['items.ingredient', 'verifiedBy'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Bàn giao ca gần đây của tôi
        $myHandovers = WarehouseShiftHandover::where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->where('handover_by', $userId)->orWhere('received_by', $userId))
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id))
            ->with(['handoverBy', 'receivedBy'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Vị trí kho
        $locations = $this->centralLocationQuery($restaurantId, $centralBranch?->id)
            ->where('status', 'active')
            ->orderBy('zone')
            ->orderBy('location_code')
            ->get();

        // Nguyên liệu (cho form nhận hàng)
        $ingredients = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->with(['unit'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'average_cost', 'unit_id']);

        $canManage = $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage');

        return Inertia::render('inventory/WarehouseStaffPortal', [
            'centralBranch'     => $centralBranch,
            'myTasks'           => $myTasks,
            'taskSummary'       => $taskSummary,
            'myVouchers'        => $myVouchers,
            'myHandovers'       => $myHandovers,
            'locations'         => $locations,
            'ingredients'       => $ingredients,
            'canManageWarehouse' => $canManage,
            'currentUser'       => [
                'id'         => $userId,
                'name'       => $user->name,
                'job_title'  => $user->employee?->job_title ?: 'Nhân viên Kho Tổng',
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    // ── 2. Task Management ───────────────────────────────────────────────────

    /**
     * API: Task của tôi (grouped by status)
     */
    public function myTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $tasks = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->myTasks($user->id)
            ->when(
                $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id),
                fn ($query, $central) => $query->where(function ($scope) use ($central) {
                    $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $central->id))
                        ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $central->id))
                        ->orWhere(function ($unlinked) {
                            $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                        });
                }),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['supplyRequest.toBranch', 'receivingVoucher'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->get()
            ->map(fn ($t) => $this->formatTask($t));

        return response()->json([
            'tasks'   => $tasks,
            'summary' => [
                'total'       => $tasks->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'assigned'    => $tasks->where('status', 'assigned')->count(),
                'overdue'     => $tasks->filter(fn ($t) => $t['is_overdue'])->count(),
                'completed_today' => $tasks->where('status', 'completed')
                    ->filter(fn ($t) => isset($t['completed_at']) && \Carbon\Carbon::parse($t['completed_at'])->isToday())
                    ->count(),
            ],
        ]);
    }

    /**
     * Bắt đầu một task (set started_at, chuyển status thành in_progress)
     */
    public function startTask(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('assigned_to', $user->id)
            ->where(function ($scope) use ($centralBranch) {
                $scope->when($centralBranch, fn ($query) => $query->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    }), fn ($query) => $query->whereRaw('1 = 0'));
            })
            ->findOrFail($id);

        abort_if($task->status === 'completed', 422, 'Task đã hoàn thành, không thể bắt đầu lại.');
        abort_if($task->status === 'in_progress', 422, 'Task đang được thực hiện.');

        $task->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        $this->logAudit($user, 'warehouse_task.start', $task, [
            'task_type' => $task->task_type,
            'task_id'   => $task->id,
        ]);

        return response()->json(['message' => 'Bắt đầu task thành công.', 'task' => $this->formatTask($task->fresh())]);
    }

    /**
     * Hoàn thành một task + upload evidence
     */
    public function completeTask(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'result_notes' => 'nullable|string|max:1000',
            'evidence'     => 'nullable|array',
            'evidence.*'   => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('assigned_to', $user->id)
            ->where(function ($scope) use ($centralBranch) {
                $scope->when($centralBranch, fn ($query) => $query->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    }), fn ($query) => $query->whereRaw('1 = 0'));
            })
            ->findOrFail($id);

        abort_if($task->status === 'completed', 422, 'Task đã hoàn thành rồi.');

        $evidencePaths = $task->evidence_paths ?? [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('warehouse/evidence/' . now()->format('Y/m'), 'local');
                $evidencePaths[] = $path;
            }
        }

        $task->update([
            'status'        => 'completed',
            'completed_at'  => now(),
            'result_notes'  => $request->result_notes,
            'evidence_paths' => $evidencePaths,
        ]);

        $this->logAudit($user, 'warehouse_task.complete', $task, [
            'task_type'    => $task->task_type,
            'result_notes' => $request->result_notes,
            'evidence_count' => count($evidencePaths),
        ]);

        return response()->json(['message' => 'Task hoàn thành!', 'task' => $this->formatTask($task->fresh())]);
    }

    // ── 3. Phiếu Nhận Hàng (GRN) ────────────────────────────────────────────

    /**
     * Tạo phiếu nhận hàng mới
     */
    public function storeReceivingVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'received_at'         => 'required|date',
            'supplier_id'         => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'purchase_order_id'   => ['nullable', 'integer', TenantRule::exists('purchase_orders')],
            'delivery_note_number' => 'nullable|string|max:100',
            'invoice_number'      => 'nullable|string|max:100',
            'vehicle_number'      => 'nullable|string|max:50',
            'seal_code'           => 'nullable|string|max:50',
            'quality_status'      => 'nullable|in:pending,passed,conditional,failed',
            'quality_notes'       => 'nullable|string|max:1000',
            'notes'               => 'nullable|string|max:500',
            'items'               => 'required|array|min:1',
            'items.*.ingredient_id' => ['required', 'integer', TenantRule::exists('ingredients')],
            'items.*.expected_qty'  => 'required|numeric|min:0',
            'items.*.actual_qty'    => 'required|numeric|min:0',
            'items.*.unit_cost'     => 'nullable|numeric|min:0',
            'items.*.lot_number'    => 'nullable|string|max:100',
            'items.*.expiry_date'   => 'nullable|date',
            'items.*.location_id'   => ['nullable', 'integer', TenantRule::exists('warehouse_locations')],
            'items.*.discrepancy_reason' => 'nullable|string|max:500',
            'evidence'            => 'nullable|array',
            'evidence.*'          => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        if ($request->filled('supplier_id')) {
            abort_unless(
                Supplier::where('restaurant_id', $restaurantId)
                    ->whereKey((int) $request->supplier_id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch->id))
                    ->exists(),
                422,
                'Nhà cung cấp không thuộc phạm vi Kho Tổng.',
            );
        }

        $purchaseOrder = null;
        if ($request->filled('purchase_order_id')) {
            $purchaseOrder = PurchaseOrder::where('restaurant_id', $restaurantId)
                ->whereKey((int) $request->purchase_order_id)
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch->id))
                ->with(['supplier', 'items:id,purchase_order_id,ingredient_id'])
                ->firstOrFail();

            abort_if($purchaseOrder->status === 'cancelled' || $purchaseOrder->is_frozen, 422, 'Đơn mua hàng đã bị hủy hoặc khóa, không thể lập GRN.');
            abort_if(
                $request->filled('supplier_id') && (int) $request->supplier_id !== (int) $purchaseOrder->supplier_id,
                422,
                'Nhà cung cấp trên GRN không khớp với đơn mua hàng.',
            );

            $poIngredientIds = $purchaseOrder->items->pluck('ingredient_id')->map(fn ($id): int => (int) $id);
            abort_if(
                collect($request->input('items', []))->pluck('ingredient_id')->map(fn ($id): int => (int) $id)->diff($poIngredientIds)->isNotEmpty(),
                422,
                'GRN có nguyên liệu không nằm trong đơn mua hàng đã chọn.',
            );
        }

        $invalidReceivingLines = collect($request->input('items', []))
            ->filter(fn (array $item): bool => (float) ($item['actual_qty'] ?? 0) > 0 && empty($item['location_id']))
            ->keys();
        abort_if($invalidReceivingLines->isNotEmpty(), 422, 'Mỗi dòng có hàng thực nhận phải được gắn vị trí cất hàng.');

        $unexplainedDiscrepancies = collect($request->input('items', []))
            ->filter(fn (array $item): bool => abs((float) ($item['actual_qty'] ?? 0) - (float) ($item['expected_qty'] ?? 0)) > 0.001 && blank($item['discrepancy_reason'] ?? null))
            ->keys();
        abort_if($unexplainedDiscrepancies->isNotEmpty(), 422, 'Mỗi dòng thiếu/thừa phải có lý do chênh lệch.');

        $ingredientIds = collect($request->input('items', []))
            ->pluck('ingredient_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        abort_unless(
            $this->centralIngredientQuery($restaurantId, $centralBranch->id)
                ->whereIn('id', $ingredientIds)
                ->count() === $ingredientIds->count(),
            422,
            'Phiáº¿u nháº­n hÃ ng chá»‰ Ä‘Æ°á»£c phÃ©p dÃ¹ng nguyÃªn liá»‡u toÃ n chuá»—i hoáº·c thuá»™c Kho Tá»•ng.'
        );

        $locationIds = collect($request->input('items', []))
            ->pluck('location_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        abort_unless(
            $this->centralLocationQuery($restaurantId, $centralBranch->id)
                ->whereIn('id', $locationIds)
                ->count() === $locationIds->count(),
            422,
            'Vá»‹ trÃ­ cất hÃ ng pháº£i thuá»™c Kho Tá»•ng.'
        );

        return DB::transaction(function () use ($request, $user, $restaurantId, $centralBranch, $purchaseOrder) {
            // Upload ảnh bằng chứng
            $evidencePaths = [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $evidencePaths[] = $file->store('warehouse/grn/' . now()->format('Y/m'), 'public');
                }
            }

            $totalExpected    = 0;
            $totalActual      = 0;
            $hasDiscrepancy   = false;

            // Tính tổng
            foreach ($request->items as $item) {
                $totalExpected += (float) $item['expected_qty'];
                $totalActual   += (float) $item['actual_qty'];
                if (abs((float) $item['expected_qty'] - (float) $item['actual_qty']) > 0.001) {
                    $hasDiscrepancy = true;
                }
            }

            $discrepancyQty = $totalActual - $totalExpected;
            $status = $hasDiscrepancy ? 'discrepancy' : 'draft';

            $voucher = WarehouseReceivingVoucher::create([
                'restaurant_id'          => $restaurantId,
                'branch_id'              => $centralBranch->id,
                'received_by'            => $user->id,
                'received_at'            => $request->received_at,
                'supplier_id'            => $request->supplier_id ?: $purchaseOrder?->supplier_id,
                'purchase_order_id'      => $purchaseOrder?->id,
                'delivery_note_number'  => $request->delivery_note_number,
                'invoice_number'        => $request->invoice_number,
                'vehicle_number'        => $request->vehicle_number,
                'seal_code'             => $request->seal_code,
                'quality_status'        => $request->input('quality_status', 'pending'),
                'quality_notes'         => $request->quality_notes,
                'status'                 => $status,
                'total_expected_qty'     => $totalExpected,
                'total_actual_qty'       => $totalActual,
                'total_discrepancy_qty'  => $discrepancyQty,
                'evidence_paths'         => $evidencePaths,
                'notes'                  => $request->notes,
            ]);

            // Tạo items
            foreach ($request->items as $item) {
                $itemExpected = (float) $item['expected_qty'];
                $itemActual   = (float) $item['actual_qty'];
                $diff         = $itemActual - $itemExpected;

                $itemStatus = 'ok';
                if ($diff < -0.001) $itemStatus = 'short';
                elseif ($diff > 0.001) $itemStatus = 'over';

                WarehouseReceivingVoucherItem::create([
                    'voucher_id'          => $voucher->id,
                    'ingredient_id'       => $item['ingredient_id'],
                    'expected_qty'        => $itemExpected,
                    'actual_qty'          => $itemActual,
                    'unit_cost'           => $item['unit_cost'] ?? 0,
                    'item_status'         => $itemStatus,
                    'discrepancy_reason'  => $item['discrepancy_reason'] ?? null,
                    'lot_number'          => $item['lot_number'] ?? null,
                    'expiry_date'         => $item['expiry_date'] ?? null,
                    'location_id'         => $item['location_id'] ?? null,
                ]);
            }

            $this->logAudit($user, 'warehouse.receiving.created', $voucher, [
                'voucher_code'  => $voucher->voucher_code,
                'has_discrepancy' => $hasDiscrepancy,
                'total_items'   => count($request->items),
            ]);

            return response()->json([
                'message' => 'Phiếu nhận hàng ' . $voucher->voucher_code . ' đã tạo thành công.',
                'voucher' => $voucher->load(['items.ingredient.unit', 'items.location', 'receivedBy', 'supplier', 'purchaseOrder']),
            ], 201);
        });
    }

    /**
     * Xác nhận phiếu nhận hàng (không có chênh lệch hoặc đã giải trình)
     */
    public function confirmReceiving(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'),
            403,
            'Bạn không có quyền xác nhận phiếu nhận hàng.'
        );

        $request->validate([
            'notes' => 'nullable|string|max:500',
            'quality_status' => 'required|in:passed,conditional,failed',
            'quality_notes' => 'nullable|string|max:1000',
        ]);

        if ($request->input('quality_status') === 'failed') {
            return response()->json([
                'message' => 'Lô hàng không đạt chất lượng không được phép hạch toán nhập kho. Hãy lập biên bản và xử lý trả/tiêu hủy.',
            ], 422);
        }

        if ($request->input('quality_status') === 'conditional' && blank($request->input('quality_notes'))) {
            return response()->json([
                'message' => 'Hàng đạt có điều kiện phải có ghi chú xử lý chất lượng hoặc thời hạn cách ly.',
            ], 422);
        }

        $user    = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $canVerifyAny = true;

        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->when(! $canVerifyAny, fn ($query) => $query->where('received_by', $user->id))
            ->whereIn('status', ['draft', 'discrepancy', 'pending_review'])
            ->findOrFail($id);

        if ($voucher->items()->where('actual_qty', '>', 0)->whereNull('location_id')->exists()) {
            return response()->json([
                'message' => 'Phiếu còn dòng hàng chưa được gắn vị trí cất hàng. Hãy bổ sung vị trí trước khi nhập kho.',
            ], 422);
        }

        if ($voucher->status === 'discrepancy' && empty($request->input('notes'))) {
            return response()->json([
                'message' => 'Phiếu nhận hàng có chênh lệch so với đơn đặt. Bắt buộc nhập ghi chú giải trình trước khi xác nhận nhập kho.',
            ], 422);
        }

        DB::transaction(function () use (&$voucher, $id, $user, $centralBranch, $canVerifyAny, $request): void {
            // Khóa phiếu trong transaction để hai người không thể đồng thời hạch toán
            // cùng một GRN thành hai lần nhập kho.
            $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $centralBranch->id)
                ->when(! $canVerifyAny, fn ($query) => $query->where('received_by', $user->id))
                ->whereIn('status', ['draft', 'discrepancy', 'pending_review'])
                ->lockForUpdate()
                ->findOrFail($id);

            $voucher->loadMissing(['items.location', 'purchaseOrder.items']);

            if ($voucher->items->contains(fn (WarehouseReceivingVoucherItem $item): bool => (float) $item->actual_qty > 0 && $item->location_id === null)) {
                abort(422, 'Phiếu còn dòng hàng chưa được gắn vị trí cất hàng. Hãy bổ sung vị trí trước khi nhập kho.');
            }

            if ($voucher->status === 'discrepancy' && blank($request->input('notes'))) {
                abort(422, 'Phiếu nhận hàng có chênh lệch so với đơn đặt. Bắt buộc nhập ghi chú giải trình trước khi xác nhận nhập kho.');
            }

            foreach ($voucher->items as $voucherItem) {
                $actualQty = (float) $voucherItem->actual_qty;
                if ($actualQty <= 0) {
                    continue;
                }

                $ingredient = $this->centralIngredientQuery($user->restaurant_id, $centralBranch->id)
                    ->findOrFail($voucherItem->ingredient_id);

                $inventory = Inventory::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $voucher->branch_id)
                    ->where('ingredient_id', $ingredient->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    $inventory = Inventory::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $voucher->branch_id,
                        'ingredient_id' => $ingredient->id,
                        'quantity_on_hand' => 0,
                        'theoretical_quantity' => 0,
                        'last_cost' => 0,
                    ]);
                    $inventory = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
                }

                $unitCost = (float) ($voucherItem->unit_cost ?: $ingredient->average_cost ?: 0);
                $oldQty = (float) $inventory->quantity_on_hand;
                $oldAverageCost = (float) ($ingredient->average_cost ?: $inventory->last_cost ?: $unitCost);
                $newAverageCost = ($oldQty + $actualQty) > 0
                    ? (($oldQty * $oldAverageCost) + ($actualQty * $unitCost)) / ($oldQty + $actualQty)
                    : $unitCost;

                $transaction = InventoryTransaction::createWithIdempotency([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $voucher->branch_id,
                    'ingredient_id' => $ingredient->id,
                    'inventory_id' => $inventory->id,
                    'supplier_id' => $voucher->supplier_id,
                    'performed_by' => $user->id,
                    'type' => 'purchase',
                    'direction' => 'in',
                    'quantity' => $actualQty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($actualQty * $unitCost, 2),
                    'source_type' => 'warehouse_receiving_voucher',
                    'source_id' => $voucher->id,
                    'idempotency_key' => "grn_{$voucher->id}_item_{$voucherItem->id}",
                    'notes' => "Nhập hàng theo phiếu {$voucher->voucher_code}",
                    'occurred_at' => $voucher->received_at ?: now(),
                ]);

                $inventory->update([
                    'quantity_on_hand' => $oldQty + $actualQty,
                    'theoretical_quantity' => (float) ($inventory->theoretical_quantity ?? $oldQty) + $actualQty,
                    'last_cost' => $unitCost,
                ]);
                $ingredient->update(['average_cost' => round($newAverageCost, 2)]);

                $location = $voucherItem->location;
                $expiryDate = $voucherItem->expiry_date
                    ? Carbon::parse($voucherItem->expiry_date)->toDateString()
                    : null;
                $isExpired = $expiryDate !== null && $expiryDate < now()->toDateString();
                $isQuarantine = (bool) $location?->is_quarantine || $request->input('quality_status') === 'conditional';

                $batch = InventoryBatch::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $voucher->branch_id,
                    'location_id' => $voucherItem->location_id,
                    'ingredient_id' => $ingredient->id,
                    'batch_number' => $voucherItem->lot_number ?: $voucher->voucher_code.'-'.$voucherItem->id,
                    'quantity_remaining' => $actualQty,
                    'unit_cost' => $unitCost,
                    'purchased_at' => optional($voucher->received_at)->toDateString() ?: now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'supplier_id' => $voucher->supplier_id,
                    'status' => $isExpired ? 'expired' : ($isQuarantine ? 'locked' : 'active'),
                    'lock_reason' => $isQuarantine
                        ? ($request->input('quality_status') === 'conditional'
                            ? 'Hàng nhập được chấp nhận có điều kiện, chờ xử lý chất lượng.'
                            : 'Hàng mới nhập đang ở vị trí cách ly, chờ kiểm tra.')
                        : null,
                    'locked_by' => $isQuarantine ? $user->id : null,
                    'locked_at' => $isQuarantine ? now() : null,
                ]);

                InventoryBatchAllocation::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $voucher->branch_id,
                    'inventory_batch_id' => $batch->id,
                    'inventory_transaction_id' => $transaction->id,
                    'direction' => 'in',
                    'quantity' => $actualQty,
                    'unit_cost' => $unitCost,
                ]);

                $voucherItem->update(['batch_id' => $batch->id]);

                $purchaseOrderItem = $voucher->purchaseOrder?->items
                    ?->first(fn (PurchaseOrderItem $item): bool => (int) $item->ingredient_id === (int) $voucherItem->ingredient_id);
                if ($purchaseOrderItem) {
                    $newReceivedQty = (float) $purchaseOrderItem->quantity_received + $actualQty;
                    $purchaseOrderItem->update(['quantity_received' => $newReceivedQty]);
                    $purchaseOrderItem->quantity_received = $newReceivedQty;
                }
            }

            if ($voucher->purchaseOrder) {
                $purchaseOrder = $voucher->purchaseOrder->fresh('items');
                if ($purchaseOrder->items->isNotEmpty() && $purchaseOrder->items->every(
                    fn (PurchaseOrderItem $item): bool => (float) $item->quantity_received + 0.0005 >= (float) $item->quantity_ordered,
                )) {
                    $purchaseOrder->update([
                        'status' => 'delivered',
                        'delivered_at' => $voucher->received_at ?: now(),
                    ]);
                }
            }

            $voucher->update([
                'status' => 'confirmed',
                'notes' => $request->notes ?? $voucher->notes,
                'quality_status' => $request->quality_status,
                'quality_notes' => $request->quality_notes ?? $voucher->quality_notes,
                'verified_by' => $canVerifyAny ? $user->id : $voucher->verified_by,
                'verified_at' => $canVerifyAny ? now() : $voucher->verified_at,
            ]);
        });

        $this->logAudit($user, 'warehouse.receiving.confirmed', $voucher, [
            'voucher_code' => $voucher->voucher_code,
        ]);

        return response()->json(['message' => 'Phiếu nhận hàng đã được xác nhận.']);
    }

    /**
     * Báo chênh lệch và yêu cầu xem xét
     */
    public function reportDiscrepancy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'discrepancy_reason' => 'required|string|max:1000',
            'evidence'           => 'nullable|array',
            'evidence.*'         => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user    = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->where('received_by', $user->id)
            ->findOrFail($id);

        $evidencePaths = $voucher->evidence_paths ?? [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $evidencePaths[] = $file->store('warehouse/grn-discrepancy/' . now()->format('Y/m'), 'public');
            }
        }

        $voucher->update([
            'status'              => 'pending_review',
            'discrepancy_reason'  => $request->discrepancy_reason,
            'evidence_paths'      => $evidencePaths,
        ]);

        $this->logAudit($user, 'warehouse.receiving.discrepancy_reported', $voucher, [
            'voucher_code'        => $voucher->voucher_code,
            'discrepancy_reason'  => $request->discrepancy_reason,
        ]);

        return response()->json(['message' => 'Chênh lệch đã được báo cáo, chờ Trưởng kho xử lý.']);
    }

    /**
     * Xác nhận cất hàng (putaway) với vị trí cụ thể
     */
    public function confirmPutaway(Request $request, int $taskId): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|integer|exists:warehouse_locations,id',
            'batch_id'    => 'nullable|integer',
            'notes'       => 'nullable|string|max:500',
            'scan_log'    => 'nullable|array',
        ]);

        $user = $request->user();
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('assigned_to', $user->id)
            ->where('task_type', 'putaway')
            ->findOrFail($taskId);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        abort_unless(
            $task->receiving_voucher_id === null
                || WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $centralBranch->id)
                    ->whereKey($task->receiving_voucher_id)
                    ->exists(),
            403,
            'Task cất hàng không thuộc Kho Tổng.'
        );
        abort_unless(
            $centralBranch && $this->centralLocationQuery($user->restaurant_id, $centralBranch->id)
                ->whereKey((int) $request->location_id)
                ->exists(),
            422,
            'Vá»‹ trÃ­ cất hÃ ng khÃ´ng thuá»™c Kho Tá»•ng.'
        );

        $scanLog = array_merge($task->scan_log ?? [], $request->scan_log ?? []);

        $task->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'result_notes' => $request->notes,
            'scan_log'     => $scanLog,
        ]);

        $this->logAudit($user, 'warehouse.putaway.confirmed', $task, [
            'task_id'     => $task->id,
            'location_id' => $request->location_id,
            'scan_count'  => count($scanLog),
        ]);

        return response()->json(['message' => 'Cất hàng thành công!']);
    }

    // ── 4. Báo Sự Cố ─────────────────────────────────────────────────────────

    /**
     * Báo sự cố / hỏng hóc / thiếu hụt
     */
    public function reportIncident(Request $request): JsonResponse
    {
        $request->validate([
            'incident_type'    => 'required|in:shortage,damage,expired,wrong_item,other',
            'ingredient_id'    => ['nullable', 'integer', TenantRule::exists('ingredients')],
            'batch_id'         => 'nullable|integer',
            'location_id'      => 'nullable|integer',
            'description'      => 'required|string|max:2000',
            'quantity_affected' => 'nullable|numeric|min:0',
            'evidence'         => 'nullable|array',
            'evidence.*'       => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        if ($request->filled('ingredient_id')) {
            abort_unless(
                $centralBranch && $this->centralIngredientQuery($restaurantId, $centralBranch->id)
                    ->whereKey((int) $request->ingredient_id)
                    ->exists(),
                422,
                'NguyÃªn liá»‡u báo sá»± cá»‘ pháº£i thuá»™c Kho Tá»•ng hoáº·c catalog toÃ n chuá»—i.'
            );
        }

        if ($request->filled('batch_id')) {
            $batch = InventoryBatch::where('restaurant_id', $restaurantId)
                ->where('branch_id', $centralBranch?->id)
                ->findOrFail((int) $request->batch_id);
            abort_unless(
                ! $request->filled('ingredient_id') || (int) $batch->ingredient_id === (int) $request->ingredient_id,
                422,
                'Lô hàng không khớp với nguyên liệu báo sự cố.'
            );
        }

        if ($request->filled('location_id')) {
            abort_unless(
                $centralBranch && $this->centralLocationQuery($restaurantId, $centralBranch->id)
                    ->whereKey((int) $request->location_id)
                    ->exists(),
                422,
                'Vá»‹ trÃ­ báo sá»± cá»‘ pháº£i thuá»™c Kho Tá»•ng.'
            );
        }

        $evidencePaths = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $evidencePaths[] = $file->store('warehouse/incidents/' . now()->format('Y/m'), 'public');
            }
        }

        // Tạo incident task cho nhân viên (loại incident)
        $task = WarehouseTaskAssignment::create([
            'restaurant_id'  => $restaurantId,
            'assigned_to'    => $user->id,
            'assigned_by'    => $user->id,
            'task_type'      => 'incident',
            'status'         => 'in_progress',
            'priority'       => 'high',
            'notes'          => $request->description,
            'evidence_paths' => $evidencePaths,
            'started_at'     => now(),
            'result_notes'   => json_encode([
                'incident_type'     => $request->incident_type,
                'ingredient_id'     => $request->ingredient_id,
                'batch_id'          => $request->batch_id,
                'location_id'       => $request->location_id,
                'quantity_affected' => $request->quantity_affected,
            ]),
        ]);

        $this->logAudit($user, 'warehouse.incident.reported', $task, [
            'incident_type'    => $request->incident_type,
            'description'      => $request->description,
            'evidence_count'   => count($evidencePaths),
        ]);

        return response()->json([
            'message' => 'Sự cố đã được báo cáo. Trưởng kho sẽ được thông báo.',
            'task_id' => $task->id,
        ], 201);
    }

    // ── 5. Bàn Giao Ca ───────────────────────────────────────────────────────

    /**
     * Xem bàn giao ca hiện tại / gần đây của tôi
     */
    public function myShiftHandover(Request $request): JsonResponse
    {
        $user = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        $handover = WarehouseShiftHandover::where('restaurant_id', $user->restaurant_id)
            ->where(fn ($q) => $q->where('handover_by', $user->id)->orWhere('received_by', $user->id))
            ->where('branch_id', $centralBranch->id)
            ->with(['handoverBy', 'receivedBy', 'branch'])
            ->orderByDesc('id')
            ->first();

        // Task chưa hoàn thành của tôi (cho cảnh báo khi bàn giao ca)
        $pendingTasks = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->myTasks($user->id)
            ->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            })
            ->pending()
            ->get(['id', 'task_type', 'status', 'due_at', 'notes']);

        return response()->json([
            'latest_handover' => $handover,
            'pending_tasks'   => $pendingTasks,
            'can_handover'    => $pendingTasks->isEmpty() || $pendingTasks->count() <= 3,
        ]);
    }

    /**
     * Nộp bàn giao ca cuối
     */
    public function submitShiftHandover(Request $request): JsonResponse
    {
        $request->validate([
            'shift_date'   => 'required|date',
            'shift_type'   => 'required|in:morning,afternoon,evening,night',
            'shift_label'  => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:2000',
            'received_by'  => ['nullable', 'integer', TenantRule::exists('users')],
            'incidents_json' => 'nullable|array',
        ]);

        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        if ($request->filled('received_by')) {
            $recipient = User::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->whereKey((int) $request->received_by)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['warehouse_manager', 'warehouse_staff']))
                ->where(function ($query) use ($centralBranch) {
                    $query->where('warehouse_branch_id', $centralBranch->id)
                        ->orWhere('branch_id', $centralBranch->id);
                })
                ->exists();
            abort_unless($recipient, 422, 'Người nhận ca phải là nhân sự Kho Tổng đang hoạt động.');
        }

        // Task chưa hoàn thành
        $pendingTasks = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->myTasks($user->id)
            ->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            })
            ->pending()
            ->get();

        // Kiểm tra tồn kho snapshot
        $stockSnapshot = Inventory::where('restaurant_id', $restaurantId)
            ->where('branch_id', $centralBranch->id)
            ->with(['ingredient:id,name,sku'])
            ->get(['ingredient_id', 'quantity_on_hand'])
            ->map(fn ($inv) => [
                'ingredient_id'   => $inv->ingredient_id,
                'ingredient_name' => $inv->ingredient?->name,
                'quantity_on_hand' => $inv->quantity_on_hand,
            ]);

        $isSystemLocked = $pendingTasks->count() > 5; // Ngưỡng cảnh báo: > 5 task chưa xong

        $handover = WarehouseShiftHandover::create([
            'restaurant_id'      => $restaurantId,
            'branch_id'          => $centralBranch->id,
            'shift_date'         => $request->shift_date,
            'shift_type'         => $request->shift_type,
            'shift_label'        => $request->shift_label,
            'handover_by'        => $user->id,
            'received_by'        => $request->received_by,
            'status'             => $request->received_by ? 'pending' : 'draft',
            'notes'              => $request->notes,
            'open_tasks_json'    => $pendingTasks->map(fn ($t) => [
                'id'        => $t->id,
                'task_type' => $t->task_type,
                'status'    => $t->status,
                'due_at'    => $t->due_at?->toISOString(),
            ])->values()->all(),
            'incidents_json'     => $request->incidents_json ?? [],
            'stock_snapshot_json' => $stockSnapshot->toArray(),
            'is_system_locked'   => $isSystemLocked,
            'lock_reason'        => $isSystemLocked
                ? 'Còn ' . $pendingTasks->count() . ' task chưa hoàn thành trong ca.'
                : null,
            'pending_picks_count'     => $pendingTasks->where('task_type', 'picking')->count(),
            'pending_deliveries_count' => $pendingTasks->where('task_type', 'handover')->count(),
        ]);

        $this->logAudit($user, 'warehouse.shift_handover.submitted', $handover, [
            'shift_date'          => $request->shift_date,
            'shift_type'          => $request->shift_type,
            'pending_tasks_count' => $pendingTasks->count(),
            'is_system_locked'    => $isSystemLocked,
        ]);

        return response()->json([
            'message'          => 'Bàn giao ca đã được nộp.',
            'handover'         => $handover,
            'is_system_locked' => $isSystemLocked,
            'pending_tasks'    => $pendingTasks->count(),
        ], 201);
    }

    /**
     * Người nhận ca xác nhận bàn giao
     */
    public function confirmShiftHandover(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $user     = $request->user();
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $handover = WarehouseShiftHandover::where('restaurant_id', $user->restaurant_id)
            ->where('received_by', $user->id)
            ->where('branch_id', $centralBranch->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        // Tạo hash xác nhận
        $hash = hash('sha256', $user->id . '|' . $handover->id . '|' . now()->timestamp);

        $handover->update([
            'status'                => 'confirmed',
            'signed_at'             => now(),
            'acknowledgment_hash'   => $hash,
            'notes'                 => ($handover->notes ?? '') . "\n[Người nhận ca]: " . ($request->notes ?? ''),
        ]);

        $this->logAudit($user, 'warehouse.shift_handover.confirmed', $handover, [
            'handover_id'   => $handover->id,
            'handover_by'   => $handover->handover_by,
            'received_by'   => $user->id,
            'hash'          => $hash,
        ]);

        return response()->json(['message' => 'Bàn giao ca đã được xác nhận.']);
    }

    // ── 6. Lịch Sử Của Tôi ───────────────────────────────────────────────────

    /**
     * Lịch sử thao tác của nhân viên (chỉ của mình)
     */
    public function myHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $history = AuditLog::where('restaurant_id', $user->restaurant_id)
            ->where('user_id', $user->id)
            ->whereIn('action', [
                'warehouse.receiving.created',
                'warehouse.receiving.confirmed',
                'warehouse.receiving.discrepancy_reported',
                'warehouse.putaway.confirmed',
                'warehouse.incident.reported',
                'warehouse.shift_handover.submitted',
                'warehouse.shift_handover.confirmed',
                'warehouse_task.start',
                'warehouse_task.complete',
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'action', 'metadata', 'created_at', 'ip_address']);

        return response()->json(['history' => $history]);
    }

    // ── 7. Quét Mã ───────────────────────────────────────────────────────────

    /**
     * Resolve một mã quét (QR/barcode) → trả về metadata
     */
    public function scanCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:200',
        ]);

        $code = trim($request->code);
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        // Thử match ingredient SKU
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $ingredient = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->where('sku', $code)
            ->with(['unit'])
            ->first();

        if ($ingredient) {
            return response()->json([
                'type'   => 'ingredient',
                'id'     => $ingredient->id,
                'name'   => $ingredient->name,
                'sku'    => $ingredient->sku,
                'unit'   => $ingredient->unit?->symbol,
                'status' => 'found',
            ]);
        }

        // Thử match batch code
        $batch = InventoryBatch::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('batch_number', $code)
            ->with(['ingredient'])
            ->first();

        if ($batch) {
            $batchStatus = 'ok';
            if ($batch->is_locked) {
                $batchStatus = 'locked';
            } elseif ($batch->expiry_date && $batch->expiry_date < now()) {
                $batchStatus = 'expired';
            }

            return response()->json([
                'type'          => 'batch',
                'id'            => $batch->id,
                'batch_number'  => $batch->batch_number,
                'ingredient_id' => $batch->ingredient_id,
                'ingredient_name' => $batch->ingredient?->name,
                'expiry_date'   => $batch->expiry_date,
                'quantity'      => $batch->quantity,
                'status'        => $batchStatus,
                'is_locked'     => $batch->is_locked,
                'warning'       => $batchStatus !== 'ok'
                    ? ($batchStatus === 'locked' ? 'Lô này đã bị khóa!' : 'Lô này đã hết hạn!')
                    : null,
            ]);
        }

        // Thử match warehouse location code
        $location = $this->centralLocationQuery($restaurantId, $centralBranch?->id)
            ->where('code', $code)
            ->first();

        if ($location) {
            return response()->json([
                'type'        => 'location',
                'id'          => $location->id,
                'code'        => $location->code,
                'name'        => $location->name,
                'zone'        => $location->zone,
                'is_cold'     => $location->is_cold_storage ?? false,
                'is_quarantine' => $location->is_quarantine ?? false,
                'status'      => 'found',
            ]);
        }

        return response()->json([
            'type'    => 'unknown',
            'code'    => $code,
            'status'  => 'not_found',
            'message' => 'Không tìm thấy mã: ' . $code,
        ], 404);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function centralIngredientQuery(int $restaurantId, ?int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->when(
                $centralBranchId,
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $centralBranchId)),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    private function centralLocationQuery(int $restaurantId, ?int $centralBranchId)
    {
        return WarehouseLocation::where('restaurant_id', $restaurantId)
            ->when(
                $centralBranchId,
                fn ($query) => $query->where('branch_id', $centralBranchId),
                fn ($query) => $query->whereRaw('1 = 0'),
            );
    }

    private function formatTask(WarehouseTaskAssignment $task): array
    {
        return [
            'id'               => $task->id,
            'task_type'        => $task->task_type,
            'status'           => $task->status,
            'priority'         => $task->priority,
            'due_at'           => $task->due_at?->toISOString(),
            'started_at'       => $task->started_at?->toISOString(),
            'completed_at'     => $task->completed_at?->toISOString(),
            'notes'            => $task->notes,
            'result_notes'     => $task->result_notes,
            'evidence_count'   => count($task->evidence_paths ?? []),
            'is_overdue'       => $task->isOverdue(),
            'duration_minutes' => $task->duration(),
            'supply_request'   => $task->supplyRequest ? [
                'id'           => $task->supplyRequest->id,
                'request_code' => $task->supplyRequest->request_code,
                'to_branch'    => $task->supplyRequest->toBranch?->name,
                'status'       => $task->supplyRequest->status,
            ] : null,
        ];
    }

    private function logAudit($user, string $action, $model, array $metadata = []): void
    {
        try {
            AuditLog::create([
                'restaurant_id' => $user->restaurant_id,
                'user_id'       => $user->id,
                'action'        => $action,
                'auditable_type' => get_class($model),
                'auditable_id'  => $model->id,
                'metadata'      => array_merge($metadata, [
                    'ip_address'   => request()->ip(),
                    'user_agent'   => substr(request()->userAgent() ?? '', 0, 255),
                    'performed_at' => now()->toISOString(),
                ]),
                'ip_address'    => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Không để lỗi audit làm gián đoạn luồng nghiệp vụ
        }
    }

    /**
     * Phân công soạn hàng/nhiệm vụ kho nhanh theo tải công việc.
     */
    public function quickAutoAssignTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_warehouse') || $user->hasAnyRole(['warehouse_manager', 'manager', 'owner', 'super_admin']), 403);

        $restaurantId = $user->restaurant_id;
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId)
            ?? \App\Models\RestaurantBranch::where('restaurant_id', $restaurantId)->first();
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');

        $unassignedTasks = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            })
            ->where(function ($q) {
                $q->whereNull('assigned_to')
                  ->orWhere('status', 'pending');
            })
            ->orderBy('due_at', 'asc')
            ->get();

        if ($unassignedTasks->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không có nhiệm vụ kho nào đang chờ phân công.',
            ], 422);
        }

        // Lấy danh sách nhân viên kho active
        $warehouseStaff = \App\Models\User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($query) use ($centralBranch) {
                $query->whereHas('roles', fn ($q) => $q->where('name', 'warehouse_staff'))
                    ->orWhere('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->get();

        if ($warehouseStaff->isEmpty()) {
            $warehouseStaff = collect([$user]);
        }

        // Đếm công việc hiện tại của từng nhân viên
        $staffWorkload = [];
        foreach ($warehouseStaff as $staffUser) {
            $count = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
                ->where('assigned_to', $staffUser->id)
                ->where(function ($scope) use ($centralBranch) {
                    $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                        ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                        ->orWhere(function ($unlinked) {
                            $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                        });
                })
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();
            $staffWorkload[$staffUser->id] = $count;
        }

        $assignedCount = 0;
        foreach ($unassignedTasks as $task) {
            // Chọn nhân viên có ít task nhất
            asort($staffWorkload);
            $bestStaffId = array_key_first($staffWorkload);

            $task->update([
                'assigned_to' => $bestStaffId,
                'status' => 'assigned',
            ]);

            $staffWorkload[$bestStaffId]++;
            $assignedCount++;
        }

        AuditLog::log(
            'warehouse_tasks_auto_assigned',
            'updated',
            $unassignedTasks->first(),
            null,
            ['assigned_count' => $assignedCount]
        );

        return response()->json([
            'success' => true,
            'message' => "Đã tự động phân công thành công {$assignedCount} nhiệm vụ kho cho nhân viên.",
            'assigned_count' => $assignedCount,
        ]);
    }
}
