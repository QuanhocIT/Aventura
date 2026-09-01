<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleRegistration;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapNotification;
use App\Support\Tenant\TenantContext;
use App\Support\VietnameseDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nghiệp vụ đổi ca trực giữa nhân viên (đề xuất/chấp nhận/hủy) và gợi ý đổi ca
 * thông minh dựa trên vai trò công việc + đăng ký rảnh + phép nghỉ — tách khỏi
 * ScheduleController theo đúng khuôn "chia để trị" đã áp dụng cho các
 * controller lớn khác.
 *
 * Các hàm ghi (request/accept/cancel) trả về ['success' => bool, 'message' =>
 * string] để controller tự dịch sang back()->with()/withErrors(), giữ service
 * không phụ thuộc tầng HTTP.
 */
class ShiftSwapService
{
    /**
     * Nhân viên gửi yêu cầu đổi ca trực cho đồng nghiệp.
     */
    public function requestSwap(Employee $employee, array $data): array
    {
        $reqAssignment = ScheduleAssignment::findOrFail($data['requester_assignment_id']);
        $recAssignment = ScheduleAssignment::findOrFail($data['receiver_assignment_id']);

        // Check ownership of requester assignment
        if ($reqAssignment->employee_id !== $employee->id) {
            return ['success' => false, 'message' => 'Bạn chỉ được gửi yêu cầu đổi ca cho ca trực của chính bạn.'];
        }

        // Check if assignments are in the same restaurant
        if ($reqAssignment->restaurant_id !== $employee->restaurant_id || $recAssignment->restaurant_id !== $employee->restaurant_id) {
            return ['success' => false, 'message' => 'Ca trực không hợp lệ.'];
        }
        if ((int) $reqAssignment->branch_id !== (int) $employee->branch_id || (int) $recAssignment->branch_id !== (int) $employee->branch_id) {
            return ['success' => false, 'message' => 'Không thể đổi ca giữa các chi nhánh khác nhau.'];
        }

        if ($reqAssignment->employee_id === $recAssignment->employee_id) {
            return ['success' => false, 'message' => 'Người nhận đổi ca phải là đồng nghiệp khác.'];
        }

        if ($reqAssignment->status !== 'scheduled' || $recAssignment->status !== 'scheduled') {
            return ['success' => false, 'message' => 'Chỉ được đổi các ca sắp diễn ra và chưa chấm công.'];
        }

        $currentWeekStart = Carbon::now()->startOfWeek()->toDateString();
        if (Carbon::parse($reqAssignment->scheduled_date)->lt($currentWeekStart) || Carbon::parse($recAssignment->scheduled_date)->lt($currentWeekStart)) {
            return ['success' => false, 'message' => 'Không thể đổi ca đã qua.'];
        }

        // Check duplicate swap request
        $exists = ShiftSwap::where('restaurant_id', $employee->restaurant_id)
            ->where('branch_id', $employee->branch_id)
            ->where('requester_assignment_id', $data['requester_assignment_id'])
            ->where('receiver_assignment_id', $data['receiver_assignment_id'])
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($exists) {
            return ['success' => false, 'message' => 'Yêu cầu đổi ca này đang được xử lý, không thể tạo trùng lặp.'];
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

        return ['success' => true, 'message' => 'Đã gửi yêu cầu đổi ca trực thành công đến đồng nghiệp.'];
    }

    /**
     * Chấp nhận đổi ca từ đồng nghiệp — chuyển sang chờ quản lý phê duyệt.
     */
    public function acceptSwap(Employee $employee, ShiftSwap $swap): array
    {
        if (
            (int) $swap->restaurant_id !== (int) $employee->restaurant_id
            || (int) $swap->branch_id !== (int) $employee->branch_id
        ) {
            return ['success' => false, 'message' => 'Yêu cầu đổi ca không thuộc chi nhánh của bạn.'];
        }

        // Verify that the current employee is the receiver of the swap
        $recAssignment = $swap->receiverAssignment;
        $reqAssignment = $swap->requesterAssignment;
        if (
            ! $recAssignment
            || ! $reqAssignment
            || $recAssignment->employee_id !== $employee->id
            || (int) $reqAssignment->branch_id !== (int) $employee->branch_id
            || (int) $recAssignment->branch_id !== (int) $employee->branch_id
        ) {
            return ['success' => false, 'message' => 'Bạn không phải là người nhận của yêu cầu đổi ca này.'];
        }

        $swap->update([
            'status' => 'accepted',
            'notes' => $swap->notes."\n[Chấp nhận bởi ".$employee->full_name.']',
        ]);

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        if ($requesterUser) {
            $requesterUser->notify(new ShiftSwapNotification(
                $swap,
                'accepted',
                "Đồng nghiệp {$employee->full_name} đã đồng ý yêu cầu đổi ca của bạn. Đang chờ Quản lý duyệt."
            ));
        }

        $managers = User::where('restaurant_id', $swap->restaurant_id)
            ->where(function ($q) use ($swap) {
                $q->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'owner'))
                    ->orWhere(function ($managerQuery) use ($swap) {
                        $managerQuery->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'manager'))
                            ->where(function ($branchQuery) use ($swap) {
                                $branchQuery->where('branch_id', $swap->branch_id)
                                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('branch_id', $swap->branch_id));
                            });
                    });
            })
            ->get();
        foreach ($managers as $manager) {
            $manager->notify(new ShiftSwapNotification(
                $swap,
                'accepted',
                "Yêu cầu đổi ca giữa {$swap->requesterAssignment->employee->full_name} và {$swap->receiverAssignment->employee->full_name} đang chờ bạn phê duyệt."
            ));
        }

        return ['success' => true, 'message' => 'Bạn đã đồng ý đổi ca. Yêu cầu đã được chuyển đến Quản lý để phê duyệt cuối cùng.'];
    }

    /**
     * Hủy/từ chối yêu cầu đổi ca (bên đề xuất hoặc bên nhận đều có thể thực hiện).
     */
    public function cancelSwap(Employee $employee, ShiftSwap $swap): array
    {
        if (
            (int) $swap->restaurant_id !== (int) $employee->restaurant_id
            || (int) $swap->branch_id !== (int) $employee->branch_id
        ) {
            return ['success' => false, 'message' => 'Yêu cầu đổi ca không thuộc chi nhánh của bạn.'];
        }

        $reqAssignment = $swap->requesterAssignment;
        $recAssignment = $swap->receiverAssignment;

        // Requester or receiver can cancel/reject
        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $isReceiver = $recAssignment && $recAssignment->employee_id === $employee->id;

        if (! $isRequester && ! $isReceiver) {
            return ['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'];
        }

        $swap->update([
            'status' => 'cancelled',
            'notes' => $swap->notes."\n[Bị hủy bởi ".$employee->full_name.']',
        ]);

        $isRequester = $reqAssignment && $reqAssignment->employee_id === $employee->id;
        $otherUser = $isRequester
            ? ($recAssignment?->employee?->user)
            : ($reqAssignment?->employee?->user);

        if ($otherUser) {
            $actionWord = $isRequester ? 'hủy' : 'từ chối';
            $otherUser->notify(new ShiftSwapNotification(
                $swap,
                'cancelled',
                "Đồng nghiệp {$employee->full_name} đã {$actionWord} yêu cầu đổi ca trực."
            ));
        }

        return ['success' => true, 'message' => 'Đã hủy yêu cầu đổi ca.'];
    }

    /**
     * Gợi ý đổi ca trực thông minh: chấm điểm đồng nghiệp theo vai trò công việc
     * trùng khớp + đăng ký rảnh 2 chiều, loại bỏ ứng viên đang nghỉ phép đúng ngày.
     */
    public function suggestSwaps(Employee $employee, int $assignmentId): array
    {
        $myAssignment = ScheduleAssignment::with('shift')
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->findOrFail($assignmentId);
        $myShift = $myAssignment->shift;

        $startOfWeek = Carbon::parse($myAssignment->scheduled_date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::parse($myAssignment->scheduled_date)->endOfWeek(Carbon::SUNDAY)->toDateString();

        // 1. Lấy tất cả ca trực của đồng nghiệp trong cùng tuần
        $candidates = ScheduleAssignment::where('restaurant_id', $employee->restaurant_id)
            ->where('branch_id', $employee->branch_id)
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
            ->where('branch_id', $employee->branch_id)
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->get();

        // 4. Lấy tất cả phép nghỉ của đồng nghiệp và bản thân
        $leaves = LeaveRequest::where('restaurant_id', $employee->restaurant_id)
            ->where('branch_id', $employee->branch_id)
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
            if (! $colleague || ! $candShift) {
                continue;
            }

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
            if ($colleagueOnLeaveOnMyDay) {
                continue;
            }

            $iAmOnLeaveOnColleagueDay = $leaves->where('employee_id', $employee->id)
                ->filter(fn ($l) => $cand->scheduled_date >= $l->start_date && $cand->scheduled_date <= $l->end_date)
                ->isNotEmpty();
            if ($iAmOnLeaveOnColleagueDay) {
                continue;
            }

            if ($score === 0) {
                $reasons[] = 'Khác vị trí (Cần quản lý phê duyệt đặc biệt)';
            }

            $suggestions[] = [
                'id' => $cand->id,
                'employee_name' => $colleague->full_name,
                'shift_name' => $candShift->name,
                'shift_time' => substr($candShift->start_time, 0, 5).' - '.substr($candShift->end_time, 0, 5),
                'day' => VietnameseDate::dayName(Carbon::parse($cand->scheduled_date)->format('l')),
                'date' => Carbon::parse($cand->scheduled_date)->format('d/m/Y'),
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    /**
     * Quản lý/Owner phê duyệt cuối cùng cho yêu cầu đổi ca đã được đồng nghiệp
     * chấp nhận — hoán đổi employee_id giữa 2 assignment trong 1 transaction có
     * khoá bi quan để tránh phê duyệt đúp.
     *
     * @return array{success: bool, message: string}
     */
    public function approveSwap(User $actingUser, ShiftSwap $swap, ?string $notes): array
    {
        try {
            DB::transaction(function () use ($swap, $actingUser, $notes) {
                // Khóa dòng ShiftSwap để tránh phê duyệt đúp
                $lockedSwap = ShiftSwap::where('id', $swap->id)->lockForUpdate()->firstOrFail();
                if ($lockedSwap->status !== 'accepted') {
                    throw new \Exception('Yêu cầu đổi ca này đã được xử lý trước đó.');
                }
                if (
                    (int) $lockedSwap->restaurant_id !== (int) $actingUser->restaurant_id
                    || ! $actingUser->canAccessBranch((int) $lockedSwap->branch_id)
                ) {
                    throw new \Exception('Bạn không có quyền duyệt yêu cầu đổi ca của chi nhánh này.');
                }

                if (app(TenantContext::class)->isBranchScoped()) {
                    if ((int) app(TenantContext::class)->activeBranchId() !== (int) $lockedSwap->branch_id) {
                        throw new \Exception('YÃªu cáº§u Ä‘á»•i ca khÃ´ng thuá»™c chi nhÃ¡nh Ä‘ang thao tÃ¡c.');
                    }
                }

                $reqAssignment = $lockedSwap->requesterAssignment ? ScheduleAssignment::where('id', $lockedSwap->requesterAssignment->id)->lockForUpdate()->first() : null;
                $recAssignment = $lockedSwap->receiverAssignment ? ScheduleAssignment::where('id', $lockedSwap->receiverAssignment->id)->lockForUpdate()->first() : null;

                if (
                    ! $reqAssignment
                    || ! $recAssignment
                    || (int) $reqAssignment->restaurant_id !== (int) $actingUser->restaurant_id
                    || (int) $recAssignment->restaurant_id !== (int) $actingUser->restaurant_id
                    || (int) $reqAssignment->branch_id !== (int) $lockedSwap->branch_id
                    || (int) $recAssignment->branch_id !== (int) $lockedSwap->branch_id
                ) {
                    throw new \Exception('Dữ liệu đổi ca không đồng nhất với chi nhánh.');
                }

                $employeeCount = Employee::where('restaurant_id', $actingUser->restaurant_id)
                    ->where('branch_id', $lockedSwap->branch_id)
                    ->whereIn('id', [$reqAssignment->employee_id, $recAssignment->employee_id])
                    ->count();
                if ($employeeCount !== 2 || $reqAssignment->employee_id === $recAssignment->employee_id) {
                    throw new \Exception('Nhân sự trong yêu cầu đổi ca không hợp lệ.');
                }

                if ($reqAssignment && $recAssignment) {
                    // Swap employee_ids
                    $tempEmpId = $reqAssignment->employee_id;
                    $reqAssignment->update(['employee_id' => $recAssignment->employee_id]);
                    $recAssignment->update(['employee_id' => $tempEmpId]);
                }

                if (
                    (int) $lockedSwap->restaurant_id !== (int) $actingUser->restaurant_id
                    || ! $actingUser->canAccessBranch((int) $lockedSwap->branch_id)
                    || (app(TenantContext::class)->isBranchScoped() && (int) app(TenantContext::class)->activeBranchId() !== (int) $lockedSwap->branch_id)
                ) {
                    throw new \Exception('Báº¡n khÃ´ng cÃ³ quyá»n xá»­ lÃ½ yÃªu cáº§u Ä‘á»•i ca cá»§a chi nhÃ¡nh nÃ y.');
                }

                $lockedSwap->update([
                    'status' => 'approved',
                    'approved_by' => $actingUser->id,
                    'notes' => $notes ?? 'Phê duyệt bởi Quản lý/Chủ nhà hàng',
                ]);
            });
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        $receiverUser = $swap->receiverAssignment?->employee?->user;

        if ($requesterUser) {
            $requesterUser->notify(new ShiftSwapNotification(
                $swap,
                'approved',
                'Yêu cầu đổi ca trực của bạn đã được Quản lý phê duyệt thành công.'
            ));
        }
        if ($receiverUser) {
            $receiverUser->notify(new ShiftSwapNotification(
                $swap,
                'approved',
                'Yêu cầu đổi ca trực của bạn đã được Quản lý phê duyệt thành công.'
            ));
        }

        return ['success' => true, 'message' => 'Đã phê duyệt yêu cầu đổi ca làm việc thành công.'];
    }

    /**
     * Quản lý/Owner từ chối yêu cầu đổi ca đã được đồng nghiệp chấp nhận.
     *
     * @return array{success: bool, message: string}
     */
    public function rejectSwap(User $actingUser, ShiftSwap $swap, ?string $notes): array
    {
        try {
            DB::transaction(function () use ($swap, $actingUser, $notes) {
                $lockedSwap = ShiftSwap::where('id', $swap->id)->lockForUpdate()->firstOrFail();

                if (
                    (int) $lockedSwap->restaurant_id !== (int) $actingUser->restaurant_id
                    || ! $actingUser->canAccessBranch((int) $lockedSwap->branch_id)
                    || (app(TenantContext::class)->isBranchScoped() && (int) app(TenantContext::class)->activeBranchId() !== (int) $lockedSwap->branch_id)
                ) {
                    throw new \Exception('Branch scope does not allow this shift swap.');
                }

                if ($lockedSwap->status !== 'accepted') {
                    throw new \Exception('Yêu cầu đổi ca này đã được xử lý trước đó.');
                }

                $lockedSwap->update([
                    'status' => 'rejected',
                    'approved_by' => $actingUser->id,
                    'notes' => $notes ?? 'Từ chối bởi Quản lý/Chủ nhà hàng',
                ]);
            });
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $requesterUser = $swap->requesterAssignment?->employee?->user;
        $receiverUser = $swap->receiverAssignment?->employee?->user;

        $reason = $notes ?? 'Từ chối bởi Quản lý/Chủ nhà hàng';
        if ($requesterUser) {
            $requesterUser->notify(new ShiftSwapNotification(
                $swap,
                'rejected',
                "Yêu cầu đổi ca trực của bạn bị Quản lý từ chối: {$reason}"
            ));
        }
        if ($receiverUser) {
            $receiverUser->notify(new ShiftSwapNotification(
                $swap,
                'rejected',
                "Yêu cầu đổi ca trực của bạn bị Quản lý từ chối: {$reason}"
            ));
        }

        return ['success' => true, 'message' => 'Đã từ chối yêu cầu đổi ca làm việc.'];
    }
}
