<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WarehouseShiftHandover;
use App\Models\WarehouseStaffSupervisorHistory;
use App\Models\WarehouseTaskAssignment;
use App\Services\CentralWarehouseService;
use App\Services\CentralWarehouseStaffKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Support\TenantRule;
use Inertia\Inertia;
use Inertia\Response;

class CentralWarehouseTeamController extends Controller
{
    public function __construct(
        protected CentralWarehouseService $warehouseService,
        protected CentralWarehouseStaffKpiService $kpiService,
    ) {}

    /**
     * Màn hình chính "Đội ngũ Kho Tổng".
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền truy cập quản lý đội ngũ Kho Tổng.');

        $restaurantId = $user->restaurant_id;
        $centralBranch = $this->warehouseService->getCentralWarehouse($restaurantId);

        // Danh sách nhân viên Kho Tổng (chỉ lấy vai trò warehouse_staff)
        $staffMembers = User::where('restaurant_id', $restaurantId)
            ->role('warehouse_staff')
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->with([
                'supervisor:id,name,email,avatar_url',
                'warehouseBranch:id,name',
                'roles:id,name',
            ])
            ->get();

        // Map thông tin chi tiết từng nhân viên
        $staffData = $staffMembers->map(function ($staff) use ($restaurantId) {
            $activeTasksCount = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
                ->where('assigned_to', $staff->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();

            $overdueTasksCount = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
                ->where('assigned_to', $staff->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->where('due_at', '<', now())
                ->count();

            $kpi = $this->kpiService->calculateStaffKpi($restaurantId, $staff->id);

            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'avatar_url' => $staff->avatar_url,
                'supervisor_id' => $staff->supervisor_user_id,
                'supervisor_name' => $staff->supervisor?->name ?? 'Chưa bổ nhiệm',
                'warehouse_branch_id' => $staff->warehouse_branch_id,
                'warehouse_branch_name' => $staff->warehouseBranch?->name ?? 'Kho Tổng',
                'warehouse_staff_status' => $staff->warehouse_staff_status ?? 'active',
                'active_tasks_count' => $activeTasksCount,
                'overdue_tasks_count' => $overdueTasksCount,
                'kpi_score' => $kpi['composite_score'],
                'completion_rate' => $kpi['completion_rate'],
                'on_time_rate' => $kpi['on_time_rate'],
                'incidents_count' => $kpi['incidents_count'],
            ];
        });

        // Danh sách Trưởng kho khả dụng (dùng để bổ nhiệm người quản lý trực tiếp)
        $supervisors = User::where('restaurant_id', $restaurantId)
            ->where(function ($q) {
                $q->role('warehouse_manager')
                    ->orWhere('id', DB::raw('supervisor_user_id'));
            })
            ->orWhere(function ($q) use ($restaurantId) {
                $q->where('restaurant_id', $restaurantId)
                    ->whereHas('roles', fn ($r) => $r->whereIn('name', ['owner', 'super_admin', 'warehouse_manager']));
            })
            ->get(['id', 'name', 'email', 'avatar_url']);

        // Danh sách nhiệm vụ gần đây
        $recentTasks = WarehouseTaskAssignment::where('restaurant_id', $restaurantId)
            ->when($centralBranch, fn ($query) => $query->where(function ($scope) use ($centralBranch) {
                $scope->whereHas('supplyRequest', fn ($request) => $request->where('from_branch_id', $centralBranch->id))
                    ->orWhereHas('receivingVoucher', fn ($voucher) => $voucher->where('branch_id', $centralBranch->id))
                    ->orWhere(function ($unlinked) {
                        $unlinked->whereNull('supply_request_id')->whereNull('receiving_voucher_id');
                    });
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->with(['assignee:id,name,avatar_url', 'assigner:id,name', 'supplyRequest.toBranch'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Đơn nghỉ phép của nhân viên kho
        $staffIds = $staffMembers->pluck('id')->toArray();
        $leaveRequests = LeaveRequest::where('restaurant_id', $restaurantId)
            ->where(function ($q) use ($staffIds) {
                $q->whereIn('requested_by', $staffIds)
                    ->orWhereHas('employee', fn ($eq) => $eq->whereIn('user_id', $staffIds));
            })
            ->with(['user:id,name,email,avatar_url', 'employee.user:id,name,email,avatar_url'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($leave) {
                if (! $leave->relationLoaded('user') || ! $leave->user) {
                    $userObj = $leave->employee?->user;
                    $leave->setRelation('user', $userObj ?? (object) [
                        'id' => 0,
                        'name' => $leave->employee?->full_name ?? 'Nhân viên',
                        'email' => '',
                        'avatar_url' => null,
                    ]);
                }

                return $leave;
            });

        // Báo cáo KPI toàn đội ngũ
        $teamKpi = $this->kpiService->getTeamKpiReport($restaurantId, $centralBranch?->id);

        return Inertia::render('inventory/CentralWarehouseTeam', [
            'centralBranch' => $centralBranch,
            'staffMembers' => $staffData,
            'supervisors' => $supervisors,
            'recentTasks' => $recentTasks,
            'leaveRequests' => $leaveRequests,
            'teamKpi' => $teamKpi,
            'taskTypes' => [
                ['value' => 'receiving', 'label' => 'Nhận hàng (GRN)'],
                ['value' => 'putaway', 'label' => 'Cất hàng vào vị trí'],
                ['value' => 'picking', 'label' => 'Soạn hàng theo đơn'],
                ['value' => 'packing', 'label' => 'Đóng gói hàng hóa'],
                ['value' => 'inventory_count', 'label' => 'Kiểm kê tồn kho'],
                ['value' => 'discrepancy_resolution', 'label' => 'Xử lý sai lệch'],
                ['value' => 'incident_resolution', 'label' => 'Xử lý sự cố'],
                ['value' => 'shift_handover', 'label' => 'Bàn giao ca'],
            ],
        ]);
    }

    /**
     * Bổ nhiệm / Thay đổi Trưởng kho quản lý trực tiếp cho Nhân viên kho.
     */
    public function assignSupervisor(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền phân công Trưởng kho.');

        $data = $request->validate([
            'staff_user_id' => ['required', TenantRule::exists('users')],
            'supervisor_user_id' => ['nullable', TenantRule::exists('users')],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $staff = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['staff_user_id']);

        abort_unless($staff->hasRole('warehouse_staff'), 422, 'Chỉ có thể gán Trưởng kho cho nhân viên có vai trò warehouse_staff.');

        if ($data['supervisor_user_id']) {
            $supervisor = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['supervisor_user_id']);
            abort_unless($supervisor->hasAnyRole(['warehouse_manager', 'owner', 'super_admin']), 422, 'Người quản lý trực tiếp phải có vai trò Trưởng kho (warehouse_manager) hoặc Owner.');
        }

        // Kết thúc lịch sử cũ
        WarehouseStaffSupervisorHistory::where('warehouse_staff_id', $staff->id)
            ->where('status', 'active')
            ->update([
                'effective_to' => now(),
                'status' => 'ended',
            ]);

        // Cập nhật User
        $staff->update([
            'supervisor_user_id' => $data['supervisor_user_id'],
            'warehouse_branch_id' => $staff->warehouse_branch_id ?? $this->warehouseService->getCentralWarehouse($user->restaurant_id)?->id,
        ]);

        // Ghi lịch sử mới
        WarehouseStaffSupervisorHistory::create([
            'restaurant_id' => $user->restaurant_id,
            'warehouse_branch_id' => $staff->warehouse_branch_id,
            'warehouse_staff_id' => $staff->id,
            'supervisor_user_id' => $data['supervisor_user_id'],
            'assigned_by' => $user->id,
            'effective_from' => now(),
            'status' => 'active',
            'notes' => $data['notes'] ?? 'Bổ nhiệm Trưởng kho trực tiếp',
        ]);

        AuditLog::log('warehouse_staff_supervisor_assigned', 'updated', $staff, null, ['supervisor_user_id' => $data['supervisor_user_id']]);

        return back()->with('success', 'Đã phân công Trưởng kho trực tiếp thành công.');
    }

