<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use App\Models\WorkShift;
use App\Services\SalaryService;
use App\Notifications\ScheduleUpdatedNotification;
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
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(404, 'Không tìm thấy nhà hàng.');
        }

        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return back()->withErrors(['feature' => 'Gói dịch vụ hiện tại không hỗ trợ tính năng Lịch làm việc. Vui lòng nâng cấp gói.']);
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

                // Eager load approved leaves and registrations for the entire week outside the day loop
                $approvedLeavesThisWeek = LeaveRequest::where('restaurant_id', $restaurant->id)
                    ->where('status', 'approved')
                    ->where('start_date', '<=', $endOfWeek->toDateString())
                    ->where('end_date', '>=', $startOfWeek->toDateString())
                    ->get();

                $registrationsThisWeek = ScheduleRegistration::where('restaurant_id', $restaurant->id)
                    ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                    ->get()
                    ->groupBy(function ($r) {
                        return $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString();
                    });

                // Lặp qua 7 ngày trong tuần
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $startOfWeek->copy()->addDays($i);
                    $dateStr = $currentDate->toDateString();

                    // Track employee IDs assigned today to prevent double booking in-memory
                    $assignedTodayEmployeeIds = [];

                    // Xác định xem nhân viên nào đang nghỉ phép (đã được duyệt) vào ngày này
                    $onLeaveEmployeeIds = $approvedLeavesThisWeek->filter(function ($leave) use ($dateStr) {
                        $start = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : Carbon::parse($leave->start_date)->toDateString();
                        $end = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : Carbon::parse($leave->end_date)->toDateString();
                        return $start <= $dateStr && $end >= $dateStr;
                    })->pluck('employee_id')->toArray();

                    $availableEmployees = $activeEmployees->reject(fn ($e) => in_array($e->id, $onLeaveEmployeeIds))->values();

                    if ($availableEmployees->isNotEmpty()) {
                        // Get availability registrations for today
                        $registrationsToday = isset($registrationsThisWeek[$dateStr])
                            ? $registrationsThisWeek[$dateStr]->groupBy('shift_id')
                            : collect();

                        foreach ($activeShifts as $shift) {
                            // Phân phối tối đa 2 nhân viên cho mỗi ca trực (nếu có đủ nhân sự)
                            $empPerShift = $availableEmployees->count() >= 3 ? 2 : 1;
                            $assignedForThisShift = [];
                            
                            for ($j = 0; $j < $empPerShift; $j++) {
                                $candidates = $availableEmployees->reject(function ($e) use ($assignedForThisShift, $employeeShiftCounts, $shift, $assignedTodayEmployeeIds) {
                                    // Already assigned to this shift today
                                    if (in_array($e->id, $assignedForThisShift)) {
                                        return true;
                                    }
                                    // Max 6 shifts per week constraint
                                    if (($employeeShiftCounts[$e->id] ?? 0) >= 6) {
                                        return true;
                                    }
                                    // Already assigned to some shift today (prevent double booking)
                                    if (in_array($e->id, $assignedTodayEmployeeIds)) {
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
                                $candidates = $candidates->sortBy(function ($cand) use ($registrationsToday, $shift, $assignedForThisShift, $employeeShiftCounts, $availableEmployees) {
                                    $hasRegistered = isset($registrationsToday[$shift->id]) && $registrationsToday[$shift->id]->contains('employee_id', $cand->id);
                                    $registrationScore = $hasRegistered ? 0 : 1;

                                    $shiftCount = $employeeShiftCounts[$cand->id] ?? 0;

                                    $roleScore = 0;
                                    if (!empty($assignedForThisShift)) {
                                        $assignedRoles = $availableEmployees->whereIn('id', $assignedForThisShift)->pluck('role_id')->toArray();
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
                                    $assignedTodayEmployeeIds[] = $bestCandidate->id;
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

        // 11-hour rest rule check
        $startProposed = Carbon::parse($scheduledDate . ' ' . $shift->start_time);
        $endProposed = $shift->is_overnight 
            ? Carbon::parse($scheduledDate . ' ' . $shift->end_time)->addDay()
            : Carbon::parse($scheduledDate . ' ' . $shift->end_time);

        $adjacentAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('scheduled_date', '!=', $scheduledDate) // exclude the slot we are trying to set/update
            ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
            ->whereBetween('scheduled_date', [
                Carbon::parse($scheduledDate)->subDays(1)->toDateString(),
                Carbon::parse($scheduledDate)->addDays(1)->toDateString()
            ])
            ->with('shift')
            ->get();

        foreach ($adjacentAssignments as $aa) {
            $aaShift = $aa->shift;
            if (!$aaShift) continue;

            $dateStr = $aa->scheduled_date instanceof Carbon ? $aa->scheduled_date->toDateString() : Carbon::parse($aa->scheduled_date)->toDateString();
            $startExist = Carbon::parse($dateStr . ' ' . $aaShift->start_time);
            $endExist = $aaShift->is_overnight
                ? Carbon::parse($dateStr . ' ' . $aaShift->end_time)->addDay()
                : Carbon::parse($dateStr . ' ' . $aaShift->end_time);

            // 1. Overlap Check
            if ($startProposed->lt($endExist) && $endProposed->gt($startExist)) {
                return back()->withErrors(['shift_name' => "Nhân viên {$employee->full_name} đã có ca làm việc trùng lặp trong thời gian này."]);
            }

            // 2. Rest hour check (11 hours)
            if ($endProposed->lte($startExist)) {
                $restHours = $endProposed->diffInSeconds($startExist) / 3600.0;
                if ($restHours < 11.0) {
                    return back()->withErrors(['shift_name' => "[⚠️ Vi phạm nghỉ 11h] Nhân viên {$employee->full_name} không có đủ 11 tiếng nghỉ ngơi giữa các ca làm việc."]);
                }
            } elseif ($endExist->lte($startProposed)) {
                $restHours = $endExist->diffInSeconds($startProposed) / 3600.0;
                if ($restHours < 11.0) {
                    return back()->withErrors(['shift_name' => "[⚠️ Vi phạm nghỉ 11h] Nhân viên {$employee->full_name} không có đủ 11 tiếng nghỉ ngơi giữa các ca làm việc."]);
                }
            }
        }

        // Save schedule
        ScheduleAssignment::updateOrCreate([
            'restaurant_id' => $user->restaurant_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $scheduledDate,
        ], [
            'status' => 'scheduled',
        ]);

        $employeeUser = $employee->user;
        if ($employeeUser) {
            $dateFormatted = Carbon::parse($scheduledDate)->format('d/m/Y');
            $employeeUser->notify(new ScheduleUpdatedNotification(
                "Bạn đã được phân công ca trực mới vào ngày {$dateFormatted}.",
                $scheduledDate
            ));
        }

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

        $employee = Employee::where('restaurant_id', $user->restaurant_id)->findOrFail($data['employee_id']);

        // 1. Overlapping Leave Check
        $overlapping = LeaveRequest::where('restaurant_id', $user->restaurant_id)
            ->where('employee_id', $employee->id)
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_date', '<=', $data['start_date'])
                            ->where('end_date', '>=', $data['end_date']);
                    });
            })
            ->exists();

        if ($overlapping) {
            return back()->withErrors(['start_date' => 'Yêu cầu nghỉ phép trùng lặp với đơn nghỉ phép đã đăng ký trước đó.']);
        }

        // 2. Department/Role Quota Check (Max 30% role limit)
        $totalRoleEmployees = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('role_id', $employee->role_id)
            ->where('status', 'active')
            ->count();

        if ($totalRoleEmployees > 0) {
            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);

            $activeLeaves = LeaveRequest::where('restaurant_id', $user->restaurant_id)
                ->whereIn('status', ['approved', 'pending'])
                ->whereDate('start_date', '<=', $endDate->toDateString())
                ->whereDate('end_date', '>=', $startDate->toDateString())
                ->whereHas('employee', function ($q) use ($employee) {
                    $q->where('role_id', $employee->role_id);
                })
                ->get();

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateStr = $date->toDateString();

                $onLeaveCount = $activeLeaves->filter(function ($leave) use ($dateStr) {
                    $start = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : Carbon::parse($leave->start_date)->toDateString();
                    $end = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : Carbon::parse($leave->end_date)->toDateString();
                    return $start <= $dateStr && $end >= $dateStr;
                })->count();

                if (($onLeaveCount + 1) / $totalRoleEmployees > 0.30) {
                    return back()->withErrors(['start_date' => "Vượt quá giới hạn nghỉ phép đồng thời của bộ phận vào ngày {$date->format('d/m/Y')} (Tối đa 30% nhân sự bộ phận được nghỉ)."]);
                }
            }
        }

        // 3. Schedule Conflict Warning Check
        $hasSchedules = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereDate('scheduled_date', '>=', $data['start_date'])
            ->whereDate('scheduled_date', '<=', $data['end_date'])
            ->exists();

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

        $warning = $hasSchedules ? ' Lưu ý: Nhân viên đã có ca trực được xếp trong thời gian này, vui lòng điều chỉnh/đổi ca trực.' : '';

        return back()->with('success', 'Nộp đơn xin nghỉ thành công.' . $warning);
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

        // Lấy các ứng viên có cùng vai trò chuyên môn, đang hoạt động bên ngoài vòng lặp
        $candidates = Employee::where('restaurant_id', $user->restaurant_id)
            ->where('role_id', $employee->role_id)
            ->where('id', '!=', $employee->id)
            ->where('status', 'active')
            ->get();

        // Pre-fetch all assignments and registrations in target date range to avoid in-loop queries
        $allAssignments = ScheduleAssignment::whereBetween('scheduled_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->get()
            ->groupBy(function ($a) {
                return $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString();
            });

        $allRegistrations = ScheduleRegistration::whereBetween('scheduled_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get()
            ->groupBy(function ($r) {
                $dateStr = $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString();
                return $dateStr . '-' . $r->shift_id;
            });

        $data = [];
        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (!$shift) continue;

            $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                ? $assignment->scheduled_date->toDateString()
                : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

            $scheduledEmployeeIds = isset($allAssignments[$dateStr])
                ? $allAssignments[$dateStr]->pluck('employee_id')->toArray()
                : [];

            $regKey = $dateStr . '-' . $shift->id;
            $registeredEmployeeIds = isset($allRegistrations[$regKey])
                ? $allRegistrations[$regKey]->pluck('employee_id')->toArray()
                : [];

            $suggestions = [];
            foreach ($candidates as $cand) {
                // Kiểm tra xem ứng viên này đã có lịch trực nào vào ngày đó chưa
                if (in_array($cand->id, $scheduledEmployeeIds)) {
                    continue;
                }

                // Kiểm tra đăng ký rảnh (ScheduleRegistration)
                $isRegistered = in_array($cand->id, $registeredEmployeeIds);

                // Tính số ca làm việc trong tuần của ứng viên (Overtime Check)
                $carbonDate = Carbon::parse($dateStr);
                $startOfWeek = $carbonDate->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $endOfWeek = $carbonDate->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                
                $weeklyShiftsCount = ScheduleAssignment::where('employee_id', $cand->id)
                    ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                    ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                    ->count();
                
                $hasOvertimeViolation = ($weeklyShiftsCount >= 6);

                // Kiểm tra luật nghỉ 11 tiếng giữa các ca (11-hour rest rule check)
                $startProposed = Carbon::parse($dateStr . ' ' . $shift->start_time);
                $endProposed = $shift->is_overnight
                    ? Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay()
                    : Carbon::parse($dateStr . ' ' . $shift->end_time);

                $hasRestViolation = false;
                $adjacentAssignments = ScheduleAssignment::where('employee_id', $cand->id)
                    ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                    ->whereBetween('scheduled_date', [
                        Carbon::parse($dateStr)->subDays(1)->toDateString(),
                        Carbon::parse($dateStr)->addDays(1)->toDateString()
                    ])
                    ->with('shift')
                    ->get();

                foreach ($adjacentAssignments as $aa) {
                    $aaShift = $aa->shift;
                    if (!$aaShift) continue;

                    $aaDateStr = $aa->scheduled_date instanceof Carbon ? $aa->scheduled_date->toDateString() : Carbon::parse($aa->scheduled_date)->toDateString();
                    $startExist = Carbon::parse($aaDateStr . ' ' . $aaShift->start_time);
                    $endExist = $aaShift->is_overnight
                        ? Carbon::parse($aaDateStr . ' ' . $aaShift->end_time)->addDay()
                        : Carbon::parse($aaDateStr . ' ' . $aaShift->end_time);

                    if ($endProposed->lte($startExist)) {
                        $restHours = $endProposed->diffInSeconds($startExist) / 3600.0;
                        if ($restHours < 11.0) {
                            $hasRestViolation = true;
                            break;
                        }
                    } elseif ($endExist->lte($startProposed)) {
                        $restHours = $endExist->diffInSeconds($startProposed) / 3600.0;
                        if ($restHours < 11.0) {
                            $hasRestViolation = true;
                            break;
                        }
                    }
                }

                $hasWarning = $hasOvertimeViolation || $hasRestViolation;
                $warningMessage = '';
                if ($hasOvertimeViolation && $hasRestViolation) {
                    $warningMessage = 'Quá 6 ca/tuần & Thiếu nghỉ 11h';
                } elseif ($hasOvertimeViolation) {
                    $warningMessage = 'Quá 6 ca/tuần';
                } elseif ($hasRestViolation) {
                    $warningMessage = 'Thiếu nghỉ 11h';
                }

                $suggestions[] = [
                    'id'                     => $cand->id,
                    'full_name'              => $cand->full_name,
                    'employee_code'          => $cand->employee_code,
                    'registered_available'   => $isRegistered,
                    'weekly_shifts'          => $weeklyShiftsCount,
                    'has_overtime_violation' => $hasOvertimeViolation,
                    'has_rest_violation'     => $hasRestViolation,
                    'has_warning'            => $hasWarning,
                    'warning_message'        => $warningMessage,
                ];
            }

            // Sắp xếp: Không bị cảnh báo lên đầu -> Có đăng ký rảnh lên đầu -> Số ca làm việc ít nhất lên đầu
            usort($suggestions, function ($a, $b) {
                if ($a['has_warning'] !== $b['has_warning']) {
                    return $a['has_warning'] ? 1 : -1;
                }
                if ($a['registered_available'] !== $b['registered_available']) {
                    return $b['registered_available'] ? 1 : -1;
                }
                return $a['weekly_shifts'] <=> $b['weekly_shifts'];
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

        // Self-Approval Prevention Check
        abort_if(
            $leave->requested_by === $user->id || 
            ($leave->employee && $leave->employee->user_id === $user->id), 
            403, 
            'Bạn không thể tự phê duyệt đơn xin nghỉ của chính mình.'
        );

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($leave, $user, $request) {
                // Khóa bi quan bản ghi phép nghỉ
                $lockedLeave = LeaveRequest::where('id', $leave->id)->lockForUpdate()->firstOrFail();
                if ($lockedLeave->status !== 'pending') {
                    throw new \Exception('Đơn xin nghỉ này đã được xử lý trước đó.');
                }

                $lockedLeave->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                ]);

                $employee = $lockedLeave->employee;

                if ($employee) {
                    if ($lockedLeave->leave_type === 'resignation') {
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
                        $replacementEmpIds = array_filter(array_values($replacements));
                        $replacementEmployees = Employee::where('restaurant_id', $user->restaurant_id)
                            ->whereIn('id', $replacementEmpIds)
                            ->where('status', 'active')
                            ->get()
                            ->keyBy('id');

                        // Khóa các assignments của nhân viên để tránh trùng lặp ca thay thế
                        $assignments = ScheduleAssignment::where('employee_id', $employee->id)
                            ->lockForUpdate()
                            ->get();

                        foreach ($assignments as $assignment) {
                            $dateStr = $assignment->scheduled_date instanceof \Carbon\Carbon
                                ? $assignment->scheduled_date->toDateString()
                                : \Carbon\Carbon::parse($assignment->scheduled_date)->toDateString();

                            if ($dateStr >= $lockedLeave->start_date->toDateString() && $dateStr <= $lockedLeave->end_date->toDateString()) {
                                // Cập nhật trạng thái lịch làm sang leave_approved
                                $assignment->update(['status' => 'leave_approved']);

                                // Tạo lịch trực mới cho nhân viên thay thế (nếu có)
                                if (!empty($replacements[$assignment->id])) {
                                    $replacementEmpId = $replacements[$assignment->id];
                                    $replacementEmp = $replacementEmployees->get($replacementEmpId);

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
            });
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $employee = $leave->employee;
        $employeeUser = $employee?->user;

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

        // Fetch all approved leaves for the current week to avoid N+1 queries in the loop
        $currentWeekLeaves = LeaveRequest::where('restaurant_id', $restaurantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endOfCurrentWeek->toDateString())
            ->whereDate('end_date', '>=', $startOfCurrentWeek->toDateString())
            ->get();

        foreach ($lastWeekAssignments as $assignment) {
            // Calculate corresponding day in current week
            $lastWeekDate = Carbon::parse($assignment->scheduled_date);
            $dayOffset = $lastWeekDate->diffInDays($startOfLastWeek); // days since Monday of last week
            $currentWeekDateStr = $startOfCurrentWeek->copy()->addDays($dayOffset)->toDateString();

            // Check if employee is on approved leave on that day
            $isOnLeave = $currentWeekLeaves->contains(function ($leave) use ($assignment, $currentWeekDateStr) {
                $start = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : Carbon::parse($leave->start_date)->toDateString();
                $end = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : Carbon::parse($leave->end_date)->toDateString();
                return $leave->employee_id == $assignment->employee_id
                    && $start <= $currentWeekDateStr
                    && $end >= $currentWeekDateStr;
            });

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

        // Self-Approval Prevention Check
        $userEmployeeId = $user->employee?->id;
        $requesterEmployeeId = $swap->requesterAssignment?->employee_id;
        $receiverEmployeeId = $swap->receiverAssignment?->employee_id;

        if ($userEmployeeId && ($userEmployeeId === $requesterEmployeeId || $userEmployeeId === $receiverEmployeeId)) {
            abort(403, 'Bạn không thể phê duyệt yêu cầu đổi ca liên quan đến chính mình.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($swap, $user, $request) {
            // Khóa dòng ShiftSwap để tránh phê duyệt đúp
            $lockedSwap = ShiftSwap::where('id', $swap->id)->lockForUpdate()->firstOrFail();
            if ($lockedSwap->status !== 'accepted') {
                throw new \Exception('Yêu cầu đổi ca này đã được xử lý trước đó.');
            }

            $reqAssignment = $lockedSwap->requesterAssignment ? ScheduleAssignment::where('id', $lockedSwap->requesterAssignment->id)->lockForUpdate()->first() : null;
            $recAssignment = $lockedSwap->receiverAssignment ? ScheduleAssignment::where('id', $lockedSwap->receiverAssignment->id)->lockForUpdate()->first() : null;

            if ($reqAssignment && $recAssignment) {
                // Swap employee_ids
                $tempEmpId = $reqAssignment->employee_id;
                $reqAssignment->update(['employee_id' => $recAssignment->employee_id]);
                $recAssignment->update(['employee_id' => $tempEmpId]);
            }

            $lockedSwap->update([
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
