<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateEmployeeKpiJob;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Notifications\ShiftSwapNotification;
use App\Support\MaterializedViews\MaterializedViewReader;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Render the private employee profile and customer feedback page.
     */
    public function profile(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee()->with(['branch', 'role'])->first();

        if (! $employee) {
            abort(403, 'Bạn không phải là nhân viên hợp lệ.');
        }

        $reviews = collect();
        $feedbacks = CustomerFeedback::query()
            ->where('restaurant_id', $employee->restaurant_id)
            ->whereNotNull('staff_rating')
            ->latest('id')
            ->take(200)
            ->get([
                'id',
                'staff_rating',
                'rating',
                'content',
                'submitted_by_name',
                'is_anonymous',
                'created_at',
            ]);

        foreach ($feedbacks as $feedback) {
            $staffRatings = is_array($feedback->staff_rating)
                ? $feedback->staff_rating
                : [];

            foreach ($staffRatings as $key => $staffRating) {
                $employeeId = is_array($staffRating)
                    ? ($staffRating['employee_id'] ?? null)
                    : $key;

                if ((string) $employeeId !== (string) $employee->id) {
                    continue;
                }

                $reviews->push([
                    'id' => $feedback->id.'-'.$key,
                    'rating' => (int) (is_array($staffRating)
                        ? ($staffRating['rating'] ?? $feedback->rating)
                        : $staffRating),
                    'comment' => is_array($staffRating)
                        ? ($staffRating['comment'] ?? $feedback->content)
                        : $feedback->content,
                    'overall_rating' => (int) $feedback->rating,
                    'reviewer_name' => $feedback->is_anonymous
                        ? 'Khách ẩn danh'
                        : ($feedback->submitted_by_name ?: 'Khách hàng'),
                    'created_at' => $feedback->created_at?->format('d/m/Y H:i'),
                ]);
            }

            if ($reviews->count() >= 20) {
                break;
            }
        }

        return Inertia::render('employee-portal/Profile', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'email' => $employee->email ?: $user->email,
                'phone' => $employee->phone,
                'date_of_birth' => $employee->date_of_birth?->format('d/m/Y'),
                'gender' => $employee->gender,
                'address' => $employee->address,
                'job_title' => $employee->job_title,
                'employment_type' => $employee->employment_type,
                'hire_date' => $employee->hire_date?->format('d/m/Y'),
                'status' => $employee->status,
                'branch_name' => $employee->branch?->name,
                'role_name' => $employee->role?->name,
                'rating_star' => (float) ($employee->rating_star ?? 0),
                'rating_count' => (int) ($employee->rating_count ?? 0),
            ],
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'email_verified' => $user->email_verified_at !== null,
                'last_login_at' => $user->last_login_at?->format('d/m/Y H:i'),
                'roles' => $user->getRoleNames()->values()->all(),
            ],
            'reviews' => $reviews->values(),
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

        $reader = app(MaterializedViewReader::class);
        $snapshot = $reader->read(
            'employee_portal',
            (int) $employee->restaurant_id,
            $employee->branch_id,
            today(),
            allowStale: true,
        );

        $dashboard = $snapshot['employees'][(string) $employee->id] ?? null;

        // Tình huống an toàn khi nhân viên vừa đổi chi nhánh hoặc snapshot chưa
        // được tạo: đọc payload cấp nhà hàng một lần, vẫn không lộ dữ liệu tenant khác.
        if (! $dashboard) {
            $restaurantSnapshot = $reader->read(
                'employee_portal',
                (int) $employee->restaurant_id,
                null,
                today(),
                allowStale: true,
            );
            $dashboard = $restaurantSnapshot['employees'][(string) $employee->id] ?? null;
        }

        if (! $dashboard) {
            return response()->json(['success' => false, 'error' => 'Chưa có dữ liệu nhân sự. Vui lòng thử lại sau ít phút.'], 503);
        }

        $period = now()->format('Y-m');
        if (! $dashboard['kpis']) {
            CalculateEmployeeKpiJob::dispatchAfterResponse($employee, $period);
        }

        // Notifications cần gần thời gian thực nên không lưu vào materialized view.
        $notifications = $request->user()->notifications()
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? 'info',
                'message' => $notification->data['message'] ?? '',
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->diffForHumans(),
            ])
            ->values();

        $payload = [
            'success' => true,
            'summary' => $dashboard['summary'],
            'kpis' => $dashboard['kpis'],
            'schedules' => $dashboard['schedules'],
            'notifications' => $notifications,
        ];

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
                'compensation_type' => $employee->compensation_type ?? 'fixed',
                'bonus_amount' => (float) $s->bonus_amount,
                'overtime_amount' => (float) ($s->overtime_amount ?? 0),
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
            'requester_assignment_id' => ['required', TenantRule::exists('schedule_assignments')],
            'receiver_assignment_id' => ['required', TenantRule::exists('schedule_assignments')],
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
