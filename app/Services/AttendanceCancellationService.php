<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\ScheduleAssignment;
use App\Notifications\AttendanceAutoCancelledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceCancellationService
{
    /**
     * Tự động kiểm tra và hủy các yêu cầu chấm công (shift_checkin / shift_checkout)
     * quá 24h mà chưa được Quản lý chi nhánh hoặc Chủ duyệt.
     *
     * @return int Số bản ghi đã tự động hủy
     */
    public function cancelExpiredAttendanceRequests(): int
    {
        $expiredThreshold = now()->subHours(24);

        $expiredRequests = ApprovalRequest::withoutGlobalScopes()
            ->whereIn('operation_type', ['shift_checkin', 'shift_checkout'])
            ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED])
            ->where('created_at', '<=', $expiredThreshold)
            ->get();

        $cancelledCount = 0;

        foreach ($expiredRequests as $request) {
            try {
                DB::transaction(function () use ($request, &$cancelledCount): void {
                    $lockedRequest = ApprovalRequest::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->find($request->id);

                    if (! $lockedRequest || ! in_array($lockedRequest->status, [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED], true)) {
                        return;
                    }

                    $opData = $lockedRequest->operation_data ?? [];
                    $assignmentId = $opData['assignment_id'] ?? null;

                    $assignment = null;
                    if ($assignmentId) {
                        $assignment = ScheduleAssignment::withoutGlobalScopes()->find($assignmentId);
                    }

                    // 1. Cập nhật trạng thái ApprovalRequest thành rejected
                    $lockedRequest->update([
                        'status' => ApprovalRequest::STATUS_REJECTED,
                        'rejection_reason' => 'Hệ thống tự động hủy chấm công do Quản lý chi nhánh / Chủ không duyệt trong 24 giờ.',
                    ]);

                    // 2. Hủy chấm công tạm thời trên ScheduleAssignment
                    if ($assignment) {
                        if ($lockedRequest->operation_type === 'shift_checkin') {
                            $assignment->update([
                                'check_in_at' => null,
                                'status' => 'absent',
                            ]);
                        } elseif ($lockedRequest->operation_type === 'shift_checkout') {
                            $assignment->update([
                                'check_out_at' => null,
                                'status' => 'checked_in',
                            ]);
                        }

                        $assignment->employee?->flushShiftAccessCache();
                    }

                    // 3. Ghi Audit Log
                    AuditLog::log(
                        'attendance_auto_cancelled_24h',
                        'updated',
                        $assignment ?? $lockedRequest,
                        ['status' => $lockedRequest->status],
                        [
                            'approval_request_id' => $lockedRequest->id,
                            'operation_type' => $lockedRequest->operation_type,
                            'reason' => '24h_unapproved_timeout',
                        ]
                    );

                    // 4. Gửi email thông báo tới nhân sự đó
                    $requester = $lockedRequest->requester ?? $assignment?->employee?->user;
                    if ($requester && $requester->email) {
                        try {
                            $requester->notify(new AttendanceAutoCancelledNotification($assignment, $lockedRequest));
                        } catch (\Throwable $e) {
                            Log::warning("Could not send AttendanceAutoCancelledNotification to {$requester->email}: ".$e->getMessage());
                        }
                    }

                    $cancelledCount++;
                });
            } catch (\Throwable $e) {
                Log::error("Failed to auto-cancel expired attendance request #{$request->id}: ".$e->getMessage());
            }
        }

        return $cancelledCount;
    }
}
