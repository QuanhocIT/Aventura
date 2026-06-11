<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Hiển thị bảng chấm công và lịch xếp ca.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        // 1. Nếu là Chủ hoặc Quản lý: Xem toàn cục
        if ($user->hasAnyRole(['owner', 'manager'])) {
            $selectedDate = $request->input('date', today()->toDateString());

            // Lấy danh sách lịch xếp ca trong ngày được chọn
            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereDate('scheduled_date', $selectedDate)
                ->with(['employee:id,full_name,employee_code,job_title', 'shift'])
                ->get()
                ->map(function ($a) {
                    $duration = null;
                    if ($a->check_in_at) {
                        $end = $a->check_out_at ? Carbon::parse($a->check_out_at) : now();
                        $diff = Carbon::parse($a->check_in_at)->diff($end);
                        $duration = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);
                    }

                    return [
                        'id' => $a->id,
                        'employee_id' => $a->employee_id,
                        'employee_name' => $a->employee?->full_name ?? 'Không rõ',
                        'employee_code' => $a->employee?->employee_code ?? '—',
                        'job_title' => $a->employee?->job_title ?? '—',
                        'shift_id' => $a->shift_id,
                        'shift_name' => $a->shift?->name ?? '—',
                        'shift_time' => $a->shift ? substr($a->shift->start_time, 0, 5).' - '.substr($a->shift->end_time, 0, 5) : '—',
                        'scheduled_date' => $a->scheduled_date instanceof Carbon ? $a->scheduled_date->toDateString() : Carbon::parse($a->scheduled_date)->toDateString(),
                        'check_in_at' => $a->check_in_at ? Carbon::parse($a->check_in_at)->format('H:i:s d/m/Y') : null,
                        'check_out_at' => $a->check_out_at ? Carbon::parse($a->check_out_at)->format('H:i:s d/m/Y') : null,
                        'status' => $a->status,
                        'duration' => $duration,
                        'notes' => $a->notes,
                    ];
                });

            // Tổng hợp thống kê chấm công hôm nay
            $stats = [
                'scheduled' => $assignments->where('status', 'scheduled')->count(),
                'working' => $assignments->where('status', 'checked_in')->count(),
                'completed' => $assignments->where('status', 'completed')->count(),
                'absent' => $assignments->where('status', 'absent')->count(),
                'leave' => $assignments->where('status', 'leave_approved')->count(),
                'total' => $assignments->count(),
            ];

            // Roster tuần này của toàn hệ thống
            $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
            $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
            $weeklyAssignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
                ->with(['employee:id,full_name', 'shift:id,name'])
                ->get()
                ->map(fn ($wa) => [
                    'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                    'employee_name' => $wa->employee?->full_name ?? 'Không rõ',
                    'shift_name' => $wa->shift?->name ? explode(' (', $wa->shift->name)[0] : 'Ca Mới',
                ]);

            $shifts = WorkShift::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'start' => substr($s->start_time, 0, 5),
                    'end' => substr($s->end_time, 0, 5),
                ]);

            $employees = Employee::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->get(['id', 'full_name', 'job_title', 'employee_code']);

            // ── AI Staffing Suggestions dựa trên peak hours ──────────────────
            $peakHours = DB::table('orders')
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->subDays(30))
                ->selectRaw('HOUR(completed_at) as hour, COUNT(*) as order_count, SUM(total_amount) as revenue')
                ->groupBy(DB::raw('HOUR(completed_at)'))
                ->orderByDesc('revenue')
                ->get();

            $totalRevenuePeak = $peakHours->sum('revenue');
            $staffingTips = [];

            if ($peakHours->count() && $totalRevenuePeak > 0) {
                foreach ($shifts as $shift) {
                    // Xác định giờ trong ca
                    $startH = (int) substr($shift['start'], 0, 2);
                    $endH = (int) substr($shift['end'], 0, 2);
                    if ($endH <= $startH) {
                        $endH += 24;
                    } // overnight

                    $shiftRevenue = $peakHours
                        ->filter(fn ($r) => $r->hour >= $startH && $r->hour < $endH)
                        ->sum('revenue');
                    $pct = round($shiftRevenue / $totalRevenuePeak * 100, 1);

                    $currentStaff = $assignments
                        ->where('shift_name', $shift['name'])
                        ->whereIn('status', ['scheduled', 'checked_in'])
                        ->count();

                    if ($pct >= 35 && $currentStaff < 3) {
                        $staffingTips[] = [
                            'shift' => $shift['name'],
                            'pct' => $pct,
                            'message' => "Ca <strong>{$shift['name']}</strong> chiếm {$pct}% doanh thu — hiện chỉ có {$currentStaff} nhân viên. Nên bố trí ít nhất 3 người.",
                            'level' => 'warning',
                        ];
                    } elseif ($pct < 10 && $currentStaff > 2) {
                        $staffingTips[] = [
                            'shift' => $shift['name'],
                            'pct' => $pct,
                            'message' => "Ca <strong>{$shift['name']}</strong> chỉ chiếm {$pct}% doanh thu — {$currentStaff} nhân viên có thể hơi nhiều. Cân nhắc tối ưu chi phí lương.",
                            'level' => 'info',
                        ];
                    }
                }
            }

            return Inertia::render('schedules/Index', [
                'isAdmin' => true,
                'selectedDate' => $selectedDate,
                'assignments' => $assignments,
                'stats' => $stats,
                'weeklyAssignments' => $weeklyAssignments,
                'shifts' => $shifts,
                'employees' => $employees,
                'staffingTips' => $staffingTips,
            ]);
        }

        // 2. Nếu là Nhân viên thường: Bảng chấm công cá nhân
        $employee = $user->employee;
        if (! $employee) {
            abort(403, 'Bạn chưa được liên kết với hồ sơ nhân sự nào trên hệ thống.');
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        // Lấy lịch xếp ca tuần này của cá nhân
        $myWeeklySchedules = ScheduleAssignment::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with('shift')
            ->orderBy('scheduled_date')
            ->get()
            ->map(function ($wa) {
                return [
                    'id' => $wa->id,
                    'date' => Carbon::parse($wa->scheduled_date)->format('d/m/Y'),
                    'day' => Carbon::parse($wa->scheduled_date)->format('l'),
                    'day_vn' => $this->getDayVn(Carbon::parse($wa->scheduled_date)->format('l')),
                    'shift_name' => $wa->shift?->name ?? '—',
                    'shift_time' => $wa->shift ? substr($wa->shift->start_time, 0, 5).' - '.substr($wa->shift->end_time, 0, 5) : '—',
                    'check_in_at' => $wa->check_in_at ? Carbon::parse($wa->check_in_at)->format('H:i:s') : null,
                    'check_out_at' => $wa->check_out_at ? Carbon::parse($wa->check_out_at)->format('H:i:s') : null,
                    'status' => $wa->status,
                ];
            });

        // Tìm ca trực hiện tại khả dụng để check-in hoặc check-out ngày hôm nay
        $todayActiveAssignment = null;
        $now = now();

        // 1. Kiểm tra xem có ca đang checked_in hay không
        $checkedInAssignment = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->with('shift')
            ->first();

        if ($checkedInAssignment) {
            $diff = Carbon::parse($checkedInAssignment->check_in_at)->diff($now);
            $durationStr = sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);

            $todayActiveAssignment = [
                'id' => $checkedInAssignment->id,
                'shift_name' => $checkedInAssignment->shift?->name ?? '—',
                'shift_time' => $checkedInAssignment->shift ? substr($checkedInAssignment->shift->start_time, 0, 5).' - '.substr($checkedInAssignment->shift->end_time, 0, 5) : '—',
                'check_in_at' => Carbon::parse($checkedInAssignment->check_in_at)->format('H:i:s d/m/Y'),
                'status' => $checkedInAssignment->status,
                'duration' => $durationStr,
                'can_check_in' => false,
                'can_check_out' => true,
            ];
        } else {
            // 2. Tìm ca scheduled hôm nay trong khung giờ cho phép check-in
            $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
                ->where('status', 'scheduled')
                ->with('shift')
                ->get();

            foreach ($scheduledAssignments as $sa) {
                $shift = $sa->shift;
                if (! $shift || $shift->status !== 'active') {
                    continue;
                }

                $dateStr = $sa->scheduled_date instanceof Carbon ? $sa->scheduled_date->toDateString() : Carbon::parse($sa->scheduled_date)->toDateString();
                $start = Carbon::parse($dateStr.' '.$shift->start_time);

                if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time)->addDay();
                } else {
                    $end = Carbon::parse($dateStr.' '.$shift->end_time);
                }

                // Khung check-in: Từ 30 phút trước ca trực cho đến khi hết ca trực
                $allowedStart = $start->copy()->subMinutes(30);
                $allowedEnd = $end;

                if ($now->between($allowedStart, $allowedEnd)) {
                    $todayActiveAssignment = [
                        'id' => $sa->id,
                        'shift_name' => $shift->name,
                        'shift_time' => substr($shift->start_time, 0, 5).' - '.substr($shift->end_time, 0, 5),
                        'check_in_at' => null,
                        'status' => $sa->status,
                        'duration' => null,
                        'can_check_in' => true,
                        'can_check_out' => false,
                    ];
                    break;
                }
            }
        }

        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'start' => substr($s->start_time, 0, 5),
                'end' => substr($s->end_time, 0, 5),
            ]);

        $employees = Employee::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->where('id', '!=', $employee->id)
            ->get(['id', 'full_name', 'job_title', 'employee_code']);

        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->latest()
            ->get()
            ->map(fn ($lr) => [
                'id' => $lr->id,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date instanceof Carbon ? $lr->start_date->toDateString() : Carbon::parse($lr->start_date)->toDateString(),
                'end_date' => $lr->end_date instanceof Carbon ? $lr->end_date->toDateString() : Carbon::parse($lr->end_date)->toDateString(),
                'reason' => $lr->reason,
                'status' => $lr->status,
                'created_at' => $lr->created_at->format('H:i d/m/Y'),
            ]);

        return Inertia::render('schedules/Index', [
            'isAdmin' => false,
            'myWeeklySchedules' => $myWeeklySchedules,
            'todayActiveAssignment' => $todayActiveAssignment,
            'shifts' => $shifts,
            'employees' => $employees,
            'leaveRequests' => $leaveRequests,
            'myEmployeeId' => $employee->id,
        ]);
    }

    /**
     * Nhân viên tự Check-in vào ca.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        // Tìm ca được xếp có hiệu lực hiện tại
        $sa = null;
        $now = now();

        $scheduledAssignments = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'scheduled')
            ->with('shift')
            ->get();

        foreach ($scheduledAssignments as $saCandidate) {
            $shift = $saCandidate->shift;
            if (! $shift || $shift->status !== 'active') {
                continue;
            }

            $dateStr = $saCandidate->scheduled_date instanceof Carbon ? $saCandidate->scheduled_date->toDateString() : Carbon::parse($saCandidate->scheduled_date)->toDateString();
            $start = Carbon::parse($dateStr.' '.$shift->start_time);

            if ($shift->is_overnight || $shift->end_time < $shift->start_time) {
                $end = Carbon::parse($dateStr.' '.$shift->end_time)->addDay();
            } else {
                $end = Carbon::parse($dateStr.' '.$shift->end_time);
            }

            $allowedStart = $start->copy()->subMinutes(30);
            $allowedEnd = $end;

            if ($now->between($allowedStart, $allowedEnd)) {
                $sa = $saCandidate;
                break;
            }
        }

        if (! $sa) {
            return back()->withErrors(['email' => 'Hiện tại bạn không có ca trực nào được xếp hoặc chưa đến giờ check-in cho phép.']);
        }

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
        ]);

        return back()->with('success', 'Bạn đã CHECK-IN thành công ca trực "'.$sa->shift->name.'". Chúc bạn một ca làm việc vui vẻ!');
    }

    /**
     * Nhân viên tự Check-out khỏi ca.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $sa = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('status', 'checked_in')
            ->first();

        if (! $sa) {
            return back()->withErrors(['email' => 'Không tìm thấy ca trực nào đang hoạt động để check-out.']);
        }

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        return back()->with('success', 'Bạn đã CHECK-OUT thành công. Cảm ơn bạn vì sự đóng góp tuyệt vời ngày hôm nay!');
    }

    /**
     * Quản lý/Owner Check-in hộ nhân viên.
     */
    public function checkInEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'check_in_at' => now(),
            'status' => 'checked_in',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-in hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        return back()->with('success', 'Đã ghi nhận Check-in hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Check-out hộ nhân viên.
     */
    public function checkOutEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'check_out_at' => now(),
            'status' => 'completed',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Check-out hộ bởi Quản lý/Chủ nhà hàng',
        ]);

        return back()->with('success', 'Đã ghi nhận Check-out hộ thành công cho nhân viên.');
    }

    /**
     * Quản lý/Owner Báo vắng (Absent) nhân viên.
     */
    public function markAbsentEmployee(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'notes' => ['nullable', 'string', 'max:250'],
        ]);

        $sa = ScheduleAssignment::findOrFail($data['assignment_id']);

        $sa->update([
            'status' => 'absent',
            'approved_by' => $request->user()->id,
            'notes' => $data['notes'] ?? 'Vắng mặt không lý do',
        ]);

        return back()->with('success', 'Đã ghi nhận báo vắng thành công cho nhân viên.');
    }

    /**
     * Nhân viên tự đăng ký ca làm việc.
     */
    public function register(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return back()->withErrors(['email' => 'Bạn không phải là nhân viên hợp lệ trên hệ thống.']);
        }

        $data = $request->validate([
            'shift_id' => ['required', 'exists:work_shifts,id'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        // Kiểm tra xem đã có lịch làm việc cho ca này ngày này chưa để tránh trùng lặp
        $exists = ScheduleAssignment::where('employee_id', $employee->id)
            ->where('shift_id', $data['shift_id'])
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['scheduled_date' => 'Bạn đã đăng ký hoặc được xếp ca làm việc này vào ngày đã chọn.']);
        }

        ScheduleAssignment::create([
            'restaurant_id' => $restaurantId,
            'employee_id' => $employee->id,
            'shift_id' => $data['shift_id'],
            'scheduled_date' => $data['scheduled_date'],
            'status' => 'scheduled',
            'notes' => 'Tự đăng ký ca làm việc',
        ]);

        return back()->with('success', 'Đăng ký ca làm việc thành công.');
    }

    /**
     * Trả về tên tiếng Việt của thứ trong tuần.
     */
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
