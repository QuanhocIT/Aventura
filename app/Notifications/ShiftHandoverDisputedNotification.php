<?php

namespace App\Notifications;

use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Thông báo khi ca vào báo bàn giao không khớp (disputed) cần quản lý trọng tài giải quyết.
 */
class ShiftHandoverDisputedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ShiftHandover $handover,
        private User $disputerUser,
        private string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'handover_id' => $this->handover->id,
            'disputer_name' => $this->disputerUser->name,
            'reason' => $this->reason,
            'cash_amount' => $this->handover->cash_amount !== null ? (float) $this->handover->cash_amount : null,
            'message' => "Bàn giao ca #{$this->handover->id} bị báo không khớp bởi {$this->disputerUser->name}: {$this->reason}",
            'url' => '/shift-handovers',
        ];
    }
}
