<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $approval,
        private string $decision, // 'approved' | 'rejected'
        private ?User $reviewer = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->approval->operationLabel();
        // Yêu cầu có thể do Chủ hoặc Quản lý chi nhánh xử lý, nên phải nêu đích
        // danh người quyết định thay vì mặc định là "chủ doanh nghiệp".
        $by = $this->reviewerDescription();

        if ($this->approval->operation_type === 'order_refund') {
            $orderNumber = $this->approval->operation_data['order_number'] ?? '';
            $label = trim("Hoàn tiền đơn {$orderNumber}");
        }

        $message = $this->decision === 'approved'
            ? "Yêu cầu {$label} của bạn đã được {$by} phê duyệt."
            : "Yêu cầu {$label} của bạn đã bị {$by} từ chối"
                .($this->approval->rejection_reason ? ": {$this->approval->rejection_reason}" : '.');

        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $this->approval->operationLabel(),
            'decision' => $this->decision,
            'reviewer_name' => $this->reviewer?->name,
            'reviewer_role' => $this->approval->decided_by_role,
            'rejection_reason' => $this->approval->rejection_reason,
            'message' => $message,
            'url' => '/my-requests',
        ];
    }

    private function reviewerDescription(): string
    {
        $roleLabel = match ($this->approval->decided_by_role) {
            'owner' => 'Chủ doanh nghiệp',
            'manager' => 'Quản lý chi nhánh',
            'super_admin' => 'Quản trị hệ thống',
            default => null,
        };

        if ($this->reviewer && $roleLabel) {
            return "{$roleLabel} {$this->reviewer->name}";
        }

        return $this->reviewer?->name ?? $roleLabel ?? 'người phụ trách';
    }
}
