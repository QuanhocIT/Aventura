<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateEmployeeKpiJob;
use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\LeaveRequest;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Notifications\ShiftSwapNotification;
use App\Services\KpiService;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class EmployeePortalController extends Controller
{
    /**
     * Render the employee portal dashboard view.
     */
    public function index(Request $request): Response
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            abort(403, 'Bạn không phải là nhân viên hợp lệ.');
        }

        return Inertia::render('employee-portal/Dashboard', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'job_title' => $employee->job_title ?? 'Nhân viên',
                'employee_code' => $employee->employee_code,
            ],
        ]);
    }

    /**
     * Get dashboard summary data.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        $cacheKey = "employee_dashboard:{$employee->id}:".now()->format('Y-m');

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($employee, $request) {
            $period = now()->format('Y-m');
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            // 1. Calculate shifts & hours worked this month
            $completedAssignments = ScheduleAssignment::where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', 'completed')
                ->with('shift')
                ->get();

            $completedShiftsCount = $completedAssignments->count();
            $hoursWorked = 0.0;
            foreach ($completedAssignments as $a) {
                if ($a->check_in_at && $a->check_out_at) {
                    $in = Carbon::parse($a->check_in_at);
                    $out = Carbon::parse($a->check_out_at);
                    $hoursWorked += $in->diffInSeconds($out) / 3600.0;
                }
            }
            $hoursWorked = round($hoursWorked, 1);

            // 2. Load KPI metrics for the current month
            $kpiService = app(KpiService::class);
            $kpi = EmployeeKpi::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->where('period', $period)
                ->with('metrics')
                ->first();

            if (! $kpi) {
                CalculateEmployeeKpiJob::dispatch($employee, $period);
            }

            $kpiData = null;
            if ($kpi) {
                $kpiData = [
                    'total_score' => (float) $kpi->total_score,
                    'total_bonus' => (float) $kpi->total_bonus,
                    'total_commission' => (float) $kpi->total_commission,
                    'status' => $kpi->status,
                    'metrics' => $kpi->metrics->map(fn ($m) => [
                        'metric_name' => $m->metric_name,
                        'actual_value' => (float) $m->actual_value,
                        'target_value' => (float) $m->target_value,
                        'score' => (float) $m->score,
                        'is_achieved' => (bool) $m->is_achieved,
                        'bonus_earned' => (float) $m->bonus_earned,
                        'commission_earned' => (float) $m->commission_earned,
                    ]),
                ];
            }

            // 3. Estimate monthly earnings
            $baseSalary = $kpiService->getKpiRole($employee) !== null
                ? app(SalaryService::class)->calculateDynamicBaseSalary($employee, $startOfMonth->toDateString(), $endOfMonth->toDateString())
                : (float) ($employee->base_salary ?? 0);

            $kpiBonus = $kpi ? (float) $kpi->total_bonus : 0.0;
            $kpiCommission = $kpi ? (float) $kpi->total_commission : 0.0;
            $estimatedEarnings = $baseSalary + $kpiBonus + $kpiCommission;

            // 4. Retrieve schedules: last week, this week, and next week
            $startRange = now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
            $endRange = now()->addWeeks(2)->endOfWeek(Carbon::SUNDAY)->toDateString();

            $schedules = ScheduleAssignment::where('employee_id', $employee->id)
                ->whereBetween('scheduled_date', [$startRange, $endRange])
                ->with('shift')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'date' => $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString(),
                    'formatted_date' => Carbon::parse($a->scheduled_date)->format('d/m/Y'),
                    'day_name' => $this->getDayVn(Carbon::parse($a->scheduled_date)->format('l')),
                    'shift_name' => $a->shift?->name ?? 'Ca Trực',
                    'start_time' => $a->shift?->start_time ? substr($a->shift->start_time, 0, 5) : '',
                    'end_time' => $a->shift?->end_time ? substr($a->shift->end_time, 0, 5) : '',
                    'status' => $a->status,
                    'check_in_at' => $a->check_in_at ? Carbon::parse($a->check_in_at)->format('H:i') : null,
                    'check_out_at' => $a->check_out_at ? Carbon::parse($a->check_out_at)->format('H:i') : null,
                ]);

            // 5. Database Notifications
            $notifications = $request->user()->notifications()
                ->take(15)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'info',
                    'message' => $n->data['message'] ?? '',
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at->diffForHumans(),
                ]);

            return [
                'success' => true,
                'summary' => [
                    'shifts_completed' => $completedShiftsCount,
                    'hours_worked' => $hoursWorked,
                    'estimated_earnings' => $estimatedEarnings,
                    'base_salary' => $baseSalary,
                    'kpi_bonus' => $kpiBonus,
                    'kpi_commission' => $kpiCommission,
                ],
                'kpis' => $kpiData,
                'schedules' => $schedules,
                'notifications' => $notifications,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Get salary history and details.
     */
    public function getSalaries(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        $salaries = Salary::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->with('adjustments')
            ->orderByDesc('pay_period_start')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'pay_period' => Carbon::parse($s->pay_period_start)->format('m/Y'),
                'base_salary' => (float) $s->base_salary,
                'bonus_amount' => (float) $s->bonus_amount,
                'deduction_amount' => (float) $s->deduction_amount,
                'net_salary' => (float) $s->net_salary,
                'status' => $s->status,
                'paid_at' => $s->paid_at ? $s->paid_at->format('d/m/Y') : null,
                'adjustments' => $s->adjustments->map(fn ($a) => [
                    'type' => $a->type,
                    'amount' => (float) $a->amount,
                    'reason' => $a->reason,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'salaries' => $salaries,
        ]);
    }

    /**
     * Get leave requests history.
     */
    public function getLeaves(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        $leaves = LeaveRequest::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'leave_type' => $l->leave_type,
                'start_date' => $l->start_date->toDateString(),
                'end_date' => $l->end_date->toDateString(),
                'reason' => $l->reason,
                'status' => $l->status,
                'created_at' => $l->created_at->format('d/m/Y'),
            ]);

        return response()->json([
            'success' => true,
            'leaves' => $leaves,
        ]);
    }

    /**
     * Submit a leave request.
     */
    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        $data = $request->validate([
            'leave_type' => ['required', 'string', 'in:annual,sick,unpaid,emergency'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // 1. Overlapping Leave Check
        $overlapping = LeaveRequest::where('restaurant_id', $employee->restaurant_id)
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
            return response()->json(['success' => false, 'error' => 'Yêu cầu nghỉ phép trùng lặp với đơn nghỉ phép đã đăng ký trước đó.'], 422);
        }

        // 2. Department/Role Quota Check (Max 30% role limit)
        $totalRoleEmployees = Employee::where('restaurant_id', $employee->restaurant_id)
            ->where('role_id', $employee->role_id)
            ->where('status', 'active')
            ->count();

        if ($totalRoleEmployees > 0) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $activeLeaves = LeaveRequest::where('restaurant_id', $employee->restaurant_id)
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
                    return response()->json([
                        'success' => false,
                        'error' => "Vượt quá giới hạn nghỉ phép đồng thời của bộ phận vào ngày {$date->format('d/m/Y')} (Tối đa 30% nhân sự bộ phận được nghỉ).",
                    ], 422);
                }
            }
        }

        // 3. Schedule Conflict Warning Check
        $hasSchedules = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereDate('scheduled_date', '>=', $data['start_date'])
            ->whereDate('scheduled_date', '<=', $data['end_date'])
            ->exists();

        $leave = LeaveRequest::create([
            'restaurant_id' => $employee->restaurant_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'requested_by' => $user->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? '',
            'status' => 'pending',
        ]);

        $warning = $hasSchedules ? ' Lưu ý: Bạn đang có lịch làm việc được xếp trong khoảng thời gian này, vui lòng báo Quản lý hoặc đổi ca trực.' : '';

        // Notify managers/owners
        $managers = User::where('restaurant_id', $employee->restaurant_id)
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['owner', 'manager']);
            })
            ->get();
        foreach ($managers as $manager) {
            $manager->notify(new LeaveRequestNotification($leave, 'pending', "Nhân viên {$employee->full_name} đã nộp một đơn xin nghỉ phép mới."));
        }

        return response()->json([
            'success' => true,
            'message' => 'Nộp đơn xin nghỉ phép thành công.'.$warning,
            'leave' => $leave,
        ]);
    }

    /**
     * Get active shift swaps and AI suggestions.
     */
    public function getSwaps(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        // 1. Get shift swaps involving this employee
        $swaps = ShiftSwap::whereHas('requesterAssignment', fn ($q) => $q->where('employee_id', $employee->id))
            ->orWhereHas('receiverAssignment', fn ($q) => $q->where('employee_id', $employee->id))
            ->with([
                'requesterAssignment.employee:id,full_name',
                'requesterAssignment.shift',
                'receiverAssignment.employee:id,full_name',
                'receiverAssignment.shift',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'notes' => $s->notes,
                'is_requester' => $s->requesterAssignment->employee_id === $employee->id,
                'requester_name' => $s->requesterAssignment->employee?->full_name,
                'requester_date' => Carbon::parse($s->requesterAssignment->scheduled_date)->format('d/m/Y'),
                'requester_shift' => $s->requesterAssignment->shift?->name,
                'receiver_name' => $s->receiverAssignment->employee?->full_name,
                'receiver_date' => Carbon::parse($s->receiverAssignment->scheduled_date)->format('d/m/Y'),
                'receiver_shift' => $s->receiverAssignment->shift?->name,
            ]);

        // 2. Fetch my upcoming assignments this week
        $startOfWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $myAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->where('status', 'scheduled')
            ->with('shift')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'date_formatted' => Carbon::parse($a->scheduled_date)->format('d/m/Y'),
                'shift_name' => $a->shift?->name ?? 'Ca trực',
                'shift_time' => $a->shift?->start_time ? (substr($a->shift->start_time, 0, 5).' - '.substr($a->shift->end_time, 0, 5)) : '',
            ]);

        // 3. Smart AI Swap Suggestions: colleagues scheduled this week with matching role_id
        $colleagueAssignments = ScheduleAssignment::where('restaurant_id', $employee->restaurant_id)
            ->where('employee_id', '!=', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->where('status', 'scheduled')
            ->whereHas('employee', function ($q) use ($employee) {
                $q->where('role_id', $employee->role_id);
            })
            ->with(['employee:id,full_name,role_id,job_title', 'shift'])
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'employee_name' => $a->employee?->full_name,
                'role_id' => $a->employee?->role_id,
                'job_title' => $a->employee?->job_title,
                'shift_name' => $a->shift?->name,
                'shift_time' => $a->shift?->start_time ? (substr($a->shift->start_time, 0, 5).' - '.substr($a->shift->end_time, 0, 5)) : '',
                'date_formatted' => Carbon::parse($a->scheduled_date)->format('d/m/Y'),
                'day_name' => $this->getDayVn(Carbon::parse($a->scheduled_date)->format('l')),
            ]);

        return response()->json([
            'success' => true,
            'swaps' => $swaps,
            'my_assignments' => $myAssignments,
            'colleague_assignments' => $colleagueAssignments,
        ]);
    }

    /**
     * Submit a shift swap request.
     */
    public function requestSwap(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        $data = $request->validate([
            'requester_assignment_id' => ['required', \App\Support\TenantRule::exists('schedule_assignments')],
            'receiver_assignment_id' => ['required', \App\Support\TenantRule::exists('schedule_assignments')],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $reqAssignment = ScheduleAssignment::findOrFail($data['requester_assignment_id']);
        $recAssignment = ScheduleAssignment::findOrFail($data['receiver_assignment_id']);

        if ($reqAssignment->employee_id !== $employee->id) {
            return response()->json(['success' => false, 'error' => 'Bạn chỉ được gửi yêu cầu đổi ca cho ca trực của chính bạn.'], 422);
        }

        if ($reqAssignment->restaurant_id !== $employee->restaurant_id || $recAssignment->restaurant_id !== $employee->restaurant_id) {
            return response()->json(['success' => false, 'error' => 'Ca trực không hợp lệ.'], 422);
        }

        $exists = ShiftSwap::where('restaurant_id', $employee->restaurant_id)
            ->where('requester_assignment_id', $data['requester_assignment_id'])
            ->where('receiver_assignment_id', $data['receiver_assignment_id'])
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'error' => 'Yêu cầu đổi ca này đang được xử lý, không thể tạo trùng lặp.'], 422);
        }

        $swap = ShiftSwap::create([
            'restaurant_id' => $employee->restaurant_id,
            'branch_id' => $employee->branch_id,
            'requester_assignment_id' => $data['requester_assignment_id'],
            'receiver_assignment_id' => $data['receiver_assignment_id'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? 'Đề xuất đổi ca làm việc',
        ]);

        $receiverUser = $recAssignment->employee?->user;
        if ($receiverUser) {
            $receiverUser->notify(new ShiftSwapNotification(
                $swap,
                'requested',
                "Đồng nghiệp {$employee->full_name} đề xuất đổi ca trực tuần này với bạn."
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu đổi ca trực thành công đến đồng nghiệp.',
            'swap' => $swap,
        ]);
    }

    /**
     * Respond to a swap request (Accept / Cancel / Reject).
     */
    public function respondSwap(Request $request, ShiftSwap $swap): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['success' => false, 'error' => 'Bạn không phải là nhân viên hợp lệ.'], 403);
        }

        if ($swap->restaurant_id !== $employee->restaurant_id) {
            return response()->json(['success' => false, 'error' => 'Yêu cầu đổi ca không hợp lệ.'], 403);
        }

        $action = $request->input('action'); // 'accept' | 'cancel' | 'reject'
        if (! in_array($action, ['accept', 'cancel', 'reject'])) {
            return response()->json(['success' => false, 'error' => 'Hành động không hợp lệ.'], 422);
        }

        $reqAssignment = $swap->requesterAssignment;
        $recAssignment = $swap->receiverAssignment;

        if ($action === 'accept') {
            abort_unless($swap->status === 'pending', 422);
            if (! $recAssignment || $recAssignment->employee_id !== $employee->id) {
                return response()->json(['success' => false, 'error' => 'Bạn không phải là người nhận của yêu cầu đổi ca này.'], 403);
            }

            $swap->update([
                'status' => 'accepted',
                'notes' => $swap->notes."\n[Chấp nhận bởi ".$employee->full_name.']',
            ]);

            $requesterUser = $reqAssignment?->employee?->user;
            if ($requesterUser) {
                $requesterUser->notify(new ShiftSwapNotification(
                    $swap,
                    'accepted',
                    "Đồng nghiệp {$employee->full_name} đã đồng ý yêu cầu đổi ca của bạn. Đang chờ Quản lý duyệt."
                ));
            }

            // Notify owners/managers
            $managers = User::where('restaurant_id', $swap->restaurant_id)
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['owner', 'manager']);
                })
                ->get();
            foreach ($managers as $manager) {
                $manager->notify(new ShiftSwapNotification(
                    $swap,
                    'accepted',
                    "Yêu cầu đổi ca giữa {$swap->requesterAssignment->employee->full_name} và {$swap->receiverAssignment->employee->full_name} đang chờ bạn phê duyệt."
                ));
            }

            $message = 'Bạn đã đồng ý đổi ca. Yêu cầu đã được chuyển đến Quản lý phê duyệt.';
        } else {
            // Cancel or Reject
            $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
            $isReceiver = $recAssignment && $recAssignment->employee_id === $employee->id;

            if (! $isRequester && ! $isReceiver) {
                return response()->json(['success' => false, 'error' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
            }

            $swap->update([
                'status' => 'cancelled',
                'notes' => $swap->notes."\n[Bị hủy/từ chối bởi ".$employee->full_name.']',
            ]);

            $otherUser = $isRequester ? ($recAssignment?->employee?->user) : ($reqAssignment?->employee?->user);
            if ($otherUser) {
                $actionWord = $isRequester ? 'hủy' : 'từ chối';
                $otherUser->notify(new ShiftSwapNotification(
                    $swap,
                    'cancelled',
                    "Đồng nghiệp {$employee->full_name} đã {$actionWord} yêu cầu đổi ca trực."
                ));
            }

            $message = 'Yêu cầu đổi ca đã được hủy/từ chối thành công.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'swap' => $swap,
        ]);
    }

    /**
     * Mark all unread database notifications as read.
     */
    public function readAllNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu đọc tất cả thông báo.',
        ]);
    }

    private function getDayVn(string $day): string
    {
        $days = [
            'Monday' => 'Thứ Hai',
            'Tuesday' => 'Thứ Ba',
            'Wednesday' => 'Thứ Tư',
            'Thursday' => 'Thứ Năm',
            'Friday' => 'Thứ Sáu',
            'Saturday' => 'Thứ Bảy',
            'Sunday' => 'Chủ Nhật',
        ];

        return $days[$day] ?? $day;
    }
}
