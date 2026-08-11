<?php

namespace App\Notifications;

use App\Models\CashHandover;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo người nhận rằng có một khoản tiền đang chờ họ ký xác nhận.
 */
class CashHandoverPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private CashHandover $handover,
        private User $fromUser,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format((float) $this->handover->amount);

        return [
            'handover_id' => $this->handover->id,
            'amount' => (float) $this->handover->amount,
            'from_user_name' => $this->fromUser->name,
            'message' => "{$this->fromUser->name} bàn giao {$amount}đ — cần bạn ký xác nhận đã nhận đủ.",
            'url' => '/shift-closings',
        ];
    }
}
