<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo Chủ doanh nghiệp biết một Quản lý vừa tự quyết một yêu cầu.
 *
 * Đây là vế "báo về cho chủ quản biết rõ" của cơ chế ủy quyền: Quản lý xử lý
 * ngay tại chi nhánh, nhưng Chủ luôn nhận được thông tin đầy đủ.
 */
class DelegatedApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $approval,
        private User $reviewer,
        private string $decision, // 'approved' | 'rejected'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->decision === 'approved' ? 'đã phê duyệt' : 'đã từ chối';
        $label = $this->approval->operationLabel();
        $branch = $this->approval->branch?->name;

        $message = "Quản lý {$this->reviewer->name} {$verb}: {$label}";

        if ($this->approval->amount_involved) {
            $message .= ' — '.number_format((float) $this->approval->amount_involved).'đ';
        }

        if ($branch) {
            $message .= " (chi nhánh {$branch})";
        }

        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $label,
            'decision' => $this->decision,
            'reviewer_name' => $this->reviewer->name,
            'reviewer_id' => $this->reviewer->id,
            'branch_name' => $branch,
            'amount_involved' => $this->approval->amount_involved,
            'message' => $message,
            'url' => '/approvals/ledger',
        ];
    }
}
