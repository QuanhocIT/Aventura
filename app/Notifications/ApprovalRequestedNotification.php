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
        $operationData = $this->approval->operation_data ?? [];
        $message = "{$this->requester->name} yêu cầu {$this->approval->operationLabel()} — cần phê duyệt.";

        if ($this->approval->operation_type === 'inventory_waste' && ! empty($operationData['ingredient_name'])) {
            $quantity = $operationData['quantity'] ?? 0;
            $unit = $operationData['unit_symbol'] ?? '';
            $message = "{$this->requester->name} báo hao hụt {$operationData['ingredient_name']} ({$quantity} {$unit}) — cần phê duyệt.";
        }

        if ($this->approval->operation_type === 'order_refund' && ! empty($operationData['order_number'])) {
            $amount = number_format((float) ($operationData['refund_amount'] ?? 0), 0, ',', '.');
            $message = "{$this->requester->name} yêu cầu hoàn {$amount}đ cho đơn {$operationData['order_number']} — cần phê duyệt.";
        }

        return [
            'approval_id' => $this->approval->id,
            'operation_type' => $this->approval->operation_type,
            'operation_label' => $this->approval->operationLabel(),
            'requester_name' => $this->requester->name,
            'message' => $message,
            'url' => '/approvals',
        ];
    }
}
