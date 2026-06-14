<?php

namespace App\Notifications;

use App\Models\ShiftSwap;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShiftSwapNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ShiftSwap $swap,
        private string $swapAction, // 'requested' | 'accepted' | 'cancelled' | 'approved' | 'rejected'
        private string $customMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'swap_id' => $this->swap->id,
            'action' => $this->swapAction,
            'message' => $this->customMessage,
            'requester_name' => $this->swap->requesterAssignment?->employee?->full_name ?? 'Không rõ',
            'receiver_name' => $this->swap->receiverAssignment?->employee?->full_name ?? 'Không rõ',
            'type' => 'shift_swap',
        ];
    }
}
