<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OvertimeRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private OvertimeRequest $overtime,
        private string $action,
        private string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'overtime_request_id' => $this->overtime->id,
            'action' => $this->action,
            'message' => $this->message,
            'employee_name' => $this->overtime->employee?->full_name,
            'scheduled_date' => $this->overtime->scheduled_date?->format('d/m/Y'),
            'hours_requested' => (float) $this->overtime->hours_requested,
            'type' => 'overtime_request',
        ];
    }
}
