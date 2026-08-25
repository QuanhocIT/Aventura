<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo Chủ doanh nghiệp khi một yêu cầu vượt thẩm quyền Quản lý và cần Chủ quyết.
 */
class ApprovalEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $approval,
        private User $attemptedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->approval->operationLabel();
        $branch = $this->approval->branch?->name;

        $message = "Yêu cầu {$label} vượt thẩm quyền của Quản lý {$this->attemptedBy->name}";

        if ($branch) {
            $message .= " (chi nhánh {$branch})";
        }

        $message .= ' — cần bạn quyết định.';

        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $label,
            'escalation_reason' => $this->approval->escalation_reason,
            'attempted_by' => $this->attemptedBy->name,
            'branch_name' => $branch,
            'amount_involved' => $this->approval->amount_involved,
            'message' => $message,
            'url' => '/approvals',
        ];
    }
}
