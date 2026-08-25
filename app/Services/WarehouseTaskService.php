<?php

namespace App\Services;

use App\Models\SupplyRequest;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use Illuminate\Validation\ValidationException;

class WarehouseTaskService
{
    public function __construct(
        protected CentralWarehouseService $warehouseService
    ) {}

    /**
     * Danh sách nhân viên Kho Tổng để Trưởng kho phân công theo ca việc.
     */
    public function getWarehouseStaff(int $restaurantId): array
    {
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        return User::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where('warehouse_staff_status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'warehouse_staff'))
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['roles', 'employee'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $staff): array => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'job_title' => $staff->employee?->job_title ?: 'Nhân viên Kho Tổng',
                'employee_code' => $staff->employee?->employee_code,
                'avatar_url' => $staff->avatar_url,
            ])
            ->values()
            ->all();
    }

    /**
     * Danh sách công việc Kho Tổng đã phân công.
     */
    public function getWarehouseTasks(int $restaurantId): array
    {
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        return WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            }))
            ->when(! $centralBranch, fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'assignee.employee',
                'supplyRequest.toBranch',
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WarehouseTaskAssignment $task): array => [
                'id' => $task->id,
                'supply_request_id' => $task->supply_request_id,
                'request_code' => $task->supplyRequest?->request_code,
                'request_status' => $task->supplyRequest?->status,
                'branch_name' => $task->supplyRequest?->toBranch?->name,
                'task_type' => $task->task_type,
                'status' => $task->status,
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
                'assignee_name' => $task->assignee?->name,
                'assignee_job_title' => $task->assignee?->employee?->job_title,
                'due_at' => $task->due_at?->toISOString(),
                'notes' => $task->notes,
                'created_at' => $task->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Thống kê tổng hợp trạng thái các Task.
     */
    public function buildWarehouseTaskSummary(array $tasks): array
    {
        return [
            'total' => count($tasks),
            'assigned' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'assigned')),
            'in_progress' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'in_progress')),
            'completed' => count(array_filter($tasks, fn (array $task) => $task['status'] === 'completed')),
            'unassigned' => count(array_filter($tasks, fn (array $task) => empty($task['assigned_to']) && $task['status'] !== 'completed')),
        ];
    }

    /**
     * Phân công nhiệm vụ kho cho nhân viên.
     */
    public function assignTask(User $user, array $data): WarehouseTaskAssignment
    {
        $this->assertWarehouseManager($user);
        $data['task_type'] = [
            'inventory_count' => 'counting',
            'discrepancy_resolution' => 'incident',
            'incident_resolution' => 'incident',
            'shift_handover' => 'handover',
        ][$data['task_type']] ?? $data['task_type'];
        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');

        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)
            ->where('from_branch_id', $centralBranch->id)
            ->findOrFail((int) $data['supply_request_id']);

        $assignee = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->where('warehouse_staff_status', 'active')
            ->whereKey((int) $data['assigned_to'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'warehouse_staff'))
            ->where(function ($query) use ($centralBranch) {
                $query->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->firstOrFail();

        $allowedStatuses = $data['task_type'] === 'picking'
            ? [SupplyRequest::STATUS_APPROVED, SupplyRequest::STATUS_PREPARING]
            : [SupplyRequest::STATUS_DISPATCH_PENDING];

        if (! in_array($supplyRequest->status, $allowedStatuses, true)) {
            $msg = $data['task_type'] === 'picking'
                ? 'Đơn phải ở trạng thái Đã duyệt hoặc Đang soạn mới có thể giao việc soạn hàng.'
                : 'Đơn phải được Trưởng kho duyệt xuất trước khi giao việc bàn giao.';
            throw ValidationException::withMessages(['task_type' => $msg]);
        }

        return WarehouseTaskAssignment::updateOrCreate(
            [
                'restaurant_id' => $user->restaurant_id,
                'supply_request_id' => $supplyRequest->id,
                'task_type' => $data['task_type'],
            ],
            [
                'assigned_to' => $assignee->id,
                'assigned_by' => $user->id,
                'status' => 'assigned',
                'priority' => $data['priority'],
                'due_at' => $data['due_at'] ?? $supplyRequest->requested_delivery_date,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    /**
     * Cập nhật trạng thái nhiệm vụ.
     */
    public function updateTaskStatus(WarehouseTaskAssignment $task, User $user, string $newStatus, ?string $notes = null): WarehouseTaskAssignment
    {
        if ((int) $task->restaurant_id !== (int) $user->restaurant_id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['task' => 'Nhiệm vụ không thuộc nhà hàng của tài khoản.']);
        }

        $centralBranch = $this->warehouseService->getCentralWarehouse((int) $task->restaurant_id);
        $isCentralTask = ((int) ($task->supplyRequest?->from_branch_id ?? 0) === (int) ($centralBranch?->id ?? 0) && $centralBranch)
            || ((int) ($task->receivingVoucher?->branch_id ?? 0) === (int) ($centralBranch?->id ?? 0) && $centralBranch)
            || ($task->supply_request_id === null && $task->receiving_voucher_id === null);
        if (! $isCentralTask) {
            throw ValidationException::withMessages(['task' => 'Nhiệm vụ không thuộc Kho Tổng hiện tại.']);
        }

        $isManager = $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage');
        if (! $isManager && (int) $task->assigned_to !== (int) $user->id) {
            throw ValidationException::withMessages(['task' => 'Bạn không được cập nhật nhiệm vụ không được giao cho mình.']);
        }
        $currentStatus = $task->status;

        $allowedTransitions = [
            'pending' => ['assigned', 'in_progress', 'cancelled'],
            'assigned' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled', 'assigned'],
            'completed' => $isManager ? ['in_progress', 'assigned'] : [],
            'cancelled' => $isManager ? ['assigned', 'pending'] : [],
        ];

        if ($newStatus !== $currentStatus && ! in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển trạng thái nhiệm vụ từ '{$currentStatus}' sang '{$newStatus}'.",
            ]);
        }

        $updates = [
            'status' => $newStatus,
            'notes' => $notes ?? $task->notes,
        ];
        if ($newStatus === 'in_progress' && $currentStatus !== 'in_progress') {
            $updates['started_at'] = $task->started_at ?? now();
        }
        if ($newStatus === 'completed' && $currentStatus !== 'completed') {
            $updates['completed_at'] = now();
        }
        if (in_array($newStatus, ['assigned', 'pending'], true) && $currentStatus === 'completed') {
            $updates['completed_at'] = null;
        }

        $task->update($updates);

        return $task;
    }

    private function assertWarehouseManager(User $user): void
    {
        if ($user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage')) {
            return;
        }

        throw ValidationException::withMessages(['task' => 'Chỉ quản lý Kho Tổng mới được phân công nhiệm vụ.']);
    }

    /**
     * Lấy dữ liệu Task Board.
     */
    public function getTaskBoardData(int $restaurantId): array
    {
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        $pickingPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_APPROVED)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'creator'])
            ->orderBy('requested_delivery_date')
            ->get();

        $dispatchApprovalPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_PREPARING)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'preparedBy'])
            ->orderBy('prepared_at')
            ->get();

        $handoverPending = SupplyRequest::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where('from_branch_id', $centralBranch->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', SupplyRequest::STATUS_DISPATCH_PENDING)
            ->with(['items.ingredient.unit', 'items.batch', 'toBranch', 'approver', 'preparedBy'])
            ->orderBy('dispatch_approved_at')
            ->get();

        $warehouseTasks = $this->getWarehouseTasks($restaurantId);

        return [
            'picking_pending' => $pickingPending,
            'dispatch_approval_pending' => $dispatchApprovalPending,
            'handover_pending' => $handoverPending,
            'assignments' => $warehouseTasks,
            'staff' => $this->getWarehouseStaff($restaurantId),
            'summary' => $this->buildWarehouseTaskSummary($warehouseTasks),
        ];
    }
}
