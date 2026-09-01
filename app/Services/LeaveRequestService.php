<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ đơn xin nghỉ phép/nghỉ việc: nộp đơn (kiểm tra trùng lịch nghỉ +
 * hạn mức 30% nhân sự cùng vai trò nghỉ đồng thời, có bypass kiểm toán), gợi ý
 * nhân sự thế chỗ, phê duyệt (kèm gán người thay thế hoặc xử lý nghỉ việc),
 * từ chối — tách khỏi LeaveScheduleController theo đúng khuôn "chia để trị".
 *
 * Các hàm ghi trả về ['success' => bool, 'message' => string, 'field' =>
 * ?string] để controller tự dịch sang back()->with()/withErrors().
 */
class LeaveRequestService
{
    /**
     * Nộp đơn xin nghỉ phép/nghỉ việc.
     *
     * @return array{success: bool, field?: string, message: string}
     */
    public function storeLeaveRequest(User $actingUser, array $data, ?string $bypassCode, ?string $bypassReason, string $ip, string $userAgent): array
    {
        $employee = Employee::where('restaurant_id', $actingUser->restaurant_id)->findOrFail($data['employee_id']);
        abort_unless($actingUser->canAccessBranch((int) $employee->branch_id), 403);
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped()) {
            abort_unless((int) $tenantContext->activeBranchId() === (int) $employee->branch_id, 403);
        }

