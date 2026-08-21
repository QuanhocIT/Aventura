<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WarehouseShiftHandover;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseShiftHandoverPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private WarehouseShiftHandover $handover,
        private User $fromUser,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warehouse_shift_handover_pending',
            'title' => 'Có bàn giao ca Kho Tổng cần xác nhận',
            'message' => $this->fromUser->name.' đã nộp bàn giao ca #'.$this->handover->id.'.',
            'handover_id' => $this->handover->id,
            'url' => '/inventory/staff-portal?tab=handover',
        ];
    }
}
