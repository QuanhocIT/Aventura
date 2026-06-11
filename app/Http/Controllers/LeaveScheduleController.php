<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveScheduleController extends Controller
{    /**
     * Bật/Tắt chế độ xếp lịch tự động bằng AI.
     */
    public function toggleAutoSchedule(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurant;
        if (!$restaurant) {
            abort(404, 'Không tìm thấy nhà hàng.');
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $restaurant->update([
            'auto_schedule' => $data['enabled'],
        ]);

        if ($data['enabled']) {
            // Auto generate schedules for the current calendar week
            $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

            // 1. Xóa tất cả lịch xếp ca hiện tại trong tuần này
            ScheduleAssignment::where('restaurant_id', $restaurant->id)
                ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                ->delete();

            // 2. Lấy danh sách nhân viên đang hoạt động
            $activeEmployees = Employee::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->get();

            // 3. Lấy danh sách các ca làm việc đang hoạt động
            $activeShifts = WorkShift::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->get();

            if ($activeEmployees->isNotEmpty() && $activeShifts->isNotEmpty()) {
                // Track shift count per employee to limit workload (max 6 shifts/week)
                $employeeShiftCounts = [];
                foreach ($activeEmployees as $emp) {
                    $employeeShiftCounts[$emp->id] = 0;
                }

                // Lặp qua 7 ngày trong tuần
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $startOfWeek->copy()->addDays($i);
                    $dateStr = $currentDate->toDateString();

                    // Xác định xem nhân viên nào đang nghỉ phép (đã được duyệt) vào ngày này
                    $onLeaveEmployeeIds = LeaveRequest::where('restaurant_id', $restaurant->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->pluck('employee_id')
                        ->toArray();

                    $availableEmployees = $activeEmployees->reject(fn ($e) => in_array($e->id, $onLeaveEmployeeIds))->values();

                    if ($availableEmployees->isNotEmpty()) {
                        // Get availability registrations for today
                        $registrationsToday = ScheduleRegistration::where('restaurant_id', $restaurant->id)
                            ->whereDate('scheduled_date', $dateStr)
                            ->get()
                            ->groupBy('shift_id');

                        foreach ($activeShifts as $shift) {
                            // Phân phối tối đa 2 nhân viên cho mỗi ca trực (nếu có đủ nhân sự)
                            $empPerShift = $availableEmployees->count() >= 3 ? 2 : 1;
                            $assignedForThisShift = [];
                            
                            for ($j = 0; $j < $empPerShift; $j++) {
                                $candidates = $availableEmployees->reject(function ($e) use ($assignedForThisShift, $employeeShiftCounts, $shift, $dateStr) {
                                    // Already assigned to this shift today
                                    if (in_array($e->id, $assignedForThisShift)) {
                                        return true;
                                    }
                                    // Max 6 shifts per week constraint
                                    if (($employeeShiftCounts[$e->id] ?? 0) >= 6) {
                                        return true;
                                    }
                                    // Already assigned to some shift today (prevent double booking)
                                    $isAssignedToday = ScheduleAssignment::where('employee_id', $e->id)
                                        ->whereDate('scheduled_date', $dateStr)
                                        ->exists();
                                    if ($isAssignedToday) {
                                        return true;
                                    }
                                    return false;
                                });

                                if ($candidates->isEmpty()) {
                                    break;
                                }

                                // Sort candidates:
                                // 1. Registered available for this shift (registered_available = true)
                                // 2. Role balance: Prefer adding a different role to this shift if one is already assigned
                                // 3. Lowest shift count so far in the week.
                                $candidates = $candidates->sortBy(function ($cand) use ($registrationsToday, $shift, $assignedForThisShift, $employeeShiftCounts) {
                                    $hasRegistered = isset($registrationsToday[$shift->id]) && $registrationsToday[$shift->id]->contains('employee_id', $cand->id);
                                    $registrationScore = $hasRegistered ? 0 : 1;

                                    $shiftCount = $employeeShiftCounts[$cand->id] ?? 0;

                                    $roleScore = 0;
                                    if (!empty($assignedForThisShift)) {
                                        $assignedRoles = Employee::whereIn('id', $assignedForThisShift)->pluck('role_id')->toArray();
                                        if (in_array($cand->role_id, $assignedRoles)) {
                                            $roleScore = 1;
                                        }
                                    }

                                    return sprintf('%d-%02d-%d', $registrationScore, $shiftCount, $roleScore);
                                });

                                $bestCandidate = $candidates->first();
                                if ($bestCandidate) {
                                    ScheduleAssignment::create([
                                        'restaurant_id' => $restaurant->id,
                                        'branch_id'     => $bestCandidate->branch_id ?? $user->branch_id,
                                        'employee_id'   => $bestCandidate->id,
                                        'shift_id'      => $shift->id,
                                        'scheduled_date'=> $dateStr,
                                        'status'        => 'scheduled',
                                    ]);

                                    $assignedForThisShift[] = $bestCandidate->id;
                                    $employeeShiftCounts[$bestCandidate->id] = ($employeeShiftCounts[$bestCandidate->id] ?? 0) + 1;
                                }
                            }
                        }
                    }
                }
            }

            return back()->with('success', 'Chế độ xếp lịch tự động đã được KÍCH HOẠT. AI đã tự động phân bổ ca trực tối ưu cho tất cả nhân sự.');
        }

        return back()->with('success', 'Chế độ xếp lịch tự động đã TẮT. Bây giờ bạn có thể tự xếp lịch thủ công.');
    }

    /**
     * Tạo mới hoặc cập nhật lịch xếp ca.
     */
    public function storeAssignment(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['required', 'string'],
        ]);

        // Find employee
        $employee = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('full_name', $data['employee_name'])
            ->first();

        if (!$employee) {
            return back()->withErrors(['employee_name' => 'Nhân viên không tồn tại.']);
        }

        // Find shift (match name prefix)
        $shift = WorkShift::where('restaurant_id', $user->restaurant_id)
            ->where('name', 'like', $data['shift_name'] . '%')
            ->first();

        if (!$shift) {
            return back()->withErrors(['shift_name' => 'Ca làm việc không tồn tại.']);
        }

        // Calculate date of current week's day
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $days = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
            'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
        ];
        $offset = $days[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        // Save schedule
        ScheduleAssignment::updateOrCreate([
            'restaurant_id' => $user->restaurant_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $scheduledDate,
        ], [
            'status' => 'scheduled',
        ]);

        return back()->with('success', 'Xếp ca thành công.');
    }

    /**
     * Hủy xếp ca nhân sự.
     */
    public function destroyAssignment(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'day' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'employee_name' => ['required', 'string'],
            'shift_name' => ['required', 'string'],
        ]);

        // Find employee
        $employee = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('full_name', $data['employee_name'])
            ->first();

        if (!$employee) {
            return back()->with('success', 'Hủy xếp ca thành công.');
        }

        // Find shift
        $shift = WorkShift::where('restaurant_id', $user->restaurant_id)
            ->where('name', 'like', $data['shift_name'] . '%')
            ->first();

        if (!$shift) {
            return back()->with('success', 'Hủy xếp ca thành công.');
        }

        // Calculate date of current week's day
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $days = [
            'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
            'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
        ];
        $offset = $days[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        // Delete assignment
        ScheduleAssignment::where('restaurant_id', $user->restaurant_id)
            ->where('employee_id', $employee->id)
            ->where('shift_id', $shift->id)
            ->where('scheduled_date', $scheduledDate)
            ->delete();

        return back()->with('success', 'Hủy xếp ca thành công.');
    }

    /**
     * Nộp đơn xin nghỉ phép / nghỉ việc.
     */
    public function storeLeaveRequest(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type'  => ['required', 'string', 'in:annual,sick,unpaid,emergency,resignation'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'reason'      => ['nullable', 'string', 'max:1000'],
        ]);

        LeaveRequest::create([
            'restaurant_id' => $user->restaurant_id,
            'employee_id'   => $data['employee_id'],
            'requested_by'  => $user->id,
            'leave_type'    => $data['leave_type'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'reason'        => $data['reason'] ?? '',
            'status'        => 'pending',
        ]);

        return back()->with('success', 'Nộp đơn xin nghỉ thành công.');
    }

    /**
     * Lấy các gợi ý thế chỗ nhân sự cho đơn nghỉ phép.
     */
    public function getReplacementSuggestions(Request $request, LeaveRequest $leave): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);

        $employee = $leave->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Nhân viên không tồn tại.'], 404);
        }

        $startDate = $leave->start_date->toDateString();
        $endDate = $leave->end_date->toDateString();

        $assignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->whereBetween('scheduled_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['shift'])
            ->get();

        $data = [];
        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (!$shift) continue;

            $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                ? $assignment->scheduled_date->toDateString()
                : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

            // Lấy các ứng viên có cùng vai trò chuyên môn, đang hoạt động và không trùng lịch trực ngày đó
            $candidates = Employee::where('restaurant_id', $user->restaurant_id)
                ->where('role_id', $employee->role_id)
                ->where('id', '!=', $employee->id)
                ->where('status', 'active')
                ->get();

            // Fetch scheduled and registered employee IDs for this date/shift in bulk
            $scheduledEmployeeIds = ScheduleAssignment::whereDate('scheduled_date', $dateStr)
                ->whereIn('status', ['scheduled', 'checked_in'])
                ->pluck('employee_id')
                ->toArray();

            $registeredEmployeeIds = ScheduleRegistration::whereDate('scheduled_date', $dateStr)
                ->where('shift_id', $shift->id)
                ->pluck('employee_id')
                ->toArray();

            $suggestions = [];
            foreach ($candidates as $cand) {
                // Kiểm tra xem ứng viên này đã có lịch trực nào vào ngày đó chưa
                if (in_array($cand->id, $scheduledEmployeeIds)) {
                    continue;
                }

                // Kiểm tra đăng ký rảnh (ScheduleRegistration)
                $isRegistered = in_array($cand->id, $registeredEmployeeIds);

                $suggestions[] = [
                    'id' => $cand->id,
                    'full_name' => $cand->full_name,
                    'employee_code' => $cand->employee_code,
                    'registered_available' => $isRegistered,
                ];
            }

            // Ưu tiên nhân viên đăng ký rảnh lên đầu
            usort($suggestions, function ($a, $b) {
                return $b['registered_available'] <=> $a['registered_available'];
            });

            $formattedDate = $assignment->scheduled_date instanceof \Carbon\Carbon
                ? $assignment->scheduled_date->format('d/m/Y')
                : \Carbon\Carbon::parse($assignment->scheduled_date)->format('d/m/Y');

            $data[] = [
                'assignment_id' => $assignment->id,
                'date' => $dateStr,
                'formatted_date' => $formattedDate,
                'shift_id' => $shift->id,
                'shift_name' => $shift->name ? explode(' (', $shift->name)[0] : 'Ca Mới',
                'shift_time' => $shift->start_time . ' - ' . $shift->end_time,
                'suggestions' => $suggestions,
            ];
        }

        return response()->json([
            'success' => true,
            'leave_id' => $leave->id,
            'employee_name' => $employee->full_name,
            'leave_type' => $leave->leave_type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'assignments' => $data,
        ]);
    }

    /**
     * Phê duyệt đơn xin nghỉ phép / nghỉ việc.
     */
    public function approveLeaveRequest(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($leave->status === 'pending', 422);

        $leave->update([
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);

        $employee = $leave->employee;
        $employeeUser = $employee?->user;

        if ($employee) {
            if ($leave->leave_type === 'resignation') {
                // Chuyển trạng thái sang terminated
                $employee->update(['status' => 'terminated']);

                // Vô hiệu hóa tài khoản
                $empUser = $employee->user;
                if ($empUser) {
                    $empUser->update(['status' => 'inactive']);
                }

                // Kích hoạt Xóa mềm
                $employee->delete();
            } else {
                $replacements = $request->input('replacements', []);

                $assignments = ScheduleAssignment::where('employee_id', $employee->id)->get();
                foreach ($assignments as $assignment) {
                    $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                        ? $assignment->scheduled_date->toDateString()
                        : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

                    if ($dateStr >= $leave->start_date->toDateString() && $dateStr <= $leave->end_date->toDateString()) {
                        // Cập nhật trạng thái lịch làm sang leave_approved
                        $assignment->update(['status' => 'leave_approved']);

                        // Tạo lịch trực mới cho nhân viên thay thế (nếu có)
                        if (!empty($replacements[$assignment->id])) {
                            $replacementEmpId = $replacements[$assignment->id];
                            $replacementEmp = Employee::where('restaurant_id', $user->restaurant_id)
                                ->where('id', $replacementEmpId)
                                ->where('status', 'active')
                                ->first();

                            if ($replacementEmp) {
                                ScheduleAssignment::create([
                                    'restaurant_id' => $user->restaurant_id,
                                    'branch_id' => $assignment->branch_id,
                                    'employee_id' => $replacementEmp->id,
                                    'shift_id' => $assignment->shift_id,
                                    'scheduled_date' => $assignment->scheduled_date,
                                    'status' => 'scheduled',
                                ]);
                            }
                        }
                    }
                }
            }
        }

        if ($employeeUser) {
            $employeeUser->notify(new \App\Notifications\LeaveRequestNotification(
                $leave,
                'approved',
                'Đơn xin nghỉ của bạn đã được Quản lý/Chủ nhà hàng phê duyệt.'
            ));
        }

        return back()->with('success', 'Phê duyệt đơn xin nghỉ thành công.');
    }

    /**
     * Từ chối đơn xin nghỉ phép / nghỉ việc.
     */
    public function rejectLeaveRequest(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($leave->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($leave->status === 'pending', 422);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'reason' => $leave->reason . "\n[Từ chối: " . $data['rejection_reason'] . "]",
        ]);

        $employeeUser = $leave->employee?->user;
        if ($employeeUser) {
            $employeeUser->notify(new \App\Notifications\LeaveRequestNotification(
                $leave,
                'rejected',
                "Đơn xin nghỉ của bạn bị từ chối: {$data['rejection_reason']}"
            ));
        }

        return back()->with('success', 'Đã từ chối đơn xin nghỉ.');
    }

    /**
     * Sao chép lịch xếp ca từ tuần trước sang tuần hiện tại.
     */
    public function copyLastWeekSchedules(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurantId = $user->restaurant_id;

        // Current week boundaries
        $startOfCurrentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfCurrentWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Last week boundaries
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY);

        // Get all assignments from last week
        $lastWeekAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereBetween('scheduled_date', [$startOfLastWeek->toDateString(), $endOfLastWeek->toDateString()])
            ->get();

        if ($lastWeekAssignments->isEmpty()) {
            return back()->with('error', 'Không tìm thấy lịch trực nào từ tuần trước để sao chép.');
        }

        // Delete current week schedules first
        ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->whereBetween('scheduled_date', [$startOfCurrentWeek->toDateString(), $endOfCurrentWeek->toDateString()])
            ->delete();

        $copiedCount = 0;

        foreach ($lastWeekAssignments as $assignment) {
            // Calculate corresponding day in current week
            $lastWeekDate = Carbon::parse($assignment->scheduled_date);
            $dayOffset = $lastWeekDate->diffInDays($startOfLastWeek); // days since Monday of last week
            $currentWeekDateStr = $startOfCurrentWeek->copy()->addDays($dayOffset)->toDateString();

            // Check if employee is on approved leave on that day
            $isOnLeave = LeaveRequest::where('restaurant_id', $restaurantId)
                ->where('employee_id', $assignment->employee_id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $currentWeekDateStr)
                ->whereDate('end_date', '>=', $currentWeekDateStr)
                ->exists();

            if ($isOnLeave) {
                continue; // Skip copying this assignment as they are on leave
            }

            // Duplicate assignment
            ScheduleAssignment::create([
                'restaurant_id'  => $restaurantId,
                'branch_id'      => $assignment->branch_id,
                'employee_id'    => $assignment->employee_id,
                'shift_id'       => $assignment->shift_id,
                'scheduled_date' => $currentWeekDateStr,
                'status'         => 'scheduled',
            ]);

            $copiedCount++;
        }

        return back()->with('success', "Đã sao chép thành công {$copiedCount} phân công lịch trực từ tuần trước.");
    }

    /**
     * Phê duyệt yêu cầu đổi ca.
     */
    public function approveSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($swap->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($swap->status === 'accepted', 422);

        \Illuminate\Support\Facades\DB::transaction(function () use ($swap, $user, $request) {
            $reqAssignment = $swap->requesterAssignment;
            $recAssignment = $swap->receiverAssignment;

            if ($reqAssignment && $recAssignment) {
                // Swap employee_ids
                $tempEmpId = $reqAssignment->employee_id;
                $reqAssignment->update(['employee_id' => $recAssignment->employee_id]);
                $recAssignment->update(['employee_id' => $tempEmpId]);
            }

            $swap->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'notes' => $request->input('notes', 'Phê duyệt bởi Quản lý/Chủ nhà hàng')
            ]);
        });

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        $receiverUser = $swap->receiverAssignment?->employee?->user;

        if ($requesterUser) {
            $requesterUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'approved',
                "Yêu cầu đổi ca trực của bạn đã được Quản lý phê duyệt thành công."
            ));
        }
        if ($receiverUser) {
            $receiverUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'approved',
                "Yêu cầu đổi ca trực của bạn đã được Quản lý phê duyệt thành công."
            ));
        }

        return back()->with('success', 'Đã phê duyệt yêu cầu đổi ca làm việc thành công.');
    }

    /**
     * Từ chối yêu cầu đổi ca.
     */
    public function rejectSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($swap->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($swap->status === 'accepted', 422);

        $swap->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'notes' => $request->input('notes', 'Từ chối bởi Quản lý/Chủ nhà hàng')
        ]);

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        $receiverUser = $swap->receiverAssignment?->employee?->user;

        $reason = $request->input('notes', 'Từ chối bởi Quản lý/Chủ nhà hàng');
        if ($requesterUser) {
            $requesterUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'rejected',
                "Yêu cầu đổi ca trực của bạn bị Quản lý từ chối: {$reason}"
            ));
        }
        if ($receiverUser) {
            $receiverUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'rejected',
                "Yêu cầu đổi ca trực của bạn bị Quản lý từ chối: {$reason}"
            ));
        }

        return back()->with('success', 'Đã từ chối yêu cầu đổi ca làm việc.');
    }

}