    /**
     * Tạm dừng hoặc mở lại trạng thái nhận việc của Nhân viên kho.
     */
    public function toggleTaskStatus(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền thay đổi trạng thái nhận việc.');

        $data = $request->validate([
            'staff_user_id' => ['required', TenantRule::exists('users')],
            'status' => ['required', 'in:active,paused,inactive'],
        ]);

        $staff = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['staff_user_id']);
        $staff->update(['warehouse_staff_status' => $data['status']]);

        AuditLog::log('warehouse_staff_status_updated', 'updated', $staff, null, ['status' => $data['status']]);

        return back()->with('success', 'Đã cập nhật trạng thái làm việc thành công.');
    }

    /**
     * Giao nhiệm vụ mới cho Nhân viên kho.
     * Quy tắc bảo mật: Chỉ cho phép giao cho người có vai trò `warehouse_staff` và đang `active`.
     */
    public function assignTask(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền phân công nhiệm vụ kho.');

        $data = $request->validate([
            'assigned_to' => ['required', TenantRule::exists('users')],
            'task_type' => ['required', 'string', 'in:receiving,putaway,picking,packing,inventory_count,discrepancy_resolution,incident_resolution,shift_handover'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'supply_request_id' => ['nullable', TenantRule::exists('central_supply_requests')],
            'receiving_voucher_id' => ['nullable', TenantRule::exists('warehouse_receiving_vouchers')],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');

        $assignee = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereKey($data['assigned_to'])
            ->where(function ($query) use ($centralBranch) {
                $query->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->firstOrFail();

        if (! empty($data['supply_request_id'])) {
            abort_unless(
                \App\Models\SupplyRequest::where('restaurant_id', $user->restaurant_id)
                    ->where('from_branch_id', $centralBranch->id)
                    ->whereKey($data['supply_request_id'])
                    ->exists(),
                422,
                'Đơn cấp phát được giao phải xuất từ Kho Tổng.'
            );
        }
        if (! empty($data['receiving_voucher_id'])) {
            abort_unless(
                \App\Models\WarehouseReceivingVoucher::where('restaurant_id', $user->restaurant_id)
                    ->where('branch_id', $centralBranch->id)
                    ->whereKey($data['receiving_voucher_id'])
                    ->exists(),
                422,
                'Phiếu nhận hàng được giao phải thuộc Kho Tổng.'
            );
        }

        // Ranh giới bảo mật: Chặn không cho phân công cho manager, owner hoặc nhân viên bị paused
        if (! $assignee->hasRole('warehouse_staff')) {
            return back()->withErrors(['assigned_to' => 'Nhiệm vụ Kho Tổng chỉ được phép giao cho Nhân viên kho (warehouse_staff).']);
        }

        if (($assignee->warehouse_staff_status ?? 'active') === 'paused') {
            return back()->withErrors(['assigned_to' => 'Nhân viên này đang tạm dừng nhận việc. Vui lòng chọn nhân viên khác.']);
        }

        $task = WarehouseTaskAssignment::create([
            'restaurant_id' => $user->restaurant_id,
            'supply_request_id' => $data['supply_request_id'] ?? null,
            'receiving_voucher_id' => $data['receiving_voucher_id'] ?? null,
            'assigned_to' => $assignee->id,
            'assigned_by' => $user->id,
            'task_type' => $data['task_type'],
            'status' => 'assigned',
            'priority' => $data['priority'],
            'due_at' => $data['due_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::log('warehouse_task_assigned', 'created', $task);

        return back()->with('success', 'Đã phân công nhiệm vụ mới thành công.');
    }

    /**
     * Điều chuyển nhiệm vụ cho nhân viên khác.
     */
    public function reassignTask(Request $request, WarehouseTaskAssignment $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền điều chuyển nhiệm vụ.');
        abort_unless($task->restaurant_id === $user->restaurant_id, 403);

        $data = $request->validate([
            'new_assigned_to' => ['required', TenantRule::exists('users')],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $centralBranch = $this->warehouseService->getCentralWarehouse($user->restaurant_id);
        abort_unless($centralBranch, 422, 'Nhà hàng chưa cấu hình Kho Tổng đang hoạt động.');
        $newAssignee = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereKey($data['new_assigned_to'])
            ->where(function ($query) use ($centralBranch) {
                $query->where('warehouse_branch_id', $centralBranch->id)
                    ->orWhere('branch_id', $centralBranch->id);
            })
            ->findOrFail($data['new_assigned_to']);

        if (! $newAssignee->hasRole('warehouse_staff')) {
            return back()->withErrors(['new_assigned_to' => 'Nhiệm vụ chỉ có thể điều chuyển cho người có vai trò warehouse_staff.']);
        }

        $oldAssigneeName = $task->assignee?->name ?? 'Chưa rõ';

        $task->update([
            'assigned_to' => $newAssignee->id,
            'notes' => trim(($task->notes ?? '')."\n[Điều chuyển từ {$oldAssigneeName} sang {$newAssignee->name}: ".($data['reason'] ?? 'Không có lý do').']'),
        ]);

        AuditLog::log('warehouse_task_reassigned', 'updated', $task, null, ['new_assigned_to' => $newAssignee->id]);

        return back()->with('success', "Đã điều chuyển nhiệm vụ sang cho {$newAssignee->name}.");
    }

    /**
     * Duyệt đơn nghỉ phép / đổi ca của nhân viên Kho Tổng.
     */
    public function approveLeave(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403, 'Bạn không có quyền duyệt đơn nghỉ phép.');
        abort_unless($leave->restaurant_id === $user->restaurant_id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'response_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $leave->update([
            'status' => $data['status'],
            'approved_by' => $user->id,
            'approved_at' => now(),
            'response_notes' => $data['response_notes'] ?? null,
        ]);

        AuditLog::log('warehouse_leave_processed', 'updated', $leave, null, ['status' => $data['status']]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn nghỉ phép.');
    }

    /**
     * API báo cáo KPI toàn đội ngũ.
     */
    public function kpiReport(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canManageWarehouseStaff(), 403);

        $report = $this->kpiService->getTeamKpiReport($user->restaurant_id);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