        // 1. Overlapping Leave Check
        $overlapping = LeaveRequest::where('restaurant_id', $actingUser->restaurant_id)
            ->where('branch_id', $employee->branch_id)
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
            return ['success' => false, 'field' => 'start_date', 'message' => 'Yêu cầu nghỉ phép trùng lặp với đơn nghỉ phép đã đăng ký trước đó.'];
        }

        // 2. Department/Role Quota Check (Max 30% role limit)
        $totalRoleEmployees = Employee::where('restaurant_id', $actingUser->restaurant_id)
            ->where('branch_id', $employee->branch_id)
            ->where('role_id', $employee->role_id)
            ->where('status', 'active')
            ->count();

        if ($totalRoleEmployees > 0) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $activeLeaves = LeaveRequest::where('restaurant_id', $actingUser->restaurant_id)
                ->where('branch_id', $employee->branch_id)
                ->whereIn('status', ['approved', 'pending'])
                ->whereDate('start_date', '<=', $endDate->toDateString())
                ->whereDate('end_date', '>=', $startDate->toDateString())
                ->whereHas('employee', function ($q) use ($employee) {
                    $q->where('role_id', $employee->role_id);
                })
                ->get();

            $hasQuotaViolation = false;
            $violationDate = '';
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateStr = $date->toDateString();

                $onLeaveCount = $activeLeaves->filter(function ($leave) use ($dateStr) {
                    $start = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : Carbon::parse($leave->start_date)->toDateString();
                    $end = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : Carbon::parse($leave->end_date)->toDateString();

                    return $start <= $dateStr && $end >= $dateStr;
                })->count();

                if (($onLeaveCount + 1) / $totalRoleEmployees > 0.30) {
                    $hasQuotaViolation = true;
                    $violationDate = $date->format('d/m/Y');
                    break;
                }
            }

            if ($hasQuotaViolation) {
                $hasBypass = false;

                if ($bypassCode) {
                    try {
                        $approvingUser = User::validateManagerBypass($bypassCode, $actingUser->restaurant_id);
                        if ($approvingUser && ! empty($bypassReason)) {
                            $hasBypass = true;

                            AuditLog::create([
                                'restaurant_id' => $actingUser->restaurant_id,
                                'user_id' => $actingUser->id,
                                'event' => 'created',
                                'action' => 'leave_quota_bypass',
                                'ip_address' => $ip,
                                'user_agent' => $userAgent,
                                'old_values' => [
                                    'employee_id' => $employee->id,
                                    'start_date' => $data['start_date'],
                                    'end_date' => $data['end_date'],
                                ],
                                'new_values' => [
                                    'bypass_code_used' => true,
                                    'bypass_approver_id' => $approvingUser->id,
                                    'bypass_reason' => $bypassReason,
                                ],
                            ]);
                        }
                    } catch (\Exception $e) {
                        return ['success' => false, 'field' => 'bypass_code', 'message' => $e->getMessage()];
                    }
                }

                if (! $hasBypass) {
                    return ['success' => false, 'field' => 'start_date', 'message' => "Vượt quá giới hạn nghỉ phép đồng thời của bộ phận vào ngày {$violationDate} (Tối đa 30% nhân sự bộ phận được nghỉ). Vui lòng nhập mã phê duyệt và lý do để ghi đè đặc cách."];
                }
            }
        }

        // 3. Schedule Conflict Warning Check
        $hasSchedules = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereDate('scheduled_date', '>=', $data['start_date'])
            ->whereDate('scheduled_date', '<=', $data['end_date'])
            ->exists();

        LeaveRequest::create([
            'restaurant_id' => $actingUser->restaurant_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $data['employee_id'],
            'requested_by' => $actingUser->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? '',
            'status' => 'pending',
        ]);

        $warning = $hasSchedules ? ' Lưu ý: Nhân viên đã có ca trực được xếp trong thời gian này, vui lòng điều chỉnh/đổi ca trực.' : '';

        return ['success' => true, 'message' => 'Nộp đơn xin nghỉ thành công.'.$warning];
    }

    /**
     * Gợi ý nhân sự thế chỗ cho từng ca trực bị ảnh hưởng bởi đơn nghỉ phép —
     * chấm điểm theo đăng ký rảnh, số ca/tuần (chống quá tải) và luật nghỉ 11h.
     */
    public function getReplacementSuggestions(int $restaurantId, LeaveRequest $leave): array
    {
        $employee = $leave->employee;
        if (! $employee) {
            return ['success' => false, 'message' => 'Nhân viên không tồn tại.'];
        }
        $tenantContext = app(TenantContext::class);
        if ($tenantContext->isBranchScoped()) {
            abort_unless((int) $tenantContext->activeBranchId() === (int) $leave->branch_id, 403);
        }

        $startDate = $leave->start_date->toDateString();
        $endDate = $leave->end_date->toDateString();

        $assignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $employee->branch_id)
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->whereBetween('scheduled_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->with(['shift'])
            ->get();

        // Lấy các ứng viên có cùng vai trò chuyên môn, đang hoạt động bên ngoài vòng lặp
        $candidates = Employee::where('restaurant_id', $restaurantId)
            ->where('branch_id', $employee->branch_id)
            ->where('role_id', $employee->role_id)
            ->where('id', '!=', $employee->id)
            ->where('status', 'active')
            ->get();

        // Pre-fetch all assignments and registrations in target date range to avoid in-loop queries
        $allAssignments = ScheduleAssignment::whereBetween('scheduled_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $employee->branch_id)
            ->whereIn('status', ['scheduled', 'checked_in'])
            ->get()
            ->groupBy(function ($a) {
                return $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString();
            });

        $allRegistrations = ScheduleRegistration::whereBetween('scheduled_date', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $employee->branch_id)
            ->get()
            ->groupBy(function ($r) {
                $dateStr = $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString();

                return $dateStr.'-'.$r->shift_id;
            });

        $data = [];
        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (! $shift) {
                continue;
            }

            $dateStr = $assignment->scheduled_date instanceof Carbon
                ? $assignment->scheduled_date->toDateString()
                : Carbon::parse($assignment->scheduled_date)->toDateString();

            $scheduledEmployeeIds = isset($allAssignments[$dateStr])
                ? $allAssignments[$dateStr]->pluck('employee_id')->toArray()
                : [];

            $regKey = $dateStr.'-'.$shift->id;
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
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $employee->branch_id)
                    ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                    ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                    ->count();

                $hasOvertimeViolation = ($weeklyShiftsCount >= 6);

                // Kiểm tra luật nghỉ 11 tiếng giữa các ca (11-hour rest rule check)
                $startProposed = Carbon::parse($dateStr.' '.$shift->start_time);
                $endProposed = $shift->is_overnight
                    ? Carbon::parse($dateStr.' '.$shift->end_time)->addDay()
                    : Carbon::parse($dateStr.' '.$shift->end_time);

                $hasRestViolation = false;
                $adjacentAssignments = ScheduleAssignment::where('employee_id', $cand->id)
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $employee->branch_id)
                    ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                    ->whereBetween('scheduled_date', [
                        Carbon::parse($dateStr)->subDays(1)->toDateString(),
                        Carbon::parse($dateStr)->addDays(1)->toDateString(),
                    ])
                    ->with('shift')
                    ->get();

                foreach ($adjacentAssignments as $aa) {
                    $aaShift = $aa->shift;
                    if (! $aaShift) {
                        continue;
                    }

                    $aaDateStr = $aa->scheduled_date instanceof Carbon ? $aa->scheduled_date->toDateString() : Carbon::parse($aa->scheduled_date)->toDateString();
                    $startExist = Carbon::parse($aaDateStr.' '.$aaShift->start_time);
                    $endExist = $aaShift->is_overnight
                        ? Carbon::parse($aaDateStr.' '.$aaShift->end_time)->addDay()
                        : Carbon::parse($aaDateStr.' '.$aaShift->end_time);

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
                    'id' => $cand->id,
                    'full_name' => $cand->full_name,
                    'employee_code' => $cand->employee_code,
                    'registered_available' => $isRegistered,
                    'weekly_shifts' => $weeklyShiftsCount,
                    'has_overtime_violation' => $hasOvertimeViolation,
                    'has_rest_violation' => $hasRestViolation,
                    'has_warning' => $hasWarning,
                    'warning_message' => $warningMessage,
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

            $formattedDate = $assignment->scheduled_date instanceof Carbon
                ? $assignment->scheduled_date->format('d/m/Y')
                : Carbon::parse($assignment->scheduled_date)->format('d/m/Y');

            $data[] = [
                'assignment_id' => $assignment->id,
                'date' => $dateStr,
                'formatted_date' => $formattedDate,
                'shift_id' => $shift->id,
                'shift_name' => $shift->name ? explode(' (', $shift->name)[0] : 'Ca Mới',
                'shift_time' => $shift->start_time.' - '.$shift->end_time,
                'suggestions' => $suggestions,
            ];
        }

        return [
            'success' => true,
            'leave_id' => $leave->id,
            'employee_name' => $employee->full_name,
            'leave_type' => $leave->leave_type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'assignments' => $data,
        ];
    }

    /**
     * Phê duyệt đơn xin nghỉ phép/nghỉ việc — với nghỉ việc: chuyển nhân sự
     * sang terminated + vô hiệu hoá tài khoản + xoá mềm; với nghỉ phép: đánh
     * dấu các ca bị ảnh hưởng là leave_approved và gán nhân sự thay thế (nếu có).
     *
     * @return array{success: bool, message: string}
     */
    public function approveLeaveRequest(User $actingUser, LeaveRequest $leave, array $replacements): array
    {
        try {
            DB::transaction(function () use ($leave, $actingUser, $replacements) {
                // Khóa bi quan bản ghi phép nghỉ
                $lockedLeave = LeaveRequest::where('id', $leave->id)->lockForUpdate()->firstOrFail();
                if ($lockedLeave->status !== 'pending') {
                    throw new \Exception('Đơn xin nghỉ này đã được xử lý trước đó.');
                }
                if (
                    (int) $lockedLeave->restaurant_id !== (int) $actingUser->restaurant_id
                    || ! $actingUser->canAccessBranch((int) $lockedLeave->branch_id)
                ) {
                    throw new \Exception('Bạn không có quyền xử lý đơn nghỉ của chi nhánh này.');
                }
                if (app(TenantContext::class)->isBranchScoped()) {
                    if ((int) app(TenantContext::class)->activeBranchId() !== (int) $lockedLeave->branch_id) {
                        throw new \Exception('Leave request is outside the active branch.');
                    }
                }

                $lockedLeave->update([
                    'status' => 'approved',
                    'approved_by' => $actingUser->id,
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
                        $replacementEmpIds = array_filter(array_values($replacements));
                        $replacementEmployees = Employee::where('restaurant_id', $actingUser->restaurant_id)
                            ->where('branch_id', $lockedLeave->branch_id)
                            ->whereIn('id', $replacementEmpIds)
                            ->where('status', 'active')
                            ->get()
                            ->keyBy('id');

                        // Khóa các assignments của nhân viên để tránh trùng lặp ca thay thế
                        $assignments = ScheduleAssignment::where('employee_id', $employee->id)
                            ->where('restaurant_id', $actingUser->restaurant_id)
                            ->where('branch_id', $lockedLeave->branch_id)
                            ->lockForUpdate()
                            ->get();

                        foreach ($assignments as $assignment) {
                            $dateStr = $assignment->scheduled_date instanceof Carbon
                                ? $assignment->scheduled_date->toDateString()
                                : Carbon::parse($assignment->scheduled_date)->toDateString();

                            if ($dateStr >= $lockedLeave->start_date->toDateString() && $dateStr <= $lockedLeave->end_date->toDateString()) {
                                // Cập nhật trạng thái lịch làm sang leave_approved
                                $assignment->update(['status' => 'leave_approved']);

                                // Tạo lịch trực mới cho nhân viên thay thế (nếu có)
                                if (! empty($replacements[$assignment->id])) {
                                    $replacementEmpId = $replacements[$assignment->id];
                                    $replacementEmp = $replacementEmployees->get($replacementEmpId);

                                    if ($replacementEmp) {
                                        ScheduleAssignment::create([
                                            'restaurant_id' => $actingUser->restaurant_id,
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
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $employee = $leave->employee;
        $employeeUser = $employee?->user;

        if ($employeeUser) {
            $employeeUser->notify(new LeaveRequestNotification(
                $leave,
                'approved',
                'Đơn xin nghỉ của bạn đã được Quản lý/Chủ nhà hàng phê duyệt.'
            ));
        }

        return ['success' => true, 'message' => 'Phê duyệt đơn xin nghỉ thành công.'];
    }

    /**
     * Từ chối đơn xin nghỉ phép/nghỉ việc.
     */
    public function rejectLeaveRequest(User $actingUser, LeaveRequest $leave, string $rejectionReason): void
    {
        $leave->update([
            'status' => 'rejected',
            'approved_by' => $actingUser->id,
            'reason' => $leave->reason."\n[Từ chối: ".$rejectionReason.']',
        ]);

        $employeeUser = $leave->employee?->user;
        if ($employeeUser) {
            $employeeUser->notify(new LeaveRequestNotification(
                $leave,
                'rejected',
                "Đơn xin nghỉ của bạn bị từ chối: {$rejectionReason}"
            ));
        }
    }
}
