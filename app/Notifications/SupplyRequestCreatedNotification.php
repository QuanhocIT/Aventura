<?php

namespace App\Notifications;

use App\Models\SupplyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupplyRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private SupplyRequest $supplyRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $branchName = $this->supplyRequest->toBranch?->name ?? 'Chi nhánh';
        $itemCount = $this->supplyRequest->items->count();

        return [
            'type' => 'supply_request_created',
            'title' => 'Yêu cầu cấp hàng mới',
            'message' => "{$branchName} vừa gửi yêu cầu cấp {$itemCount} nguyên liệu từ Tổng kho.",
            'supply_request_id' => $this->supplyRequest->id,
            'request_code' => $this->supplyRequest->request_code,
            'url' => '/inventory/central-warehouse',
        ];
    }
}
