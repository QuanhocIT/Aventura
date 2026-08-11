<?php

namespace App\Notifications;

use App\Models\CashHandover;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo người giao khi người nhận nói số tiền không khớp.
 */
class CashHandoverDisputedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private CashHandover $handover,
        private User $disputedBy,
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
            'disputed_by' => $this->disputedBy->name,
            'dispute_reason' => $this->handover->dispute_reason,
            'message' => "{$this->disputedBy->name} không xác nhận khoản bàn giao {$amount}đ: {$this->handover->dispute_reason}",
            'url' => '/shift-closings',
        ];
    }
}
