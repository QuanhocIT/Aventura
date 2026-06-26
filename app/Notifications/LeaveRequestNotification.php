<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private LeaveRequest $leave,
        private string $leaveAction, // 'approved' | 'rejected'
        private string $customMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'leave_id' => $this->leave->id,
            'action' => $this->leaveAction,
            'message' => $this->customMessage,
            'employee_name' => $this->leave->employee?->full_name ?? 'Không rõ',
            'leave_type' => $this->leave->leave_type,
            'start_date' => $this->leave->start_date?->format('d/m/Y'),
            'end_date' => $this->leave->end_date?->format('d/m/Y'),
            'type' => 'leave_request',
        ];
    }
}
