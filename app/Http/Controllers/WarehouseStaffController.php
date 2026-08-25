<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryBatchAllocation;
use App\Models\InventoryDiscrepancyDispute;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Models\WarehouseReceivingDocument;
use App\Models\WarehouseReceivingVoucher;
use App\Models\WarehouseReceivingVoucherItem;
use App\Models\WarehouseShiftHandover;
use App\Models\WarehouseTaskAssignment;
use App\Notifications\WarehouseShiftHandoverPendingNotification;
use App\Notifications\WarehouseTaskAssignedNotification;
use App\Services\CentralWarehouseService;
use App\Services\WarehouseReverseLogisticsService;
use App\Services\WarehouseStaffAccessService;
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
        protected WarehouseStaffAccessService $staffAccess,
    ) {}

    // ── 1. Trang Portal Nhân Viên Kho ────────────────────────────────────────

    /**
     * Render trang portal dành riêng cho nhân viên kho (mobile-first).
     * Chỉ trả dữ liệu liên quan đến người dùng hiện tại.
     */
    public function staffPortalPage(Request $request): Response
    {
        $user = $request->user();
        $this->staffAccess->assertCanAccessCentral($user);
        $restaurantId = $user->restaurant_id;
        $userId = $user->id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        // Task của tôi hôm nay + pending
        $myTasksRaw = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->myTasks($userId)
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['supplyRequest.toBranch', 'supplyRequest.items.ingredient.unit', 'supplyRequest.items.batch', 'receivingVoucher.items.batch', 'receivingVoucher.items.ingredient', 'receivingVoucher.items.location', 'assignee', 'countSession'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $taskSummary = [
            'total' => $myTasksRaw->count(),
            'pending' => $myTasksRaw->whereIn('status', ['assigned'])->count(),
            'in_progress' => $myTasksRaw->where('status', 'in_progress')->count(),
            'completed_today' => $myTasksRaw->where('status', 'completed')
                ->filter(fn ($t) => $t->completed_at?->isToday())
                ->count(),
            'overdue' => $myTasksRaw->filter(fn ($t) => $t->isOverdue())->count(),
        ];

        $myTasks = $myTasksRaw->map(fn ($t) => $this->formatTask($t))->values();

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

        $myDisputes = InventoryDiscrepancyDispute::where('restaurant_id', $restaurantId)
            ->where('responsible_user_id', $userId)
            ->whereIn('status', ['open', 'investigating', 'resolved'])
            ->with(['ingredient', 'supplyRequest.toBranch'])
            ->orderByDesc('id')
            ->limit(20)
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

        $suppliers = Supplier::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch?->id ?: -1))
            ->orderBy('name')
            ->get(['id', 'name']);

        $purchaseOrders = PurchaseOrder::where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'preparing', 'shipping'])
            ->where('is_frozen', false)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $centralBranch?->id ?: -1))
            ->with('supplier:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'po_number', 'supplier_id', 'status', 'delivery_due_date']);

        $notifications = $user->unreadNotifications()
            ->latest()
            ->limit(20)
            ->get();

        $handoverRecipients = User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('warehouse_staff_status')
                    ->orWhere('warehouse_staff_status', 'active');
            })
            ->where('id', '!=', $userId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['warehouse_manager', 'warehouse_staff']))
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            }))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $canManage = $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage');

        return Inertia::render('inventory/WarehouseStaffPortal', [
            'centralBranch' => $centralBranch,
            'myTasks' => $myTasks,
            'taskSummary' => $taskSummary,
            'myVouchers' => $myVouchers,
            'myHandovers' => $myHandovers,
            'myDisputes' => $myDisputes,
            'locations' => $locations,
            'ingredients' => $ingredients,
            'suppliers' => $suppliers,
            'purchaseOrders' => $purchaseOrders,
            'notifications' => $notifications,
            'handoverRecipients' => $handoverRecipients,
            'canManageWarehouse' => $canManage,
            'currentUser' => [
                'id' => $userId,
                'name' => $user->name,
                'job_title' => $user->employee?->job_title ?: 'Nhân viên Kho Tổng',
                'avatar_url' => $user->avatar_url,
                'warehouse_staff_status' => $user->warehouse_staff_status ?? 'active',
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
        $this->staffAccess->assertCanAccessCentral($user);
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
            ->with(['supplyRequest.toBranch', 'supplyRequest.items.ingredient.unit', 'supplyRequest.items.batch', 'receivingVoucher.items.batch', 'receivingVoucher.items.ingredient', 'receivingVoucher.items.location'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->get()
            ->map(fn ($t) => $this->formatTask($t));

        return response()->json([
            'tasks' => $tasks,
            'summary' => [
                'total' => $tasks->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'assigned' => $tasks->where('status', 'assigned')->count(),
                'overdue' => $tasks->filter(fn ($t) => $t['is_overdue'])->count(),
                'completed_today' => $tasks->where('status', 'completed')
                    ->filter(fn ($t) => isset($t['completed_at']) && Carbon::parse($t['completed_at'])->isToday())
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
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $task = DB::transaction(function () use ($user, $centralBranch, $id): WarehouseTaskAssignment {
            $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('assigned_to', $user->id)
                ->where(function ($scope) use ($centralBranch) {
                    $scope->when($centralBranch, fn ($query) => $query->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                        ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                        ->orWhere(function ($unlinked) {
                            $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                        }), fn ($query) => $query->whereRaw('1 = 0'));
                })
                ->lockForUpdate()
                ->findOrFail($id);

            abort_if($task->status === 'completed', 422, 'Task đã hoàn thành, không thể bắt đầu lại.');
            abort_if($task->status === 'in_progress', 422, 'Task đang được thực hiện.');
            abort_if($task->status === 'cancelled', 422, 'Task đã bị huỷ, không thể bắt đầu.');
            abort_unless(in_array($task->status, ['assigned', 'pending'], true), 422, 'Task chưa ở trạng thái có thể bắt đầu.');

            $task->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return $task;
        });

        $this->logAudit($user, 'warehouse_task.start', $task, [
            'task_type' => $task->task_type,
            'task_id' => $task->id,
        ]);

        return response()->json(['message' => 'Bắt đầu task thành công.', 'task' => $this->formatTask($task->fresh())]);
    }

    /**
     * Hoàn thành một task + upload evidence
     */
    public function completeTask(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'idempotency_key' => 'nullable|string|max:80',
            'result_notes' => 'nullable|string|max:1000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $idempotencyKey = $request->input('idempotency_key');
        $task = DB::transaction(function () use ($request, $user, $centralBranch, $id, $idempotencyKey): WarehouseTaskAssignment {
            $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('assigned_to', $user->id)
                ->where(function ($scope) use ($centralBranch) {
                    $scope->when($centralBranch, fn ($query) => $query->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                        ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                        ->orWhere(function ($unlinked) {
                            $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                        }), fn ($query) => $query->whereRaw('1 = 0'));
                })
                ->lockForUpdate()
                ->findOrFail($id);

            if ($task->status === 'completed' && $idempotencyKey && $task->idempotency_key === $idempotencyKey) {
                return $task;
            }

            abort_if($task->status === 'completed', 422, 'Task đã hoàn thành rồi.');
            abort_if($task->status === 'cancelled', 422, 'Task đã bị huỷ, không thể hoàn tất.');
            abort_unless($task->status === 'in_progress', 422, 'Task phải được bắt đầu trước khi hoàn tất.');

            $evidencePaths = $task->evidence_paths ?? [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $evidencePaths[] = $file->store('warehouse/evidence/'.now()->format('Y/m'), 'local');
                }
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_notes' => $request->result_notes,
                'evidence_paths' => $evidencePaths,
                'idempotency_key' => $idempotencyKey ?: $task->idempotency_key,
            ]);

            return $task;
        });

        if ($task->status === 'completed' && $idempotencyKey && $task->idempotency_key === $idempotencyKey && $task->wasChanged('status') === false) {
            return response()->json([
                'message' => 'Task đã được hoàn tất trước đó.',
                'task' => $this->formatTask($task->fresh()),
                'idempotent_replay' => true,
            ]);
        }

        $evidencePaths = $task->evidence_paths ?? [];

        $this->logAudit($user, 'warehouse_task.complete', $task, [
            'task_type' => $task->task_type,
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
            'received_at' => 'required|date',
            'idempotency_key' => 'nullable|string|max:64',
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers')],
            'purchase_order_id' => ['nullable', 'integer', TenantRule::exists('purchase_orders')],
            'delivery_note_number' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_series' => 'nullable|string|max:80',
            'invoice_date' => 'nullable|date',
            'invoice_total_amount' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0',
            'vehicle_number' => 'nullable|string|max:50',
            'seal_code' => 'nullable|string|max:50',
            'carrier_name' => 'nullable|string|max:120',
            'receiving_dock' => 'nullable|string|max:60',
            'submit_for_review' => 'nullable|boolean',
            'quality_status' => 'nullable|in:pending,passed,conditional,failed',
            'quality_notes' => 'nullable|string|max:1000',
            'temperature_min_c' => 'nullable|numeric|between:-80,80',
            'temperature_max_c' => 'nullable|numeric|between:-80,80',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => ['required', 'integer', TenantRule::exists('ingredients')],
            'items.*.expected_qty' => 'required|numeric|min:0',
            'items.*.actual_qty' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.lot_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.location_id' => ['nullable', 'integer', TenantRule::exists('warehouse_locations')],
            'items.*.discrepancy_reason' => 'nullable|string|max:500',
            'items.*.manufactured_date' => 'nullable|date',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf,webp|max:10240',
            'evidence_types' => 'nullable|array',
            'evidence_types.*' => 'nullable|in:invoice,delivery_note,qc,receiving_photo,other',
        ], [
            'items.*.ingredient_id.required' => 'Vui lòng chọn nguyên liệu cho tất cả các mặt hàng.',
            'items.*.ingredient_id.integer' => 'Mã nguyên liệu không hợp lệ.',
            'items.*.ingredient_id.exists' => 'Nguyên liệu đã chọn không tồn tại trong hệ thống.',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $restaurantId = $user->restaurant_id;

        if ($request->filled('idempotency_key')) {
            $existing = WarehouseReceivingVoucher::where('restaurant_id', $restaurantId)
                ->where('idempotency_key', $request->string('idempotency_key')->toString())
                ->with(['items.ingredient.unit', 'items.location', 'receivedBy', 'supplier', 'purchaseOrder', 'documents'])
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Phiếu nhận hàng đã được ghi nhận trước đó.',
                    'voucher' => $existing,
                    'idempotent_replay' => true,
                ]);
            }
        }

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
            'Phiếu nhận hàng chỉ được phép dùng nguyên liệu toàn chuỗi hoặc thuộc Kho Tổng.'
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
            'Vị trí cất hàng phải thuộc Kho Tổng.'
        );

        return DB::transaction(function () use ($request, $user, $restaurantId, $centralBranch, $purchaseOrder) {
            // Upload ảnh bằng chứng
            $evidencePaths = [];
            $documentFiles = [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $index => $file) {
                    $path = $file->store('warehouse/grn/'.now()->format('Y/m'), 'local');
                    $evidencePaths[] = $path;
                    $documentFiles[] = [
                        'file' => $file,
                        'path' => $path,
                        'type' => $request->input("evidence_types.{$index}") ?: 'other',
                    ];
                }
            }

            $totalExpected = 0;
            $totalActual = 0;
            $hasDiscrepancy = false;

            // Tính tổng
            foreach ($request->items as $item) {
                $totalExpected += (float) $item['expected_qty'];
                $totalActual += (float) $item['actual_qty'];
                if (abs((float) $item['expected_qty'] - (float) $item['actual_qty']) > 0.001) {
                    $hasDiscrepancy = true;
                }
            }

            $discrepancyQty = $totalActual - $totalExpected;
            $status = $request->boolean('submit_for_review')
                ? 'pending_review'
                : ($hasDiscrepancy ? 'discrepancy' : 'draft');

            $voucher = WarehouseReceivingVoucher::create([
                'restaurant_id' => $restaurantId,
                'idempotency_key' => $request->input('idempotency_key'),
                'branch_id' => $centralBranch->id,
                'received_by' => $user->id,
                'submitted_by' => $request->boolean('submit_for_review') ? $user->id : null,
                'submitted_at' => $request->boolean('submit_for_review') ? now() : null,
                'received_at' => $request->received_at,
                'supplier_id' => $request->supplier_id ?: $purchaseOrder?->supplier_id,
                'purchase_order_id' => $purchaseOrder?->id,
                'delivery_note_number' => $request->delivery_note_number,
                'invoice_number' => $request->invoice_number,
                'invoice_series' => $request->invoice_series,
                'invoice_date' => $request->invoice_date,
                'invoice_total_amount' => $request->input('invoice_total_amount', 0),
                'vat_amount' => $request->input('vat_amount', 0),
                'vehicle_number' => $request->vehicle_number,
                'seal_code' => $request->seal_code,
                'carrier_name' => $request->carrier_name,
                'receiving_dock' => $request->receiving_dock,
                'quality_status' => $request->input('quality_status', 'pending'),
                'quality_notes' => $request->quality_notes,
                'temperature_min_c' => $request->temperature_min_c,
                'temperature_max_c' => $request->temperature_max_c,
                'temperature_status' => $this->temperatureStatus($request->temperature_min_c, $request->temperature_max_c),
                'three_way_match_status' => $purchaseOrder ? 'pending' : 'not_applicable',
                'disposition' => 'pending',
                'status' => $status,
                'total_expected_qty' => $totalExpected,
                'total_actual_qty' => $totalActual,
                'total_discrepancy_qty' => $discrepancyQty,
                'evidence_paths' => $evidencePaths,
                'notes' => $request->notes,
            ]);

            // Tạo items
            foreach ($request->items as $item) {
                $itemExpected = (float) $item['expected_qty'];
                $itemActual = (float) $item['actual_qty'];
                $diff = $itemActual - $itemExpected;

                $itemStatus = 'ok';
                if ($diff < -0.001) {
                    $itemStatus = 'short';
                } elseif ($diff > 0.001) {
                    $itemStatus = 'over';
                }

                WarehouseReceivingVoucherItem::create([
                    'voucher_id' => $voucher->id,
                    'ingredient_id' => $item['ingredient_id'],
                    'expected_qty' => $itemExpected,
                    'actual_qty' => $itemActual,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'item_status' => $itemStatus,
                    'discrepancy_reason' => $item['discrepancy_reason'] ?? null,
                    'lot_number' => $item['lot_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'location_id' => $item['location_id'] ?? null,
                    'manufactured_date' => $item['manufactured_date'] ?? null,
                ]);
            }

            foreach ($documentFiles as $document) {
                $file = $document['file'];
                WarehouseReceivingDocument::create([
                    'restaurant_id' => $restaurantId,
                    'voucher_id' => $voucher->id,
                    'document_type' => $document['type'],
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $document['path'],
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $user->id,
                ]);
            }

            $this->logAudit($user, 'warehouse.receiving.created', $voucher, [
                'voucher_code' => $voucher->voucher_code,
                'has_discrepancy' => $hasDiscrepancy,
                'total_items' => count($request->items),
            ]);

            return response()->json([
                'message' => 'Phiếu nhận hàng '.$voucher->voucher_code.' đã tạo thành công.',
                'voucher' => $voucher->load(['items.ingredient.unit', 'items.location', 'receivedBy', 'supplier', 'purchaseOrder', 'documents']),
            ], 201);
        });
    }

    /**
     * Gửi phiếu nháp sang hàng đợi Trưởng kho duyệt.
     */
    public function submitReceiving(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        $voucher = DB::transaction(function () use ($user, $centralBranch, $id): WarehouseReceivingVoucher {
            $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $centralBranch->id)
                ->where('received_by', $user->id)
                ->whereIn('status', ['draft', 'discrepancy', 'rejected'])
                ->lockForUpdate()
                ->findOrFail($id);

            abort_if($voucher->items()->where('actual_qty', '>', 0)->whereNull('lot_number')->exists(), 422, 'Mỗi dòng có hàng thực nhận phải có số lô để truy xuất.');

            $voucher->update([
                'status' => 'pending_review',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->logAudit($user, 'warehouse.receiving.submitted', $voucher, [
                'voucher_code' => $voucher->voucher_code,
            ]);

            return $voucher->fresh(['documents']);
        });

        return response()->json(['message' => 'Phiếu đã được gửi vào hàng đợi Trưởng kho duyệt.', 'voucher' => $voucher]);
    }

    /**
     * Từ chối phiếu trước khi hạch toán. Tồn kho không thay đổi.
     */
    public function rejectReceiving(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'), 403, 'Bạn không có quyền từ chối phiếu nhập.');
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        $voucher = DB::transaction(function () use ($user, $centralBranch, $id, $data): WarehouseReceivingVoucher {
            $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $centralBranch->id)
                ->whereIn('status', ['draft', 'discrepancy', 'pending_review'])
                ->when(! ($user->isOwner() || $user->isSuperAdmin()), fn ($query) => $query->where(function ($scope) use ($user) {
                    $scope->whereNull('received_by')->orWhere('received_by', '!=', $user->id);
                }))
                ->lockForUpdate()
                ->findOrFail($id);

            $voucher->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $data['reason'],
            ]);

            $this->logAudit($user, 'warehouse.receiving.rejected', $voucher, [
                'voucher_code' => $voucher->voucher_code,
                'reason' => $data['reason'],
            ]);

            return $voucher->fresh();
        });

        return response()->json(['message' => 'Phiếu đã bị từ chối và chưa ghi nhận vào tồn kho.', 'voucher' => $voucher]);
    }

    /**
     * Xem chứng từ GRN qua endpoint có kiểm tra tenant và đúng Kho Tổng.
     */
    public function viewReceivingDocument(Request $request, int $id, int $document): mixed
    {
        $user = $request->user();
        $this->staffAccess->assertCanAccessCentral($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch?->id)
            ->findOrFail($id);
        $document = $voucher->documents()->findOrFail($document);

        if (Storage::disk('local')->exists($document->storage_path)) {
            return Storage::disk('local')->response($document->storage_path, $document->original_name);
        }

        abort_unless(Storage::disk('public')->exists($document->storage_path), 404, 'Không tìm thấy chứng từ.');

        return response()->file(Storage::disk('public')->path($document->storage_path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
        ]);
    }

    /**
     * Tương thích với chứng từ GRN cũ được lưu trong evidence_paths.
     */
    public function viewReceivingEvidence(Request $request, int $id, int $index): mixed
    {
        $user = $request->user();
        $this->staffAccess->assertCanAccessCentral($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch?->id)
            ->findOrFail($id);
        $path = $voucher->evidence_paths[$index] ?? null;
        abort_unless(is_string($path) && $path !== '', 404, 'Không tìm thấy chứng từ.');

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path);
        }

        abort_unless(Storage::disk('public')->exists($path), 404, 'Không tìm thấy chứng từ.');

        return response()->file(Storage::disk('public')->path($path));
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
            'temperature_min_c' => 'nullable|numeric|between:-80,80',
            'temperature_max_c' => 'nullable|numeric|between:-80,80',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($request->input('quality_status') === 'conditional' && blank($request->input('quality_notes'))) {
            return response()->json([
                'message' => 'Hàng đạt có điều kiện phải có ghi chú xử lý chất lượng hoặc thời hạn cách ly.',
            ], 422);
        }

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        // GRN phải có maker-checker: nhân sự tạo phiếu không được tự xác nhận
        // chính phiếu đó. Owner/Super Admin là ngoại lệ xử lý khẩn cấp và vẫn
        // được lưu dấu vết ở verified_by/verified_at.
        $canVerifyOwnVoucher = $user->isOwner() || $user->isSuperAdmin();

        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->when(! $canVerifyOwnVoucher, fn ($query) => $query->where(function ($scope) use ($user) {
                $scope->whereNull('received_by')->orWhere('received_by', '!=', $user->id);
            }))
            ->whereIn('status', ['draft', 'discrepancy', 'pending_review', 'pending_disposition'])
            ->findOrFail($id);

        if ($voucher->status === 'pending_disposition') {
            return response()->json([
                'message' => 'Phiếu đang chờ xử lý trả nhà cung cấp hoặc tiêu hủy.',
                'requires_disposition' => true,
            ], 422);
        }

        if ($request->input('quality_status') === 'failed') {
            $evidencePaths = $voucher->evidence_paths ?? [];
            if ($request->hasFile('evidence')) {
                foreach ($request->file('evidence') as $file) {
                    $evidencePaths[] = $file->store('warehouse/grn-failed/'.now()->format('Y/m'), 'local');
                }
            }

            $voucher->update([
                'status' => 'pending_disposition',
                'quality_status' => 'failed',
                'quality_notes' => $request->quality_notes,
                'temperature_min_c' => $request->temperature_min_c,
                'temperature_max_c' => $request->temperature_max_c,
                'temperature_status' => $this->temperatureStatus($request->temperature_min_c, $request->temperature_max_c),
                'disposition' => 'pending',
                'evidence_paths' => $evidencePaths,
            ]);

            $this->logAudit($user, 'warehouse.receiving.quality_failed', $voucher, [
                'voucher_code' => $voucher->voucher_code,
                'quality_notes' => $request->quality_notes,
            ]);

            return response()->json([
                'message' => 'Lô hàng không đạt. Phiếu đã chuyển sang chờ trả nhà cung cấp/tiêu hủy và chưa hạch toán vào kho.',
                'requires_disposition' => true,
                'voucher' => $voucher->fresh(),
            ], 422);
        }

        if ($voucher->status === 'discrepancy' && empty($request->input('notes'))) {
            return response()->json([
                'message' => 'Phiếu nhận hàng có chênh lệch so với đơn đặt. Bắt buộc nhập ghi chú giải trình trước khi xác nhận nhập kho.',
            ], 422);
        }

        $voucher->loadMissing(['items.location', 'items.ingredient', 'purchaseOrder.items', 'documents']);

        foreach ($voucher->items as $voucherItem) {
            if ((float) $voucherItem->actual_qty > 0 && $voucherItem->ingredient?->batch_tracking_required && blank($voucherItem->lot_number)) {
                return response()->json(['message' => 'Nguyên liệu yêu cầu truy xuất bắt buộc phải có số lô trước khi duyệt nhập kho.'], 422);
            }
        }
        $temperatureMin = $request->filled('temperature_min_c') ? (float) $request->temperature_min_c : $voucher->temperature_min_c;
        $temperatureMax = $request->filled('temperature_max_c') ? (float) $request->temperature_max_c : $voucher->temperature_max_c;
        $hasTemperature = $temperatureMin !== null && $temperatureMax !== null;

        $requiresTemperature = $voucher->items->contains(function (WarehouseReceivingVoucherItem $item): bool {
            return (bool) $item->location?->is_cold_storage
                || in_array($item->ingredient?->storage_type, ['fresh', 'daily', 'short_shelf'], true)
                || $item->ingredient?->storage_temperature_min_c !== null
                || $item->ingredient?->storage_temperature_max_c !== null;
        });

        if ($requiresTemperature && ! $hasTemperature) {
            return response()->json(['message' => 'Hàng tươi/hàng kho lạnh bắt buộc ghi nhận nhiệt độ nhận hàng.'], 422);
        }
        if ($hasTemperature && $temperatureMin > $temperatureMax) {
            return response()->json(['message' => 'Nhiệt độ thấp nhất không được lớn hơn nhiệt độ cao nhất.'], 422);
        }

        $temperatureOutOfRange = $hasTemperature && $voucher->items->contains(function (WarehouseReceivingVoucherItem $item) use ($temperatureMin, $temperatureMax): bool {
            $ingredient = $item->ingredient;

            return ($ingredient?->storage_temperature_min_c !== null && $temperatureMin < (float) $ingredient->storage_temperature_min_c)
                || ($ingredient?->storage_temperature_max_c !== null && $temperatureMax > (float) $ingredient->storage_temperature_max_c);
        });

        if ($temperatureOutOfRange && $request->input('quality_status') === 'passed') {
            return response()->json(['message' => 'Nhiệt độ nhận hàng vượt ngưỡng cài đặt cho nguyên liệu. Hãy chuyển sang Kiểm tra có điều kiện hoặc xử lý từ chối.'], 422);
        }

        if ($voucher->purchaseOrder) {
            $orderedByIngredient = $voucher->purchaseOrder->items->groupBy('ingredient_id');
            $currentByIngredient = $voucher->items->groupBy('ingredient_id');
            foreach ($currentByIngredient as $ingredientId => $currentItems) {
                $poItem = $orderedByIngredient->get($ingredientId)?->first();
                $alreadyReceived = (float) ($poItem?->quantity_received ?? 0);
                $ordered = (float) ($poItem?->quantity_ordered ?? 0);
                $incoming = (float) $currentItems->sum(fn ($item): float => (float) $item->actual_qty);
                if (! $poItem || $alreadyReceived + $incoming > $ordered + 0.0005) {
                    return response()->json(['message' => 'Số lượng duyệt vượt phần còn lại của PO. Hãy đối chiếu các lần nhận trước khi nhập kho.'], 422);
                }
            }
        }

        $threeWayStatus = $voucher->purchaseOrder ? $this->threeWayMatchStatus($voucher) : 'not_applicable';
        if ($voucher->purchaseOrder && blank($voucher->invoice_number)) {
            return response()->json(['message' => 'GRN gắn với PO bắt buộc có số hóa đơn để đối chiếu 3 bên.'], 422);
        }
        if ($threeWayStatus === 'discrepancy' && blank($request->input('notes'))) {
            return response()->json(['message' => 'PO, hóa đơn và thực nhận có chênh lệch. Bắt buộc ghi chú đối soát trước khi xác nhận.'], 422);
        }

        DB::transaction(function () use (&$voucher, $id, $user, $centralBranch, $canVerifyOwnVoucher, $request, $temperatureMin, $temperatureMax, $hasTemperature, $temperatureOutOfRange, $threeWayStatus): void {
            // Khóa phiếu trong transaction để hai người không thể đồng thời hạch toán
            // cùng một GRN thành hai lần nhập kho.
            $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                ->where('branch_id', $centralBranch->id)
                ->when(! $canVerifyOwnVoucher, fn ($query) => $query->where(function ($scope) use ($user) {
                    $scope->whereNull('received_by')->orWhere('received_by', '!=', $user->id);
                }))
                ->whereIn('status', ['draft', 'discrepancy', 'pending_review'])
                ->lockForUpdate()
                ->findOrFail($id);

            $voucher->loadMissing(['items.location', 'items.ingredient', 'purchaseOrder.items']);

            // Serialize approvals on the same PO so two GRNs cannot consume the
            // remaining quantity concurrently.
            if ($voucher->purchase_order_id) {
                $lockedPurchaseOrder = PurchaseOrder::where('restaurant_id', $user->restaurant_id)
                    ->whereKey($voucher->purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lockedPurchaseOrder->load('items');
                $voucher->setRelation('purchaseOrder', $lockedPurchaseOrder);

                foreach ($voucher->items->groupBy('ingredient_id') as $ingredientId => $currentItems) {
                    $poItem = $lockedPurchaseOrder->items->firstWhere('ingredient_id', $ingredientId);
                    $alreadyReceived = (float) ($poItem?->quantity_received ?? 0);
                    $ordered = (float) ($poItem?->quantity_ordered ?? 0);
                    $incoming = (float) $currentItems->sum(fn ($item): float => (float) $item->actual_qty);
                    abort_if(! $poItem || $alreadyReceived + $incoming > $ordered + 0.0005, 422, 'Số lượng duyệt vượt phần còn lại của PO.');
                }
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

                $location = $voucherItem->location;
                $expiryDate = $voucherItem->expiry_date
                    ? Carbon::parse($voucherItem->expiry_date)->toDateString()
                    : null;
                $isExpired = $expiryDate !== null && $expiryDate < now()->toDateString();
                $isQuarantine = (bool) $location?->is_quarantine || $request->input('quality_status') === 'conditional' || $isExpired;

                $transaction = null;
                if (! $isQuarantine) {
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
                } else {
                    $unitCost = (float) ($voucherItem->unit_cost ?: $ingredient->average_cost ?: 0);
                }

                $batch = InventoryBatch::create([
                    'restaurant_id' => $user->restaurant_id,
                    'branch_id' => $voucher->branch_id,
                    'location_id' => $voucherItem->location_id,
                    'ingredient_id' => $ingredient->id,
                    'batch_number' => $voucherItem->lot_number ?: $voucher->voucher_code.'-'.$voucherItem->id,
                    'quantity_remaining' => $actualQty,
                    'unit_cost' => $unitCost,
                    'purchased_at' => optional($voucher->received_at)->toDateString() ?: now()->toDateString(),
                    'stored_at' => $voucherItem->location_id ? now() : null,
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

                if ($transaction) {
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $user->restaurant_id,
                        'branch_id' => $voucher->branch_id,
                        'inventory_batch_id' => $batch->id,
                        'inventory_transaction_id' => $transaction->id,
                        'direction' => 'in',
                        'quantity' => $actualQty,
                        'unit_cost' => $unitCost,
                    ]);
                }

                if ($isQuarantine) {
                    app(WarehouseReverseLogisticsService::class)->createQuarantine(
                        (int) $user->restaurant_id,
                        (int) $voucher->branch_id,
                        (int) $ingredient->id,
                        $actualQty,
                        $isExpired ? 'expired' : 'conditional',
                        $request->input('quality_notes') ?: 'Hàng nhập kho đang chờ xử lý chất lượng.',
                        $user,
                        $batch,
                        'warehouse_receiving_voucher',
                        $voucher->id,
                        $voucherItem->id,
                        $voucher->evidence_paths ?? [],
                        $request->input('quality_notes'),
                    );
                }

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

            $needsPutaway = $voucher->items()
                ->where('actual_qty', '>', 0)
                ->whereNull('location_id')
                ->exists();

            $voucher->update([
                'status' => 'confirmed',
                'notes' => $request->notes ?? $voucher->notes,
                'quality_status' => $request->quality_status,
                'quality_notes' => $request->quality_notes ?? $voucher->quality_notes,
                'temperature_min_c' => $hasTemperature ? $temperatureMin : $voucher->temperature_min_c,
                'temperature_max_c' => $hasTemperature ? $temperatureMax : $voucher->temperature_max_c,
                'temperature_status' => $hasTemperature ? ($temperatureOutOfRange ? 'failed' : 'passed') : 'not_recorded',
                'three_way_match_status' => $threeWayStatus,
                'disposition' => 'accepted',
                'verified_by' => $user->id,
                'verified_at' => now(),
                'putaway_started_at' => $needsPutaway ? now() : null,
                'putaway_completed_at' => $needsPutaway ? null : now(),
            ]);

            if ($needsPutaway) {
                $assignee = User::where('restaurant_id', $user->restaurant_id)
                    ->whereKey($voucher->received_by)
                    ->where('status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('warehouse_staff_status')
                            ->orWhere('warehouse_staff_status', 'active');
                    })
                    ->whereHas('roles', fn ($roles) => $roles->where('name', 'warehouse_staff'))
                    ->first();

                $putawayTask = WarehouseTaskAssignment::firstOrNew(
                    [
                        'restaurant_id' => $user->restaurant_id,
                        'receiving_voucher_id' => $voucher->id,
                        'task_type' => 'putaway',
                    ],
                );

                if (! $putawayTask->exists || ! in_array($putawayTask->status, ['completed', 'cancelled'], true)) {
                    $putawayTask->fill([
                        'assigned_to' => $assignee?->id,
                        'assigned_by' => $user->id,
                        'status' => $assignee ? 'assigned' : 'pending',
                        'priority' => 'normal',
                        'due_at' => now()->addHours(4),
                        'notes' => 'Cất các lô hàng của phiếu '.$voucher->voucher_code.' vào vị trí Kho Tổng.',
                    ]);
                    $putawayTask->save();
                    if ($assignee) {
                        $assignee->notify(new WarehouseTaskAssignedNotification($putawayTask));
                    }
                }
            }
        });

        $this->logAudit($user, 'warehouse.receiving.confirmed', $voucher, [
            'voucher_code' => $voucher->voucher_code,
        ]);

        return response()->json(['message' => 'Phiếu nhận hàng đã được xác nhận.']);
    }

    /**
     * Báo chênh lệch và yêu cầu xem xét
     */
    /**
     * Xử lý hàng không đạt sau khi QC từ chối. Hàng chưa từng được hạch toán vào tồn kho.
     */
    public function disposeReceiving(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage'), 403);

        $data = $request->validate([
            'disposition' => 'required|in:return_supplier,destroy',
            'reason' => 'required|string|max:1000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->where('status', 'pending_disposition')
            ->findOrFail($id);

        $evidencePaths = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $evidencePaths[] = $file->store('warehouse/grn-dispositions/'.now()->format('Y/m'), 'local');
            }
        }
        if ($data['disposition'] === 'destroy' && $evidencePaths === []) {
            return response()->json(['message' => 'Tiêu hủy hàng bắt buộc có ảnh/biên bản làm bằng chứng.'], 422);
        }

        $voucher->update([
            'status' => $data['disposition'] === 'destroy' ? 'destroyed' : 'returned',
            'disposition' => $data['disposition'],
            'disposition_reason' => $data['reason'],
            'disposed_by' => $user->id,
            'disposed_at' => now(),
            'disposition_evidence_paths' => $evidencePaths,
        ]);

        $this->logAudit($user, 'warehouse.receiving.disposed', $voucher, [
            'voucher_code' => $voucher->voucher_code,
            'disposition' => $data['disposition'],
        ]);

        return response()->json([
            'message' => $data['disposition'] === 'destroy' ? 'ÄÃ£ ghi nháº­n tiÃªu há»§y hÃ ng.' : 'ÄÃ£ ghi nháº­n tráº£ hÃ ng cho nhÃ  cung cáº¥p.',
            'voucher' => $voucher->fresh(),
        ]);
    }

    public function reportDiscrepancy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'discrepancy_reason' => 'required|string|max:1000',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $centralBranch->id)
            ->where('received_by', $user->id)
            ->findOrFail($id);

        $evidencePaths = $voucher->evidence_paths ?? [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $evidencePaths[] = $file->store('warehouse/grn-discrepancy/'.now()->format('Y/m'), 'public');
            }
        }

        $voucher->update([
            'status' => 'pending_review',
            'discrepancy_reason' => $request->discrepancy_reason,
            'evidence_paths' => $evidencePaths,
        ]);

        $this->logAudit($user, 'warehouse.receiving.discrepancy_reported', $voucher, [
            'voucher_code' => $voucher->voucher_code,
            'discrepancy_reason' => $request->discrepancy_reason,
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
            'batch_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
            'scan_log' => 'nullable|array',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
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
            'Vị trí cất hàng không thuộc Kho Tổng.'
        );

        return DB::transaction(function () use ($request, $user, $centralBranch, $task): JsonResponse {
            $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
                ->where('assigned_to', $user->id)
                ->where('task_type', 'putaway')
                ->lockForUpdate()
                ->findOrFail($task->id);

            abort_if(in_array($task->status, ['completed', 'cancelled'], true), 422, 'Task cất hàng đã đóng hoặc bị huỷ.');
            abort_unless($task->status === 'in_progress', 422, 'Task cất hàng phải được bắt đầu trước khi xác nhận.');

            $batch = null;
            $voucherItem = null;
            if ($task->receiving_voucher_id) {
                $voucherItems = WarehouseReceivingVoucherItem::where('voucher_id', $task->receiving_voucher_id)
                    ->whereNotNull('batch_id')
                    ->where('actual_qty', '>', 0)
                    ->get();
                $voucherItem = $request->filled('batch_id')
                    ? $voucherItems->firstWhere('batch_id', (int) $request->batch_id)
                    : ($voucherItems->count() === 1 ? $voucherItems->first() : null);
                abort_unless($voucherItem, 422, 'Task cất hàng phải xác định đúng lô hàng trước khi xác nhận.');
                $batch = InventoryBatch::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $centralBranch->id)
                    ->whereKey((int) $voucherItem->batch_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } elseif ($request->filled('batch_id')) {
                $batch = InventoryBatch::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $centralBranch->id)
                    ->whereKey((int) $request->batch_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $scanLog = array_merge($task->scan_log ?? [], $request->scan_log ?? [], [[
                'type' => 'putaway_confirmed',
                'batch_id' => $batch?->id,
                'location_id' => (int) $request->location_id,
                'scanned_at' => now()->toISOString(),
            ]]);

            if ($batch) {
                $batch->update([
                    'location_id' => (int) $request->location_id,
                    'stored_at' => now(),
                ]);
            }
            if ($voucherItem) {
                $voucherItem->update(['location_id' => (int) $request->location_id]);
            }

            if ($task->receiving_voucher_id) {
                $voucher = WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $centralBranch->id)
                    ->lockForUpdate()
                    ->findOrFail($task->receiving_voucher_id);
                if (! $voucher->items()->where('actual_qty', '>', 0)->whereNull('location_id')->exists()) {
                    $voucher->update(['putaway_completed_at' => now()]);
                }
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_notes' => $request->notes,
                'scan_log' => $scanLog,
            ]);

            $this->logAudit($user, 'warehouse.putaway.confirmed', $task, [
                'task_id' => $task->id,
                'location_id' => $request->location_id,
                'scan_count' => count($scanLog),
            ]);

            return response()->json(['message' => 'Cất hàng thành công!']);
        });
    }

    // ── 4. Báo Sự Cố ─────────────────────────────────────────────────────────

    /**
     * Báo sự cố / hỏng hóc / thiếu hụt
     */
    public function reportIncident(Request $request): JsonResponse
    {
        $request->validate([
            'incident_type' => 'required|in:shortage,damage,expired,wrong_item,other',
            'idempotency_key' => 'nullable|string|max:80',
            'ingredient_id' => ['nullable', 'integer', TenantRule::exists('ingredients')],
            'batch_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'description' => 'required|string|max:2000',
            'quantity_affected' => 'nullable|numeric|min:0',
            'evidence' => 'nullable|array',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $restaurantId = $user->restaurant_id;

        if ($request->filled('idempotency_key')) {
            $existingTask = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
                ->where('idempotency_key', $request->string('idempotency_key')->toString())
                ->first();

            if ($existingTask) {
                return response()->json([
                    'message' => 'Sự cố đã được ghi nhận trước đó.',
                    'task_id' => $existingTask->id,
                    'idempotent_replay' => true,
                ]);
            }
        }

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        if ($request->filled('ingredient_id')) {
            abort_unless(
                $centralBranch && $this->centralIngredientQuery($restaurantId, $centralBranch->id)
                    ->whereKey((int) $request->ingredient_id)
                    ->exists(),
                422,
                'Nguyên liệu báo sự cố phải thuộc Kho Tổng hoặc catalog toàn chuỗi.'
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
                $evidencePaths[] = $file->store('warehouse/incidents/'.now()->format('Y/m'), 'local');
            }
        }

        $quarantineId = null;
        if (in_array($request->incident_type, ['damage', 'expired'], true) && $request->filled('batch_id') && (float) ($request->quantity_affected ?? 0) > 0) {
            try {
                DB::transaction(function () use ($request, $user, $restaurantId, $centralBranch, $evidencePaths, &$quarantineId): void {
                    $batch = InventoryBatch::where('restaurant_id', $restaurantId)
                        ->where('branch_id', $centralBranch->id)
                        ->lockForUpdate()
                        ->findOrFail((int) $request->batch_id);
                    $quantity = (float) $request->quantity_affected;
                    if ($batch->status !== 'active' || (float) $batch->quantity_remaining + 0.0005 < $quantity) {
                        throw new \InvalidArgumentException('Lô không còn đủ tồn khả dụng để chuyển sang cách ly.');
                    }
                    $inventory = Inventory::where('restaurant_id', $restaurantId)
                        ->where('branch_id', $centralBranch->id)
                        ->where('ingredient_id', $batch->ingredient_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    if ((float) $inventory->quantity_on_hand + 0.0005 < $quantity) {
                        throw new \InvalidArgumentException('Tồn khả dụng không đủ để chuyển sang cách ly.');
                    }
                    $before = (float) $inventory->quantity_on_hand;
                    $transaction = InventoryTransaction::createWithIdempotency([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $centralBranch->id,
                        'ingredient_id' => $batch->ingredient_id,
                        'inventory_id' => $inventory->id,
                        'performed_by' => $user->id,
                        'type' => 'waste',
                        'direction' => 'out',
                        'quantity' => $quantity,
                        'unit_cost' => $batch->unit_cost,
                        'total_cost' => $quantity * (float) $batch->unit_cost,
                        'source_type' => 'warehouse_incident',
                        'source_id' => $request->batch_id,
                        'idempotency_key' => 'incident_quarantine_'.$request->batch_id.'_'.sha1($request->description),
                        'reference_code' => 'INC-Q-'.$request->batch_id,
                        'waste_category' => $request->incident_type,
                        'notes' => 'Chuyển hàng lỗi sang cách ly: '.$request->description,
                        'occurred_at' => now(),
                    ]);
                    $inventory->update(['quantity_on_hand' => $before - $quantity, 'theoretical_quantity' => max(0, (float) $inventory->theoretical_quantity - $quantity), 'updated_by' => $user->id]);
                    $batch->decrement('quantity_remaining', $quantity);
                    if ((float) $batch->quantity_remaining <= 0) {
                        $batch->update(['status' => 'depleted']);
                    }
                    $lockedBatch = app(WarehouseReverseLogisticsService::class)->createDestinationBatch(
                        $restaurantId,
                        (int) $centralBranch->id,
                        (int) $batch->ingredient_id,
                        $quantity,
                        (float) $batch->unit_cost,
                        $user,
                        $batch,
                        true,
                        'Lô bị báo hỏng/hết hạn, chờ hoàn trả hoặc tiêu hủy.',
                    );
                    InventoryBatchAllocation::create([
                        'restaurant_id' => $restaurantId,
                        'branch_id' => $centralBranch->id,
                        'inventory_batch_id' => $batch->id,
                        'inventory_transaction_id' => $transaction->id,
                        'direction' => 'out',
                        'quantity' => $quantity,
                        'unit_cost' => $batch->unit_cost,
                    ]);
                    $quarantine = app(WarehouseReverseLogisticsService::class)->createQuarantine(
                        $restaurantId,
                        (int) $centralBranch->id,
                        (int) $batch->ingredient_id,
                        $quantity,
                        $request->incident_type,
                        $request->description,
                        $user,
                        $lockedBatch,
                        'warehouse_incident',
                        (int) $request->batch_id,
                        null,
                        $evidencePaths,
                    );
                    $quarantineId = $quarantine->id;
                });
            } catch (\Throwable $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        // Tạo incident task cho nhân viên (loại incident)
        $task = WarehouseTaskAssignment::create([
            'restaurant_id' => $restaurantId,
            'idempotency_key' => $request->input('idempotency_key'),
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'task_type' => 'incident',
            'status' => 'in_progress',
            'priority' => 'high',
            'notes' => $request->description,
            'evidence_paths' => $evidencePaths,
            'started_at' => now(),
            'result_notes' => json_encode([
                'incident_type' => $request->incident_type,
                'ingredient_id' => $request->ingredient_id,
                'batch_id' => $request->batch_id,
                'location_id' => $request->location_id,
                'quantity_affected' => $request->quantity_affected,
                'quarantine_id' => $quarantineId,
            ]),
        ]);

        $this->logAudit($user, 'warehouse.incident.reported', $task, [
            'incident_type' => $request->incident_type,
            'description' => $request->description,
            'evidence_count' => count($evidencePaths),
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
        $this->staffAccess->assertCanAccessCentral($user);
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
            'pending_tasks' => $pendingTasks,
            'can_handover' => $pendingTasks->isEmpty() || $pendingTasks->count() <= 3,
        ]);
    }

    /**
     * Nộp bàn giao ca cuối
     */
    public function submitShiftHandover(Request $request): JsonResponse
    {
        $request->validate([
            'shift_date' => 'required|date',
            'shift_type' => 'required|in:morning,afternoon,evening,night',
            'shift_label' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'received_by' => ['required', 'integer', TenantRule::exists('users')],
            'incidents_json' => 'nullable|array',
        ]);

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $restaurantId = $user->restaurant_id;

        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');

        if ($request->filled('received_by')) {
            $recipient = User::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('warehouse_staff_status')
                        ->orWhere('warehouse_staff_status', 'active');
                })
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
                'ingredient_id' => $inv->ingredient_id,
                'ingredient_name' => $inv->ingredient?->name,
                'quantity_on_hand' => $inv->quantity_on_hand,
            ]);

        $isSystemLocked = $pendingTasks->count() > 5; // Ngưỡng cảnh báo: > 5 task chưa xong

        $handover = WarehouseShiftHandover::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $centralBranch->id,
            'shift_date' => $request->shift_date,
            'shift_type' => $request->shift_type,
            'shift_label' => $request->shift_label,
            'handover_by' => $user->id,
            'received_by' => $request->received_by,
            'status' => 'pending',
            'notes' => $request->notes,
            'open_tasks_json' => $pendingTasks->map(fn ($t) => [
                'id' => $t->id,
                'task_type' => $t->task_type,
                'status' => $t->status,
                'due_at' => $t->due_at?->toISOString(),
            ])->values()->all(),
            'incidents_json' => $request->incidents_json ?? [],
            'stock_snapshot_json' => $stockSnapshot->toArray(),
            'is_system_locked' => $isSystemLocked,
            'lock_reason' => $isSystemLocked
                ? 'Còn '.$pendingTasks->count().' task chưa hoàn thành trong ca.'
                : null,
            'pending_picks_count' => $pendingTasks->where('task_type', 'picking')->count(),
            'pending_deliveries_count' => $pendingTasks->where('task_type', 'handover')->count(),
        ]);

        $this->logAudit($user, 'warehouse.shift_handover.submitted', $handover, [
            'shift_date' => $request->shift_date,
            'shift_type' => $request->shift_type,
            'pending_tasks_count' => $pendingTasks->count(),
            'is_system_locked' => $isSystemLocked,
        ]);

        User::whereKey((int) $request->received_by)->first()?->notify(
            new WarehouseShiftHandoverPendingNotification($handover, $user)
        );

        return response()->json([
            'message' => 'Bàn giao ca đã được nộp.',
            'handover' => $handover,
            'is_system_locked' => $isSystemLocked,
            'pending_tasks' => $pendingTasks->count(),
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

        $user = $request->user();
        $this->staffAccess->assertCanOperate($user);
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Chưa thiết lập Kho Tổng.');
        $handover = WarehouseShiftHandover::where('restaurant_id', $user->restaurant_id)
            ->where('received_by', $user->id)
            ->where('branch_id', $centralBranch->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        // Tạo hash xác nhận
        $hash = hash('sha256', $user->id.'|'.$handover->id.'|'.now()->timestamp);

        $handover->update([
            'status' => 'confirmed',
            'signed_at' => now(),
            'acknowledgment_hash' => $hash,
            'notes' => ($handover->notes ?? '')."\n[Người nhận ca]: ".($request->notes ?? ''),
        ]);

        $this->logAudit($user, 'warehouse.shift_handover.confirmed', $handover, [
            'handover_id' => $handover->id,
            'handover_by' => $handover->handover_by,
            'received_by' => $user->id,
            'hash' => $hash,
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
        $this->staffAccess->assertCanAccessCentral($user);

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
            ->get(['id', 'action', 'new_values', 'created_at', 'ip_address']);

        return response()->json(['history' => $history]);
    }

    public function viewTaskEvidence(Request $request, int $id, int $index)
    {
        $user = $request->user();
        $this->staffAccess->assertCanAccessCentral($user);
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)
            ->where(function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                    ->orWhere(fn ($manager) => $manager->whereNull('assigned_to')->where('assigned_by', $user->id));
            })
            ->findOrFail($id);
        $path = $task->evidence_paths[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
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
        $this->staffAccess->assertCanAccessCentral($user);
        $restaurantId = $user->restaurant_id;

        // Thử match ingredient SKU
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
        $ingredient = $this->centralIngredientQuery($restaurantId, $centralBranch?->id)
            ->where('sku', $code)
            ->with(['unit'])
            ->first();

        if ($ingredient) {
            return response()->json([
                'type' => 'ingredient',
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'sku' => $ingredient->sku,
                'unit' => $ingredient->unit?->symbol,
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
                'type' => 'batch',
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'ingredient_id' => $batch->ingredient_id,
                'ingredient_name' => $batch->ingredient?->name,
                'expiry_date' => $batch->expiry_date,
                'quantity' => $batch->quantity,
                'status' => $batchStatus,
                'is_locked' => $batch->is_locked,
                'warning' => $batchStatus !== 'ok'
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
                'type' => 'location',
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'zone' => $location->zone,
                'is_cold' => $location->is_cold_storage ?? false,
                'is_quarantine' => $location->is_quarantine ?? false,
                'status' => 'found',
            ]);
        }

        return response()->json([
            'type' => 'unknown',
            'code' => $code,
            'status' => 'not_found',
            'message' => 'Không tìm thấy mã: '.$code,
        ], 404);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function temperatureStatus(float|int|string|null $minimum, float|int|string|null $maximum): string
    {
        return $minimum !== null && $maximum !== null ? 'recorded' : 'not_recorded';
    }

    private function threeWayMatchStatus(WarehouseReceivingVoucher $voucher): string
    {
        $poItems = $voucher->purchaseOrder?->items?->keyBy('ingredient_id') ?? collect();
        if ($poItems->isEmpty()) {
            return 'discrepancy';
        }

        foreach ($voucher->items->groupBy('ingredient_id') as $ingredientId => $items) {
            $poItem = $poItems->get($ingredientId);
            if (! $poItem) {
                return 'discrepancy';
            }

            $cumulativeQuantity = (float) $poItem->quantity_received
                + (float) $items->sum(fn ($item): float => (float) $item->actual_qty);
            $quantityMismatch = $cumulativeQuantity > (float) $poItem->quantity_ordered + 0.0005;
            $expectedPrice = (float) $poItem->price_per_unit;
            $actualPrice = (float) $items->first()->unit_cost;
            $priceMismatch = $expectedPrice > 0 && $actualPrice > 0 && abs($actualPrice - $expectedPrice) > max(0.01, $expectedPrice * 0.1);

            if ($quantityMismatch || $priceMismatch) {
                return 'discrepancy';
            }
        }

        return 'matched';
    }

    private function centralIngredientQuery(int $restaurantId, ?int $centralBranchId)
    {
        return Ingredient::where('restaurant_id', $restaurantId)
            ->when(
                $centralBranchId,
                fn ($query) => $query->where(fn ($scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $centralBranchId)
                    ->orWhereHas('inventories', fn ($inv) => $inv->where('branch_id', $centralBranchId))),
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
            'id' => $task->id,
            'task_type' => $task->task_type,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toISOString(),
            'started_at' => $task->started_at?->toISOString(),
            'completed_at' => $task->completed_at?->toISOString(),
            'notes' => $task->notes,
            'result_notes' => $task->result_notes,
            'evidence_count' => count($task->evidence_paths ?? []),
            'evidence_urls' => collect($task->evidence_paths ?? [])->keys()->map(fn ($index) => route('warehouse.tasks.evidence', ['id' => $task->id, 'index' => $index]))->values()->all(),
            'is_overdue' => $task->isOverdue(),
            'duration_minutes' => $task->duration(),
            'supply_request' => $task->supplyRequest ? [
                'id' => $task->supplyRequest->id,
                'request_code' => $task->supplyRequest->request_code,
                'to_branch' => $task->supplyRequest->toBranch?->name,
                'status' => $task->supplyRequest->status,
                'items' => $task->supplyRequest->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient?->name,
                    'unit' => $item->ingredient?->unit?->symbol,
                    'requested_quantity' => (float) ($item->requested_quantity ?? 0),
                    'approved_quantity' => (float) ($item->approved_quantity ?? $item->requested_quantity ?? 0),
                    'actual_dispatched_quantity' => $item->actual_dispatched_quantity,
                    'batch_id' => $item->batch_id,
                    'warehouse_location_id' => $item->warehouse_location_id,
                    'batch_number' => $item->batch?->batch_number,
                ])->values()->all(),
            ] : null,
            'receiving_voucher' => $task->receivingVoucher ? [
                'id' => $task->receivingVoucher->id,
                'voucher_code' => $task->receivingVoucher->voucher_code,
                'items' => $task->receivingVoucher->items->map(fn ($item) => [
                    'id' => $item->id,
                    'ingredient_id' => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient?->name,
                    'batch_id' => $item->batch_id,
                    'location_id' => $item->location_id,
                    'actual_qty' => (float) $item->actual_qty,
                    'batch_number' => $item->batch?->batch_number,
                    'location_code' => $item->location?->location_code ?? $item->location?->code,
                ])->values()->all(),
            ] : null,
            'count_session' => $task->countSession ? [
                'id' => $task->countSession->id,
                'type' => $task->countSession->type,
                'status' => $task->countSession->status,
                'period_start' => $task->countSession->period_start?->toDateString(),
                'period_end' => $task->countSession->period_end?->toDateString(),
            ] : null,
        ];
    }

    private function logAudit(User $user, string $action, mixed $model, array $metadata = []): void
    {
        try {
            AuditLog::create([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'user_role' => $user->roles()->pluck('name')->first() ?? 'staff',
                'event' => 'created',
                'action' => $action,
                'subject_type' => get_class($model),
                'subject_id' => $model->id,
                'new_values' => array_merge($metadata, [
                    'ip_address' => request()->ip(),
                    'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
                    'performed_at' => now()->toISOString(),
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
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
        abort_unless(
            $user->can('warehouse.manage') || $user->hasAnyRole(['warehouse_manager', 'owner', 'super_admin']),
            403,
            'Bạn không có quyền tự động phân công nhiệm vụ Kho Tổng.'
        );

        $restaurantId = (int) $user->restaurant_id;
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);
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

        // Lấy danh sách nhân viên kho active thuộc Kho Tổng
        $warehouseStaff = User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('warehouse_staff_status')
                    ->orWhere('warehouse_staff_status', 'active');
            })
            ->whereHas('roles', fn ($q) => $q->where('name', 'warehouse_staff'))
            ->where(function ($query) use ($centralBranch) {
                $query->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->get();

        if ($warehouseStaff->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhân viên Kho Tổng (warehouse_staff) đang hoạt động để phân công tự động. Vui lòng tạo nhân sự kho trước.',
            ], 422);
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
