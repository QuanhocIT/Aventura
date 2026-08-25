<?php

namespace App\Notifications;

use App\Models\InventoryCountSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryCountAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(private InventoryCountSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inventory_count_assignment',
            'title' => 'Bạn được phân công đếm kho lần 2',
            'message' => 'Bạn được phân công kiểm kê chéo cho phiên #'.$this->session->id.'.',
            'count_session_id' => $this->session->id,
            'branch_id' => $this->session->branch_id,
            'url' => '/inventory/count-sessions?branch_id='.$this->session->branch_id,
        ];
    }
}
