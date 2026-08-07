<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ApprovalRequest $approval,
        private string $decision, // 'approved' | 'rejected'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->approval->operationLabel();

        $message = $this->decision === 'approved'
            ? "Yêu cầu {$label} của bạn đã được phê duyệt."
            : "Yêu cầu {$label} của bạn đã bị từ chối".($this->approval->rejection_reason ? ": {$this->approval->rejection_reason}" : '.');

        if ($this->approval->operation_type === 'order_refund') {
            $orderNumber = $this->approval->operation_data['order_number'] ?? '';
            $message = $this->decision === 'approved'
                ? "Yêu cầu hoàn tiền đơn {$orderNumber} của bạn đã được chủ doanh nghiệp phê duyệt."
                : "Yêu cầu hoàn tiền đơn {$orderNumber} của bạn đã bị từ chối".($this->approval->rejection_reason ? ": {$this->approval->rejection_reason}" : '.');
        }

        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $label,
            'decision' => $this->decision,
            'rejection_reason' => $this->approval->rejection_reason,
            'message' => $message,
        ];
    }
}
