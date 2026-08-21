<?php

namespace App\Notifications;

use App\Models\InventoryCountSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryCountApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private InventoryCountSession $session,
        private string $stage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $title = match ($this->stage) {
            'submitted' => 'Phiên kiểm kê đang chờ phê duyệt',
            'approved' => 'Phiên kiểm kê đã được phê duyệt',
            'rejected' => 'Phiên kiểm kê đã bị từ chối',
            default => 'Cập nhật phiên kiểm kê',
        };

        return [
            'type' => 'inventory_count_'.$this->stage,
            'title' => $title,
            'message' => $title.' #'.$this->session->id.'.',
            'count_session_id' => $this->session->id,
            'branch_id' => $this->session->branch_id,
            'url' => '/inventory/count-sessions?branch_id='.$this->session->branch_id,
        ];
    }
}
