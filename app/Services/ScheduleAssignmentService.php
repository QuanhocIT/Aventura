<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\EmployeeTrustScore;
use App\Models\LeaveRequest;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\ScheduleUpdatedNotification;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;

/**
 * Nghiệp vụ xếp ca trực: bật/tắt xếp lịch tự động bằng AI (chấm điểm ứng viên
 * theo đăng ký rảnh + rating + KPI/trust score + cân bằng khối lượng ca),
 * xếp/hủy ca thủ công (kiểm tra trùng thời gian), sao
 * chép lịch tuần trước — tách khỏi LeaveScheduleController theo đúng khuôn
 * "chia để trị" đã áp dụng cho các controller lớn khác.
 *
 * Các hàm ghi trả về ['success' => bool, 'message' => string, 'field' =>
 * ?string] để controller tự dịch sang back()->with()/withErrors(), giữ
 * service không phụ thuộc tầng HTTP.
 */
class ScheduleAssignmentService
{
    private const DAY_OFFSETS = [
        'Monday' => 0, 'Tuesday' => 1, 'Wednesday' => 2,
        'Thursday' => 3, 'Friday' => 4, 'Saturday' => 5, 'Sunday' => 6,
    ];

    /**
     * Bật/tắt chế độ xếp lịch tự động.
     *
     * AI chỉ được phép thay đổi các assignment còn ở trạng thái scheduled và
     * chưa thuộc kỳ lương đã khóa. Các ca đã qua ngày hiện tại hoặc đã bắt
     * đầu/kết thúc phải được giữ nguyên để bảo toàn lịch sử chấm công.
     */
    public function toggleAutoSchedule(Restaurant $restaurant, User $actingUser, bool $enabled): string
    {
        $restaurant->update(['auto_schedule' => $enabled]);

        if (! $enabled) {
            return 'Chế độ xếp lịch tự động đã TẮT. Bây giờ bạn có thể tự xếp lịch thủ công.';
        }

        // Auto generate schedules for the current calendar week
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // 1. Chỉ xóa các ca còn có thể điều chỉnh. Không dùng bulk delete cho
        // toàn bộ tuần vì bulk delete bỏ qua model events và có thể làm mất
        // các ca completed hoặc ca đã bị khóa theo bảng lương.
        $currentWeekAssignments = ScheduleAssignment::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get();

        $today = Carbon::today();
        $immutableAssignmentIds = $currentWeekAssignments
            ->filter(fn (ScheduleAssignment $assignment) => $this->isImmutableAssignment($assignment, $today))
            ->pluck('id');

        $mutableAssignments = $currentWeekAssignments
            ->reject(fn (ScheduleAssignment $assignment) => $immutableAssignmentIds->contains($assignment->id))
            ->values();

        foreach ($mutableAssignments as $assignment) {
            $assignment->delete();
        }

        // 2. Lấy danh sách nhân viên đang hoạt động
        $activeEmployees = Employee::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('status', 'active')
            ->get();

        // 3. Lấy danh sách các ca làm việc đang hoạt động
        $activeShifts = WorkShift::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where(function ($q) {
                $q->whereNull('branch_id')->orWhere('branch_id', app(TenantContext::class)->activeBranchId());
            }))
            ->where('status', 'active')
            ->get();

        // Các assignment được giữ lại phải được đưa vào bộ nhớ của AI để AI
        // không xếp trùng nhân viên trong cùng ngày và vẫn tính đúng tải tuần.
        $immutableAssignments = $currentWeekAssignments
            ->whereIn('id', $immutableAssignmentIds)
            ->values();

        if ($activeEmployees->isEmpty() || $activeShifts->isEmpty()) {
            return 'Chế độ xếp lịch tự động đã được KÍCH HOẠT. AI đã tự động phân bổ ca trực tối ưu cho tất cả nhân sự.';
        }

        // Track shift count per employee to limit workload (max 6 shifts/week)
        $employeeShiftCounts = [];
        foreach ($activeEmployees as $emp) {
            $employeeShiftCounts[$emp->id] = 0;
        }

        foreach ($immutableAssignments as $assignment) {
            if (array_key_exists($assignment->employee_id, $employeeShiftCounts)) {
                $employeeShiftCounts[$assignment->employee_id]++;
            }
        }

