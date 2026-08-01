<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $approval,
        private User $requester,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $this->approval->operationLabel(),
            'requester_name' => $this->requester->name,
            'message' => "{$this->requester->name} yêu cầu {$this->approval->operationLabel()} — cần phê duyệt.",
            'url' => '/approvals',
        ];
    }
}
