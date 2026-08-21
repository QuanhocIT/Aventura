<?php

namespace App\Notifications;

use App\Models\InventoryDiscrepancyDispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseDisputeAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private InventoryDiscrepancyDispute $dispute,
        private User $actor,
        private bool $isResponse = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->isResponse ? 'warehouse_dispute_response' : 'warehouse_dispute_assigned',
            'title' => $this->isResponse ? 'Có phản hồi tranh chấp kho mới' : 'Bạn được gán xử lý tranh chấp kho',
            'message' => $this->isResponse
                ? $this->actor->name.' đã phản hồi biên bản '.$this->dispute->dispute_code.'.'
                : 'Bạn được gán xử lý biên bản '.$this->dispute->dispute_code.'.',
            'dispute_id' => $this->dispute->id,
            'url' => '/inventory/staff-portal?tab=incident',
        ];
    }
}