        // Eager load approved leaves and registrations for the entire week outside the day loop
        $approvedLeavesThisWeek = LeaveRequest::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfWeek->toDateString())
            ->where('end_date', '>=', $startOfWeek->toDateString())
            ->get();

        $registrationsThisWeek = ScheduleRegistration::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get()
            ->groupBy(function ($r) {
                return $r->scheduled_date instanceof Carbon ? $r->scheduled_date->toDateString() : Carbon::parse($r->scheduled_date)->toDateString();
            });

        $empIds = $activeEmployees->pluck('id')->toArray();

        $trustScoresMap = EmployeeTrustScore::withoutGlobalScopes()
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');

        $currentPeriod = Carbon::now()->format('Y-m');
        $kpiScoresMap = EmployeeKpi::withoutGlobalScopes()
            ->whereIn('employee_id', $empIds)
            ->where('period', $currentPeriod)
            ->get()
            ->keyBy('employee_id');

        // Lặp qua 7 ngày trong tuần
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startOfWeek->copy()->addDays($i);
            $dateStr = $currentDate->toDateString();

            // Track employee IDs assigned today to prevent double booking in-memory
            $assignedTodayEmployeeIds = $immutableAssignments
                ->filter(function (ScheduleAssignment $assignment) use ($dateStr) {
                    return Carbon::parse($assignment->scheduled_date)->toDateString() === $dateStr;
                })
                ->pluck('employee_id')
                ->all();

            // Xác định xem nhân viên nào đang nghỉ phép (đã được duyệt) vào ngày này
            $onLeaveEmployeeIds = $approvedLeavesThisWeek->filter(function ($leave) use ($dateStr) {
                $start = $leave->start_date instanceof Carbon ? $leave->start_date->toDateString() : Carbon::parse($leave->start_date)->toDateString();
                $end = $leave->end_date instanceof Carbon ? $leave->end_date->toDateString() : Carbon::parse($leave->end_date)->toDateString();

                return $start <= $dateStr && $end >= $dateStr;
            })->pluck('employee_id')->toArray();

            $availableEmployees = $activeEmployees->reject(fn ($e) => in_array($e->id, $onLeaveEmployeeIds))->values();

            if ($availableEmployees->isEmpty()) {
                continue;
            }

            // Get availability registrations for today
            $registrationsToday = isset($registrationsThisWeek[$dateStr])
                ? $registrationsThisWeek[$dateStr]->groupBy('shift_id')
                : collect();

            foreach ($activeShifts as $shift) {
                // Phân phối tối đa 2 nhân viên cho mỗi ca trực (nếu có đủ nhân sự)
                $empPerShift = $availableEmployees->count() >= 3 ? 2 : 1;
                $assignedForThisShift = [];

                for ($j = 0; $j < $empPerShift; $j++) {
                    $candidates = $availableEmployees->reject(function ($e) use ($assignedForThisShift, $employeeShiftCounts, $assignedTodayEmployeeIds) {
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

                    // Sort candidates by multi-tiered AI priority:
                    // 1. Priority 1: Registered available for this shift (registered_available = true)
                    // 2. Priority 2: Customer Rating Stars (rating_star 1.00 - 5.00)
                    // 3. Priority 3: Evaluation & trust score (KPI + Trust score 0-100)
                    // 4. Workload balancing: Lowest shift count so far in the week.
                    // 5. Role balance: Prefer adding a different role to this shift if one is already assigned
                    $candidates = $candidates->sortBy(function ($cand) use ($registrationsToday, $shift, $assignedForThisShift, $employeeShiftCounts, $availableEmployees, $trustScoresMap, $kpiScoresMap) {
                        $hasRegistered = isset($registrationsToday[$shift->id]) && $registrationsToday[$shift->id]->contains('employee_id', $cand->id);
                        $registrationScore = $hasRegistered ? 0 : 1;

                        $ratingCount = (int) ($cand->rating_count ?? 0);
                        $ratingStar = (float) ($cand->rating_star ?? 0);
                        $ratingRank = $ratingCount > 0
                            ? max(0, 500 - (int) round($ratingStar * 100))
                            : 501;

                        $trustScore = (float) ($trustScoresMap->get($cand->id)?->score ?? 80.0);
                        $kpiObj = $kpiScoresMap->get($cand->id);
                        $kpiScore = (float) ($kpiObj?->overall_score ?? $kpiObj?->kpi_score ?? 80.0);
                        $evalScore = ($trustScore + $kpiScore) / 2.0;
                        $evalRank = max(0, 100 - (int) round($evalScore));

                        $shiftCount = $employeeShiftCounts[$cand->id] ?? 0;

                        $roleScore = 0;
                        if (! empty($assignedForThisShift)) {
                            $assignedRoles = $availableEmployees->whereIn('id', $assignedForThisShift)->pluck('role_id')->toArray();
                            if (in_array($cand->role_id, $assignedRoles)) {
                                $roleScore = 1;
                            }
                        }

                        return sprintf('%d-%03d-%02d-%02d-%d', $registrationScore, $ratingRank, $evalRank, $shiftCount, $roleScore);
                    });

                    $bestCandidate = $candidates->first();
                    if ($bestCandidate) {
                        ScheduleAssignment::create([
                            'restaurant_id' => $restaurant->id,
                            'branch_id' => $bestCandidate->branch_id ?? $actingUser->branch_id,
                            'employee_id' => $bestCandidate->id,
                            'shift_id' => $shift->id,
                            'scheduled_date' => $dateStr,
                            'status' => 'scheduled',
                        ]);

                        $assignedForThisShift[] = $bestCandidate->id;
                        $assignedTodayEmployeeIds[] = $bestCandidate->id;
                        $employeeShiftCounts[$bestCandidate->id] = ($employeeShiftCounts[$bestCandidate->id] ?? 0) + 1;
                    }
                }
            }
        }

        return 'Chế độ xếp lịch tự động đã được KÍCH HOẠT. AI đã tự động phân bổ ca trực tối ưu cho tất cả nhân sự.';
    }

    /**
     * Xếp ca thủ công cho nhân sự — chỉ chặn các ca bị trùng thời gian.
     *
     * @return array{success: bool, field?: string, message: string}
     */
    public function storeAssignment(User $actingUser, array $data): array
    {
        $employee = Employee::where('restaurant_id', $actingUser->restaurant_id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('full_name', $data['employee_name'])
            ->first();

        if (! $employee) {
            return ['success' => false, 'field' => 'employee_name', 'message' => 'Nhân viên không tồn tại.'];
        }

        $assignmentBranchId = (int) $employee->branch_id;
        $activeBranchId = app(TenantContext::class)->activeBranchId();
        if (! $assignmentBranchId || ($activeBranchId !== null && $assignmentBranchId !== $activeBranchId)) {
            return ['success' => false, 'field' => 'employee_name', 'message' => 'NhÃ¢n viÃªn khÃ´ng thuá»™c chi nhÃ¡nh hiá»‡n táº¡i.'];
        }

        $shift = null;
        if (! empty($data['shift_id'])) {
            $shift = WorkShift::where('restaurant_id', $actingUser->restaurant_id)
                ->where(function ($q) {
                    $branchId = app(TenantContext::class)->activeBranchId();
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->find($data['shift_id']);
        }

        if (! $shift && ! empty($data['shift_name'])) {
            $shiftName = $data['shift_name'];
            $shift = WorkShift::where('restaurant_id', $actingUser->restaurant_id)
                ->where(function ($q) {
                    $branchId = app(TenantContext::class)->activeBranchId();
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->where(function ($q) use ($shiftName) {
                    $q->where('name', $shiftName)
                      ->orWhere('name', 'like', $shiftName.'%');
                })
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$shiftName])
                ->orderBy('id', 'desc')
                ->first();
        }

        if (! $shift) {
            return ['success' => false, 'field' => 'shift_name', 'message' => 'Ca làm việc không tồn tại.'];
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $offset = self::DAY_OFFSETS[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        $startProposed = Carbon::parse($scheduledDate.' '.$shift->start_time);
        $endProposed = $shift->is_overnight
            ? Carbon::parse($scheduledDate.' '.$shift->end_time)->addDay()
            : Carbon::parse($scheduledDate.' '.$shift->end_time);

        $adjacentAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('scheduled_date', '!=', $scheduledDate) // exclude the slot we are trying to set/update
            ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
            ->whereBetween('scheduled_date', [
                Carbon::parse($scheduledDate)->subDays(1)->toDateString(),
                Carbon::parse($scheduledDate)->addDays(1)->toDateString(),
            ])
            ->with('shift')
            ->get();

        foreach ($adjacentAssignments as $aa) {
            $aaShift = $aa->shift;
            if (! $aaShift) {
                continue;
            }

            $dateStr = $aa->scheduled_date instanceof Carbon ? $aa->scheduled_date->toDateString() : Carbon::parse($aa->scheduled_date)->toDateString();
            $startExist = Carbon::parse($dateStr.' '.$aaShift->start_time);
            $endExist = $aaShift->is_overnight
                ? Carbon::parse($dateStr.' '.$aaShift->end_time)->addDay()
                : Carbon::parse($dateStr.' '.$aaShift->end_time);

            // 1. Overlap Check
            if ($startProposed->lt($endExist) && $endProposed->gt($startExist)) {
                return ['success' => false, 'field' => 'shift_name', 'message' => "Nhân viên {$employee->full_name} đã có ca làm việc trùng lặp trong thời gian này."];
            }

        }

        // Save schedule
        ScheduleAssignment::updateOrCreate([
            'restaurant_id' => $actingUser->restaurant_id,
            'branch_id' => $assignmentBranchId,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => $scheduledDate,
        ], [
            'status' => 'scheduled',
        ]);

        $employee->flushShiftAccessCache();

        $employeeUser = $employee->user;
        if ($employeeUser) {
            $dateFormatted = Carbon::parse($scheduledDate)->format('d/m/Y');
            $employeeUser->notify(new ScheduleUpdatedNotification(
                "Bạn đã được phân công ca trực mới vào ngày {$dateFormatted}.",
                $scheduledDate
            ));
        }

        return ['success' => true, 'message' => 'Xếp ca thành công.'];
    }

    /**
     * Hủy xếp ca thủ công — luôn "thành công" kể cả khi không tìm thấy bản ghi
     * (giữ đúng hành vi cũ: không lộ thông tin tồn tại/không tồn tại của ca).
     */
    public function destroyAssignment(User $actingUser, array $data): array
    {
        $employee = Employee::where('restaurant_id', $actingUser->restaurant_id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('full_name', $data['employee_name'])
            ->first();

        if (! $employee) {
            return ['success' => false, 'field' => 'employee_name', 'message' => 'Nhân viên không tồn tại.'];
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $offset = self::DAY_OFFSETS[$data['day']] ?? 0;
        $scheduledDate = $startOfWeek->copy()->addDays($offset)->toDateString();

        $query = ScheduleAssignment::where('restaurant_id', $actingUser->restaurant_id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('employee_id', $employee->id)
            ->where('scheduled_date', $scheduledDate);

        if (! empty($data['shift_id'])) {
            $query->where('shift_id', $data['shift_id']);
        } elseif (! empty($data['shift_name'])) {
            $shift = WorkShift::where('restaurant_id', $actingUser->restaurant_id)
                ->where(function ($q) {
                    $branchId = app(TenantContext::class)->activeBranchId();
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                })
                ->where(function ($q) use ($data) {
                    $q->where('name', $data['shift_name'])
                      ->orWhere('name', 'like', $data['shift_name'].'%');
                })
                ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$data['shift_name']])
                ->orderBy('id', 'desc')
                ->first();

            if ($shift) {
                $query->where('shift_id', $shift->id);
            }
        }

        $assignment = $query->with('shift')->first();
        if (! $assignment) {
            return ['success' => true, 'message' => 'Hủy xếp ca thành công.'];
        }

        $shift = $assignment->shift;
        if ($shift) {
            $dateStr = Carbon::parse($assignment->scheduled_date)->toDateString();
            $shiftEnd = ($shift->is_overnight || $shift->end_time < $shift->start_time)
                ? Carbon::parse($dateStr.' '.$shift->end_time)->addDay()
                : Carbon::parse($dateStr.' '.$shift->end_time);

            if (now()->greaterThan($shiftEnd)) {
                return ['success' => false, 'field' => 'shift_name', 'message' => 'Không thể xóa ca làm việc đã kết thúc.'];
            }
        }

        $assignment->delete();

        $employee->flushShiftAccessCache();

        return ['success' => true, 'message' => 'Hủy xếp ca thành công.'];
    }

    /**
     * Tự động xóa các ca làm việc quá giờ mà nhân sự không thực hiện check-in.
     */
    public function cleanupUncheckedInPastShifts(int $restaurantId, ?int $branchId = null): void
    {
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($b) => $b->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [$startOfWeek, $now->toDateString()])
            ->with('shift')
            ->get();

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;
            if (! $shift) {
                continue;
            }

            $dateStr = Carbon::parse($assignment->scheduled_date)->toDateString();
            $shiftEnd = ($shift->is_overnight || $shift->end_time < $shift->start_time)
                ? Carbon::parse($dateStr.' '.$shift->end_time)->addDay()
                : Carbon::parse($dateStr.' '.$shift->end_time);

            if ($now->greaterThan($shiftEnd)) {
                $assignment->delete();
            }
        }
    }

    /**
     * Sao chép lịch xếp ca từ tuần trước sang tuần hiện tại (bỏ qua nhân viên
     * đang nghỉ phép đã duyệt trong tuần hiện tại).
     *
     * @return array{success: bool, message: string}
     */
    public function copyLastWeekSchedules(int $restaurantId): array
    {
        // Current week boundaries
        $startOfCurrentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfCurrentWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        // Last week boundaries
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY);

        // Get all assignments from last week
        $lastWeekAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfLastWeek->toDateString(), $endOfLastWeek->toDateString()])
            ->get();

        if ($lastWeekAssignments->isEmpty()) {
            return ['success' => false, 'message' => 'Không tìm thấy lịch trực nào từ tuần trước để sao chép.'];
        }

        // Chỉ xóa các assignment còn có thể thay đổi. Lịch đã qua ngày,
        // đã chấm công hoặc đã khóa theo kỳ lương phải được bảo toàn.
        $currentWeekAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfCurrentWeek->toDateString(), $endOfCurrentWeek->toDateString()])
            ->get();

        $immutableAssignmentIds = $currentWeekAssignments
            ->filter(fn (ScheduleAssignment $assignment) => $this->isImmutableAssignment($assignment, Carbon::today()))
            ->pluck('id');

        $mutableAssignments = $currentWeekAssignments
            ->reject(fn (ScheduleAssignment $assignment) => $immutableAssignmentIds->contains($assignment->id))
            ->values();

        foreach ($mutableAssignments as $assignment) {
            $assignment->delete();
        }

        $immutableAssignmentKeys = $currentWeekAssignments
            ->whereIn('id', $immutableAssignmentIds)
            ->mapWithKeys(fn (ScheduleAssignment $assignment) => [
                $assignment->employee_id.'|'.$assignment->shift_id.'|'.Carbon::parse($assignment->scheduled_date)->toDateString() => true,
            ]);

        $copiedCount = 0;

        // Fetch all approved leaves for the current week to avoid N+1 queries in the loop
        $currentWeekLeaves = LeaveRequest::where('restaurant_id', $restaurantId)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endOfCurrentWeek->toDateString())
            ->whereDate('end_date', '>=', $startOfCurrentWeek->toDateString())
            ->get();

        foreach ($lastWeekAssignments as $assignment) {
            // Calculate corresponding day in current week
            $lastWeekDate = Carbon::parse($assignment->scheduled_date);
            $dayOffset = $lastWeekDate->diffInDays($startOfLastWeek); // days since Monday of last week
            $currentWeekDateStr = $startOfCurrentWeek->copy()->addDays($dayOffset)->toDateString();

            // Không tạo bản ghi trùng với ca hiện tại đã được bảo vệ.
            $assignmentKey = $assignment->employee_id.'|'.$assignment->shift_id.'|'.$currentWeekDateStr;
            if ($immutableAssignmentKeys->has($assignmentKey)) {
                continue;
            }

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
                'restaurant_id' => $restaurantId,
                'branch_id' => $assignment->branch_id,
                'employee_id' => $assignment->employee_id,
                'shift_id' => $assignment->shift_id,
                'scheduled_date' => $currentWeekDateStr,
                'status' => 'scheduled',
            ]);

            $copiedCount++;
        }

        return ['success' => true, 'message' => "Đã sao chép thành công {$copiedCount} phân công lịch trực từ tuần trước."];
    }

    /**
     * Một assignment không còn được phép bị AI hoặc thao tác thay thế lịch
     * xóa/cập nhật nếu nó đã qua ngày, đã rời trạng thái lập lịch ban đầu,
     * hoặc ngày đó nằm trong kỳ lương đã khóa.
     */
    private function isImmutableAssignment(ScheduleAssignment $assignment, Carbon $today): bool
    {
        $scheduledDate = Carbon::parse($assignment->scheduled_date)->startOfDay();

        if ($scheduledDate->lt($today->copy()->startOfDay())) {
            return true;
        }

        if ($assignment->status !== 'scheduled') {
            return true;
        }

        return Salary::isPeriodLocked(
            (int) $assignment->restaurant_id,
            (int) $assignment->employee_id,
            $scheduledDate->toDateString(),
        );
    }

    /**
     * Lấp các vị trí còn trống từ thời điểm bấm nút đến Chủ nhật.
     * Không xóa hoặc thay thế bất kỳ assignment nào đã tồn tại.
     *
     * @return array{success: bool, message: string}
     */
    public function quickAutoSchedule(Restaurant $restaurant, User $actingUser): array
    {
        $requestedAt = Carbon::now();
        $requestedStart = $requestedAt->copy()->startOfDay();
        $startOfWeek = $requestedAt->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $endOfWeek = $requestedAt->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        $activeEmployees = Employee::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('status', 'active')
            ->get();

        $activeShifts = WorkShift::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where(function ($q) {
                $q->whereNull('branch_id')->orWhere('branch_id', app(TenantContext::class)->activeBranchId());
            }))
            ->where('status', 'active')
            ->get();

        if ($activeEmployees->isEmpty() || $activeShifts->isEmpty()) {
            return ['success' => true, 'message' => 'Không có nhân sự hoặc ca làm việc đang hoạt động để xếp.'];
        }

        $weekAssignments = ScheduleAssignment::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->copy()->endOfDay()->toDateString()])
            ->get();

        $countedStatuses = ['scheduled', 'checked_in', 'completed'];
        $employeeShiftCounts = [];
        foreach ($activeEmployees as $employee) {
            $employeeShiftCounts[$employee->id] = 0;
        }

        foreach ($weekAssignments as $assignment) {
            if (in_array($assignment->status, $countedStatuses, true) && array_key_exists($assignment->employee_id, $employeeShiftCounts)) {
                $employeeShiftCounts[$assignment->employee_id]++;
            }
        }

        $approvedLeavesThisWeek = LeaveRequest::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfWeek->copy()->endOfDay()->toDateString())
            ->where('end_date', '>=', $startOfWeek->toDateString())
            ->get();

        $registrationsThisWeek = ScheduleRegistration::where('restaurant_id', $restaurant->id)
            ->when(app(TenantContext::class)->isBranchScoped(), fn ($q) => $q->where('branch_id', app(TenantContext::class)->activeBranchId()))
            ->whereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get()
            ->groupBy(fn ($registration) => Carbon::parse($registration->scheduled_date)->toDateString());

        $empIds = $activeEmployees->pluck('id')->all();
        $trustScoresMap = EmployeeTrustScore::withoutGlobalScopes()
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');
        $kpiScoresMap = EmployeeKpi::withoutGlobalScopes()
            ->whereIn('employee_id', $empIds)
            ->where('period', Carbon::now()->format('Y-m'))
            ->get()
            ->keyBy('employee_id');

        $createdCount = 0;
        for ($currentDate = $requestedStart->copy(); $currentDate->lte($endOfWeek); $currentDate->addDay()) {
            $dateStr = $currentDate->toDateString();
            $onLeaveEmployeeIds = $approvedLeavesThisWeek
                ->filter(function ($leave) use ($dateStr) {
                    $leaveStart = Carbon::parse($leave->start_date)->toDateString();
                    $leaveEnd = Carbon::parse($leave->end_date)->toDateString();

                    return $leaveStart <= $dateStr && $leaveEnd >= $dateStr;
                })
                ->pluck('employee_id')
                ->all();
            $availableEmployees = $activeEmployees
                ->reject(fn ($employee) => in_array($employee->id, $onLeaveEmployeeIds, true))
                ->values();

            if ($availableEmployees->isEmpty()) {
                continue;
            }

            $assignmentsOnDate = $weekAssignments->filter(
                fn (ScheduleAssignment $assignment) => Carbon::parse($assignment->scheduled_date)->toDateString() === $dateStr,
            );
            $assignedTodayEmployeeIds = $assignmentsOnDate
                ->filter(fn (ScheduleAssignment $assignment) => in_array($assignment->status, $countedStatuses, true))
                ->pluck('employee_id')
                ->all();
            $registrationsToday = $registrationsThisWeek->get($dateStr, collect())->groupBy('shift_id');

            foreach ($activeShifts as $shift) {
                // Trong ngày bấm nút, chỉ xếp các ca bắt đầu từ thời điểm
                // request trở đi. Các ngày sau hôm nay không bị giới hạn giờ.
                $shiftStartAt = Carbon::parse($dateStr.' '.$shift->start_time);
                if ($dateStr === $requestedStart->toDateString() && $shiftStartAt->lt($requestedAt)) {
                    continue;
                }

                $allAssignmentsForShift = $assignmentsOnDate->where('shift_id', $shift->id);
                $workingAssignmentsForShift = $allAssignmentsForShift->filter(
                    fn (ScheduleAssignment $assignment) => in_array($assignment->status, $countedStatuses, true),
                );
                $targetStaff = $availableEmployees->count() >= 3 ? 2 : 1;
                $slotsToFill = max(0, $targetStaff - $workingAssignmentsForShift->count());
                $assignedForThisShift = $allAssignmentsForShift->pluck('employee_id')->all();

                for ($slot = 0; $slot < $slotsToFill; $slot++) {
                    $candidates = $availableEmployees->reject(function ($candidate) use ($assignedForThisShift, $employeeShiftCounts, $assignedTodayEmployeeIds, $restaurant, $dateStr) {
                        if (in_array($candidate->id, $assignedForThisShift, true)) {
                            return true;
                        }
                        if (($employeeShiftCounts[$candidate->id] ?? 0) >= 6) {
                            return true;
                        }
                        if (in_array($candidate->id, $assignedTodayEmployeeIds, true)) {
                            return true;
                        }
                        if (Salary::isPeriodLocked((int) $restaurant->id, (int) $candidate->id, $dateStr)) {
                            return true;
                        }

                        return false;
                    });

                    if ($candidates->isEmpty()) {
                        break;
                    }

                    $candidates = $candidates->sortBy(function ($candidate) use ($registrationsToday, $shift, $assignedForThisShift, $employeeShiftCounts, $availableEmployees, $trustScoresMap, $kpiScoresMap) {
                        $hasRegistered = isset($registrationsToday[$shift->id])
                            && $registrationsToday[$shift->id]->contains('employee_id', $candidate->id);
                        $registrationRank = $hasRegistered ? 0 : 1;

                        $ratingCount = (int) ($candidate->rating_count ?? 0);
                        $ratingStar = (float) ($candidate->rating_star ?? 0);
                        $ratingRank = $ratingCount > 0
                            ? max(0, 500 - (int) round($ratingStar * 100))
                            : 501;
                        $trustScore = (float) ($trustScoresMap->get($candidate->id)?->score ?? 80.0);
                        $kpi = $kpiScoresMap->get($candidate->id);
                        $kpiScore = (float) ($kpi?->overall_score ?? $kpi?->kpi_score ?? 80.0);
                        $evaluationRank = max(0, 100 - (int) round(($trustScore + $kpiScore) / 2));
                        $shiftCount = $employeeShiftCounts[$candidate->id] ?? 0;
                        $roleRank = 0;

                        if (! empty($assignedForThisShift)) {
                            $assignedRoles = $availableEmployees
                                ->whereIn('id', $assignedForThisShift)
                                ->pluck('role_id')
                                ->all();
                            if (in_array($candidate->role_id, $assignedRoles, true)) {
                                $roleRank = 1;
                            }
                        }

                        return sprintf('%d-%03d-%02d-%02d-%d', $registrationRank, $ratingRank, $evaluationRank, $shiftCount, $roleRank);
                    });

                    $bestCandidate = $candidates->first();
                    if (! $bestCandidate) {
                        break;
                    }

                    $newAssignment = ScheduleAssignment::create([
                        'restaurant_id' => $restaurant->id,
                        'branch_id' => $bestCandidate->branch_id ?? $actingUser->branch_id,
                        'employee_id' => $bestCandidate->id,
                        'shift_id' => $shift->id,
                        'scheduled_date' => $dateStr,
                        'status' => 'scheduled',
                    ]);

                    $weekAssignments->push($newAssignment);
                    $assignedForThisShift[] = $bestCandidate->id;
                    $assignedTodayEmployeeIds[] = $bestCandidate->id;
                    $employeeShiftCounts[$bestCandidate->id]++;
                    $createdCount++;
                }
            }
        }

        return [
            'success' => true,
            'message' => $createdCount > 0
                ? "Đã xếp nhanh {$createdCount} ca trống từ {$requestedAt->format('d/m/Y H:i')} đến Chủ nhật."
                : 'Không có ca trống phù hợp để xếp trong khoảng thời gian đã chọn.',
        ];
    }
}
