<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\LeaveRequestService;
use App\Services\QuotaService;
use App\Services\ScheduleAssignmentService;
use App\Services\ShiftSwapService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveScheduleController extends Controller
{
    public function __construct(
        private ScheduleAssignmentService $assignments,
        private LeaveRequestService $leaveRequests,
        private ShiftSwapService $shiftSwap,
    ) {}

    /**
     * Bật/Tắt chế độ xếp lịch tự động bằng AI.
     */
    public function toggleAutoSchedule(Request $request): RedirectResponse
    {
        $user = $request->user();
        // [SECURITY P0] Defense-in-depth: route middleware đã chặn, nhưng giữ lại tại đây.
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']) || $user->isSuperAdmin(), 403,
            'Chỉ Quản lý hoặc Trưởng kho được bật/tắt xếp ca tự động.');

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(404, 'Không tìm thấy nhà hàng.');
        }

        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return back()->withErrors(['feature' => 'Gói dịch vụ hiện tại không hỗ trợ tính năng Lịch làm việc. Vui lòng nâng cấp gói.']);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $message = $this->assignments->toggleAutoSchedule($restaurant, $user, $data['enabled']);

        return back()->with('success', $message);
    }

    /**
     * Lấp nhanh các vị trí ca còn trống từ ngày được chọn đến hết tuần.
     */
    public function quickAutoSchedule(Request $request): RedirectResponse
    {
        $user = $request->user();
        // [SECURITY P0] Defense-in-depth
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']) || $user->isSuperAdmin(), 403,
            'Chỉ Quản lý hoặc Trưởng kho được tạo lịch tự động.');

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(404, 'Không tìm thấy nhà hàng.');
        }

        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return back()->withErrors(['feature' => 'Gói dịch vụ hiện tại không hỗ trợ tính năng Lịch làm việc. Vui lòng nâng cấp gói.']);
        }

        $result = $this->assignments->quickAutoSchedule($restaurant, $user);

        if (! $result['success']) {
            return back()->withErrors(['quick_schedule' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Tạo mới hoặc cập nhật lịch xếp ca.
     */
    public function storeAssignment(Request $request): RedirectResponse
    {
        // [SECURITY P0] Defense-in-depth: nhân viên thường không được xếp ca cho người khác.
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']) || $user->isSuperAdmin(), 403,
            'Chỉ Quản lý hoặc Trưởng kho được xếp ca nhân sự.');

        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['nullable', 'string'],
            'shift_id' => ['nullable', 'integer'],
        ]);

        $result = $this->assignments->storeAssignment(
            $request->user(),
            $data,
        );

        if (! $result['success']) {
            return back()->withErrors([$result['field'] => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Hủy xếp ca nhân sự.
     */
    public function destroyAssignment(Request $request): RedirectResponse
    {
        // [SECURITY P0] Defense-in-depth
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']) || $user->isSuperAdmin(), 403,
            'Chỉ Quản lý hoặc Trưởng kho được hủy xếp ca.');

        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['nullable', 'string'],
            'shift_id' => ['nullable', 'integer'],
        ]);

        $this->assignments->destroyAssignment($request->user(), $data);

        return back()->with('success', 'Hủy xếp ca thành công.');
    }

    /**
     * Nộp đơn xin nghỉ phép / nghỉ việc.
     */
    public function storeLeaveRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'leave_type' => ['required', 'string', 'in:annual,sick,unpaid,emergency,resignation'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->leaveRequests->storeLeaveRequest(
            $request->user(),
            $data,
            $request->input('bypass_code'),
            $request->input('bypass_reason'),
            $request->ip(),
            $request->userAgent() ?? '',
        );

        if (! $result['success']) {
            return back()->withErrors([$result['field'] => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Lấy các gợi ý thế chỗ nhân sự cho đơn nghỉ phép.
     */
    public function getReplacementSuggestions(Request $request, LeaveRequest $leave): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $leave->branch_id), 403);

        $result = $this->leaveRequests->getReplacementSuggestions($user->restaurant_id, $leave);

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Thay ca KHẨN CẤP: nhân viên nghỉ đột xuất/không đến ca → quản lý xếp người thay
     * ngay. Ca gốc đánh dấu vắng; ca thay được tạo, liên kết ngược và ghi lý do. Quản
     * lý KHÔNG được tự xếp mình vào ca thay (chống tự duyệt tăng ca cho bản thân).
     */
    public function emergencyReplace(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'integer', TenantRule::exists('schedule_assignments')],
            'replacement_employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $original = ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->findOrFail($data['assignment_id']);
        abort_unless($user->canAccessBranch($original->branch_id), 403);

        $replacement = Employee::where('restaurant_id', $user->restaurant_id)
            ->findOrFail($data['replacement_employee_id']);

        if ($replacement->id === $original->employee_id) {
            return back()->withErrors(['replacement_employee_id' => 'Người thay phải khác người nghỉ.']);
        }

        // Guardrail: Quản lý (không phải Chủ) KHÔNG được tự xếp mình vào ca thay.
        $isOwner = $user->isSuperAdmin() || $user->isOwner();
        if (! $isOwner && $replacement->user_id === $user->id) {
            return back()->withErrors([
                'replacement_employee_id' => 'Quản lý không được tự xếp mình vào ca thay (tăng ca cho bản thân) — cần Chủ duyệt.',
            ]);
        }

        try {
            $newAssignment = \Illuminate\Support\Facades\DB::transaction(function () use ($original, $replacement, $data, $user) {
                $original->update([
                    'status' => 'absent',
                    'notes' => trim(($original->notes ? $original->notes.' | ' : '').'Nghỉ đột xuất: '.$data['reason']),
                ]);

                return ScheduleAssignment::create([
                    'restaurant_id' => $original->restaurant_id,
                    'branch_id' => $original->branch_id,
                    'employee_id' => $replacement->id,
                    'shift_id' => $original->shift_id,
                    'scheduled_date' => $original->scheduled_date,
                    'status' => 'confirmed',
                    'approved_by' => $user->id,
                    'replaced_assignment_id' => $original->id,
                    'replacement_reason' => $data['reason'],
                    'notes' => 'Thay ca khẩn cấp cho '.($original->employee?->full_name ?? 'nhân viên nghỉ'),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xếp người thay: '.$e->getMessage());
        }

        // Báo Chủ để minh bạch (tránh manager lạm dụng xếp ca).
        User::where('restaurant_id', $user->restaurant_id)->role('owner')
            ->where('id', '!=', $user->id)->get()
            ->each(fn (User $o) => $o->notify(new \App\Notifications\EmergencyShiftReplacedNotification($newAssignment, $user->name)));

        AuditLog::log('shift_emergency_replaced', 'updated', $newAssignment, null, [
            'replaced_assignment_id' => $original->id,
            'replacement_employee_id' => $replacement->id,
            'reason' => $data['reason'],
            'by' => $user->name,
        ]);

        return back()->with('success', 'Đã xếp người thay ca khẩn cấp và báo Chủ.');
    }

    /**
     * Phê duyệt đơn xin nghỉ phép / nghỉ việc.
     */
    public function approveLeaveRequest(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $leave->branch_id), 403);
        abort_unless($leave->status === 'pending', 422);

        // Self-Approval Prevention Check
        abort_if(
            $leave->requested_by === $user->id ||
            ($leave->employee && $leave->employee->user_id === $user->id),
            403,
            'Bạn không thể tự phê duyệt đơn xin nghỉ của chính mình.'
        );

        $result = $this->leaveRequests->approveLeaveRequest($user, $leave, $request->input('replacements', []));

        if (! $result['success']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Từ chối đơn xin nghỉ phép / nghỉ việc.
     */
    public function rejectLeaveRequest(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $leave->branch_id), 403);
        abort_unless($leave->status === 'pending', 422);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->leaveRequests->rejectLeaveRequest($user, $leave, $data['rejection_reason']);

        return back()->with('success', 'Đã từ chối đơn xin nghỉ.');
    }

    /**
     * Sao chép lịch xếp ca từ tuần trước sang tuần hiện tại.
     */
    public function copyLastWeekSchedules(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);

        $branchId = $request->integer('branch_id') ?: ($user->canViewAllBranches() ? null : $user->assignedBranchId());
        $result = $this->assignments->copyLastWeekSchedules($user->restaurant_id, $branchId);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Phê duyệt yêu cầu đổi ca.
     */
    public function approveSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);
        abort_if($swap->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $swap->branch_id), 403);
        abort_unless($swap->status === 'accepted', 422);

        // Self-Approval Prevention Check
        $userEmployeeId = $user->employee?->id;
        $requesterEmployeeId = $swap->requesterAssignment?->employee_id;
        $receiverEmployeeId = $swap->receiverAssignment?->employee_id;

        if ($userEmployeeId && ($userEmployeeId === $requesterEmployeeId || $userEmployeeId === $receiverEmployeeId)) {
            abort(403, 'Bạn không thể phê duyệt yêu cầu đổi ca liên quan đến chính mình.');
        }

        $result = $this->shiftSwap->approveSwap($user, $swap, $request->input('notes'));

        if (! $result['success']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Từ chối yêu cầu đổi ca.
     */
    public function rejectSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager']), 403);
        abort_if($swap->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $swap->branch_id), 403);

        $result = $this->shiftSwap->rejectSwap($user, $swap, $request->input('notes'));

        if (! $result['success']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
