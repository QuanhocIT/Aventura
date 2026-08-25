<?php

namespace App\Notifications;

use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo ca vào rằng có một phiên bàn giao đang chờ họ xác nhận.
 */
class ShiftHandoverPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ShiftHandover $handover,
        private User $fromUser,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $message = "{$this->fromUser->name} đã bàn giao ca";

        if ($this->handover->cash_amount !== null) {
            $message .= ' kèm '.number_format((float) $this->handover->cash_amount).'đ tiền mặt';
        }

        $message .= ' — cần bạn kiểm tra và xác nhận.';

        return [
            'handover_id' => $this->handover->id,
            'from_user_name' => $this->fromUser->name,
            'cash_amount' => $this->handover->cash_amount !== null ? (float) $this->handover->cash_amount : null,
            'incident_notes' => $this->handover->incident_notes,
            'pending_tasks' => $this->handover->pending_tasks,
            'message' => $message,
            'url' => '/shift-handovers',
        ];
    }
}
