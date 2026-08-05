<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\ShiftSwap;
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
     * Tạo mới hoặc cập nhật lịch xếp ca.
     */
    public function storeAssignment(Request $request): RedirectResponse
    {
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
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $leave->branch_id), 403);

        $result = $this->leaveRequests->getReplacementSuggestions($user->restaurant_id, $leave);

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Phê duyệt đơn xin nghỉ phép / nghỉ việc.
     */
    public function approveLeaveRequest(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
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
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
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
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $result = $this->assignments->copyLastWeekSchedules($user->restaurant_id);

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
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
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
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($swap->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch((int) $swap->branch_id), 403);

        $result = $this->shiftSwap->rejectSwap($user, $swap, $request->input('notes'));

        if (! $result['success']) {
            return back()->withErrors(['error' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
