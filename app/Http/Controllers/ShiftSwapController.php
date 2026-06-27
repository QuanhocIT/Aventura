<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\ShiftSwap;
use App\Models\WorkShift;
use App\Models\ScheduleRegistration;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    /**
     * Gửi yêu cầu đổi ca cho đồng nghiệp.
     */
    public function requestSwap(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ để thực hiện chức năng này.']);
        }

        $data = $request->validate([
            'requester_assignment_id' => ['required', 'exists:schedule_assignments,id'],
            'receiver_assignment_id'  => ['required', 'exists:schedule_assignments,id'],
            'notes'                   => ['nullable', 'string', 'max:250'],
        ]);

        $reqAssignment = ScheduleAssignment::with('shift')->findOrFail($data['requester_assignment_id']);
        $recAssignment = ScheduleAssignment::with('shift')->findOrFail($data['receiver_assignment_id']);

        // Check ownership of requester assignment
        if ($reqAssignment->employee_id !== $employee->id) {
            return back()->withErrors(['error' => 'Bạn chỉ được gửi yêu cầu đổi ca cho ca trực của chính bạn.']);
        }

        // Check if assignments are in the same restaurant
        if ($reqAssignment->restaurant_id !== $employee->restaurant_id || $recAssignment->restaurant_id !== $employee->restaurant_id) {
            return back()->withErrors(['error' => 'Ca trực không hợp lệ.']);
        }

        // Check duplicate swap request
        $exists = ShiftSwap::where('restaurant_id', $employee->restaurant_id)
            ->where('requester_assignment_id', $data['requester_assignment_id'])
            ->where('receiver_assignment_id', $data['receiver_assignment_id'])
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Yêu cầu đổi ca này đang được xử lý, không thể tạo trùng lặp.']);
        }

        $violatesRequester = $reqAssignment->shift && $recAssignment->shift
            ? $this->hasRestViolation($employee->id, $recAssignment->scheduled_date, $recAssignment->shift, $reqAssignment->id)
            : false;

        $violatesReceiver = $reqAssignment->shift && $recAssignment->shift
            ? $this->hasRestViolation($recAssignment->employee_id, $reqAssignment->scheduled_date, $reqAssignment->shift, $recAssignment->id)
            : false;

        $notes = $data['notes'] ?? 'Đề xuất đổi ca làm việc';
        if ($violatesRequester || $violatesReceiver) {
            $notes = "[⚠️ Vi phạm nghỉ 11h] " . $notes;
        }

        $swap = ShiftSwap::create([
            'restaurant_id'           => $employee->restaurant_id,
            'requester_assignment_id' => $data['requester_assignment_id'],
            'receiver_assignment_id'  => $data['receiver_assignment_id'],
            'status'                  => 'pending',
            'notes'                   => $notes,
        ]);

        $receiverUser = $recAssignment->employee?->user;
        if ($receiverUser) {
            $receiverUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'requested',
                "Đồng nghiệp {$employee->full_name} đề xuất đổi ca trực tuần này với bạn."
            ));
        }

        return back()->with('success', 'Đã gửi yêu cầu đổi ca trực thành công đến đồng nghiệp.');
    }

    /**
     * Chấp nhận đổi ca từ đồng nghiệp.
     */
    public function acceptSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ.']);
        }

        abort_if($swap->restaurant_id !== $employee->restaurant_id, 403);
        abort_unless($swap->status === 'pending', 422);

        // Verify that the current employee is the receiver of the swap
        $recAssignment = $swap->receiverAssignment;
        if (!$recAssignment || $recAssignment->employee_id !== $employee->id) {
            return back()->withErrors(['error' => 'Bạn không phải là người nhận của yêu cầu đổi ca này.']);
        }

        $swap->update([
            'status' => 'accepted',
            'notes'  => $swap->notes . "\n[Chấp nhận bởi " . $employee->full_name . "]",
        ]);

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        if ($requesterUser) {
            $requesterUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'accepted',
                "Đồng nghiệp {$employee->full_name} đã đồng ý yêu cầu đổi ca của bạn. Đang chờ Quản lý duyệt."
            ));
        }

        $managers = \App\Models\User::where('restaurant_id', $swap->restaurant_id)
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['owner', 'manager']);
            })
            ->get();
        foreach ($managers as $manager) {
            $manager->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'accepted',
                "Yêu cầu đổi ca giữa {$swap->requesterAssignment->employee->full_name} và {$swap->receiverAssignment->employee->full_name} đang chờ bạn phê duyệt."
            ));
        }

        return back()->with('success', 'Bạn đã đồng ý đổi ca. Yêu cầu đã được chuyển đến Quản lý để phê duyệt cuối cùng.');
    }

    /**
     * Hủy yêu cầu đổi ca.
     */
    public function cancelSwap(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return back()->withErrors(['error' => 'Bạn không phải là nhân viên hợp lệ.']);
        }

        abort_if($swap->restaurant_id !== $employee->restaurant_id, 403);
        
        $reqAssignment = $swap->requesterAssignment;
        $recAssignment = $swap->receiverAssignment;

        // Requester or receiver can cancel/reject
        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $isReceiver = $recAssignment && $recAssignment->employee_id === $employee->id;

        if (!$isRequester && !$isReceiver) {
            return back()->withErrors(['error' => 'Bạn không có quyền thực hiện thao tác này.']);
        }

        $swap->update([
            'status' => 'cancelled',
            'notes'  => $swap->notes . "\n[Bị hủy bởi " . $employee->full_name . "]",
        ]);

        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $otherUser = $isRequester 
            ? ($recAssignment?->employee?->user) 
            : ($reqAssignment?->employee?->user);

        if ($otherUser) {
            $actionWord = $isRequester ? 'hủy' : 'từ chối';
            $otherUser->notify(new \App\Notifications\ShiftSwapNotification(
                $swap,
                'cancelled',
                "Đồng nghiệp {$employee->full_name} đã {$actionWord} yêu cầu đổi ca trực."
            ));
        }

        return back()->with('success', 'Đã hủy yêu cầu đổi ca.');
    }

    /**
     * Lấy gợi ý đổi ca trực thông minh bằng AI.
     */
    public function getSwapSuggestions(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['success' => false, 'error' => 'Nhân viên không hợp lệ.'], 403);
        }

        $assignmentId = $request->query('assignment_id');
        $myAssignment = ScheduleAssignment::with('shift')->where('employee_id', $employee->id)->findOrFail($assignmentId);
        $myShift = $myAssignment->shift;

        $startOfWeek = Carbon::parse($myAssignment->scheduled_date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::parse($myAssignment->scheduled_date)->endOfWeek(Carbon::SUNDAY)->toDateString();

        // 1. Lấy tất cả ca trực của đồng nghiệp trong cùng tuần
        $candidates = ScheduleAssignment::where('restaurant_id', $employee->restaurant_id)
            ->where('employee_id', '!=', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->with(['employee:id,full_name,role_id,job_title', 'shift'])
            ->get();

        // 2. Lấy danh sách đăng ký ca rảnh của nhân viên trong tuần này
        $myRegistrations = ScheduleRegistration::where('employee_id', $employee->id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get();

        // 3. Lấy đăng ký ca rảnh của đồng nghiệp trong tuần này
        $colleagueRegistrations = ScheduleRegistration::where('restaurant_id', $employee->restaurant_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get();

        // 4. Lấy tất cả phép nghỉ của đồng nghiệp và bản thân
        $leaves = \App\Models\LeaveRequest::where('restaurant_id', $employee->restaurant_id)
            ->where('status', 'approved')
            ->where(function ($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('start_date', [$startOfWeek, $endOfWeek])
                  ->orWhereBetween('end_date', [$startOfWeek, $endOfWeek]);
            })
            ->get();

        $suggestions = [];

        foreach ($candidates as $cand) {
            $colleague = $cand->employee;
            $candShift = $cand->shift;
            if (!$colleague || !$candShift) continue;

            $score = 0;
            $reasons = [];

            // A. Trùng vai trò công việc: +50 điểm
            if ($colleague->role_id === $employee->role_id || $colleague->job_title === $employee->job_title) {
                $score += 50;
                $reasons[] = 'Cùng vai trò công việc';
            }

            // B. Đồng nghiệp rảnh vào ngày của tôi: +30 điểm
            $colleagueFreeOnMyDay = $colleagueRegistrations->where('employee_id', $colleague->id)
                ->where('scheduled_date', $myAssignment->scheduled_date)
                ->where('shift_id', $myShift->id)
                ->isNotEmpty();
            if ($colleagueFreeOnMyDay) {
                $score += 30;
                $reasons[] = 'Đồng nghiệp đăng ký rảnh vào ngày của bạn';
            }

            // C. Tôi rảnh vào ngày của đồng nghiệp: +20 điểm
            $iAmFreeOnColleagueDay = $myRegistrations->where('scheduled_date', $cand->scheduled_date)
                ->where('shift_id', $candShift->id)
                ->isNotEmpty();
            if ($iAmFreeOnColleagueDay) {
                $score += 20;
                $reasons[] = 'Bạn đăng ký rảnh vào ngày của đồng nghiệp';
            }

            // D. Kiểm tra phép nghỉ (Tránh đổi vào ngày nghỉ): Nếu nghỉ thì bỏ qua luôn
            $colleagueOnLeaveOnMyDay = $leaves->where('employee_id', $colleague->id)
                ->filter(fn ($l) => $myAssignment->scheduled_date >= $l->start_date && $myAssignment->scheduled_date <= $l->end_date)
                ->isNotEmpty();
            if ($colleagueOnLeaveOnMyDay) continue;

            $iAmOnLeaveOnColleagueDay = $leaves->where('employee_id', $employee->id)
                ->filter(fn ($l) => $cand->scheduled_date >= $l->start_date && $cand->scheduled_date <= $l->end_date)
                ->isNotEmpty();
            if ($iAmOnLeaveOnColleagueDay) continue;

            if ($score === 0) {
                $reasons[] = 'Khác vị trí (Cần quản lý phê duyệt đặc biệt)';
            }

            $suggestions[] = [
                'id' => $cand->id,
                'employee_name' => $colleague->full_name,
                'shift_name' => $candShift->name,
                'shift_time' => substr($candShift->start_time, 0, 5) . ' - ' . substr($candShift->end_time, 0, 5),
                'day' => $this->getDayVn(Carbon::parse($cand->scheduled_date)->format('l')),
                'date' => Carbon::parse($cand->scheduled_date)->format('d/m/Y'),
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Lấy danh sách thông báo của nhân sự.
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        
        $notifications = $user->unreadNotifications()
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'general',
                    'action' => $n->data['action'] ?? 'info',
                    'message' => $n->data['message'] ?? '',
                    'created_at' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }

    /**
     * Đánh dấu thông báo đã đọc.
     */
    public function markNotificationAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true
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

    private function hasRestViolation($employeeId, $targetDate, $targetShift, $excludeAssignmentId): bool
    {
        $targetDateObj = Carbon::parse($targetDate);
        $startProposed = Carbon::parse($targetDateObj->toDateString() . ' ' . $targetShift->start_time);
        $endProposed = $targetShift->is_overnight 
            ? Carbon::parse($targetDateObj->toDateString() . ' ' . $targetShift->end_time)->addDay()
            : Carbon::parse($targetDateObj->toDateString() . ' ' . $targetShift->end_time);

        // Lấy tất cả ca làm việc khác của nhân viên này xung quanh ngày đó (-1 ngày, +0 ngày, +1 ngày)
        $adjacentAssignments = ScheduleAssignment::where('employee_id', $employeeId)
            ->where('id', '!=', $excludeAssignmentId)
            ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
            ->whereBetween('scheduled_date', [
                $targetDateObj->copy()->subDays(1)->toDateString(),
                $targetDateObj->copy()->addDays(1)->toDateString()
            ])
            ->with('shift')
            ->get();

        foreach ($adjacentAssignments as $aa) {
            $shift = $aa->shift;
            if (!$shift) continue;

            $dateStr = $aa->scheduled_date instanceof Carbon ? $aa->scheduled_date->toDateString() : Carbon::parse($aa->scheduled_date)->toDateString();
            $startExist = Carbon::parse($dateStr . ' ' . $shift->start_time);
            $endExist = $shift->is_overnight
                ? Carbon::parse($dateStr . ' ' . $shift->end_time)->addDay()
                : Carbon::parse($dateStr . ' ' . $shift->end_time);

            // 1. Kiểm tra overlap (trùng ca)
            if ($startProposed->lt($endExist) && $endProposed->gt($startExist)) {
                return true;
            }

            // 2. Tính khoảng nghỉ
            if ($endProposed->lte($startExist)) {
                $restHours = $endProposed->diffInSeconds($startExist) / 3600.0;
                if ($restHours < 11.0) {
                    return true;
                }
            } elseif ($endExist->lte($startProposed)) {
                $restHours = $endExist->diffInSeconds($startProposed) / 3600.0;
                if ($restHours < 11.0) {
                    return true;
                }
            }
        }

        return false;
    }

}
