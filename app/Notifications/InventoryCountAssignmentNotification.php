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
        $isMaterialClosing = $this->session->type === 'material_closing';
        $isBranchClosing = $this->session->type === 'branch_closing';

        return [
            'type' => $isMaterialClosing
                ? 'material_closing_assignment'
                : ($isBranchClosing ? 'branch_closing_assignment' : 'inventory_count_assignment'),
            'title' => $isMaterialClosing
                ? 'Bạn được giao đối chiếu kỳ chốt nguyên liệu'
                : ($isBranchClosing ? 'Bạn được giao đối chiếu kỳ chốt kho chi nhánh' : 'Bạn được phân công đếm kho lần 2'),
            'message' => $isMaterialClosing
                ? 'Bạn được giao đối chiếu thực tế cho kỳ chốt nguyên liệu #'.$this->session->id.'.'
                : ($isBranchClosing
                    ? 'Bạn được giao đối chiếu thực tế cho kỳ chốt kho chi nhánh #'.$this->session->id.'.'
                    : 'Bạn được phân công kiểm kê chéo cho phiên #'.$this->session->id.'.'),
            'count_session_id' => $this->session->id,
            'branch_id' => $this->session->branch_id,
            'url' => $isMaterialClosing
                ? '/inventory/central-warehouse/material-closing?session='.$this->session->id
                : ($isBranchClosing
                    ? '/inventory/branch-closing?branch_id='.$this->session->branch_id.'&session='.$this->session->id
                    : '/inventory/count-sessions?branch_id='.$this->session->branch_id),
        ];
    }
}
