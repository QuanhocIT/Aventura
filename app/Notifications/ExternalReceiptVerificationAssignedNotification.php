<?php

namespace App\Notifications;

use App\Models\WarehouseReceivingVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExternalReceiptVerificationAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private WarehouseReceivingVoucher $voucher) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $source = $this->voucher->external_source_name ?: 'nguồn bên ngoài';

        return [
            'type' => 'external_receipt_verification_assigned',
            'title' => 'Bạn được giao kiểm kê phiếu nhập ngoài',
            'message' => "Phiếu {$this->voucher->voucher_code} từ {$source} đang chờ bạn kiểm kê và xác nhận.",
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->voucher_code,
            'source_name' => $source,
            'url' => '/inventory/staff-portal?tab=receiving&voucher='.$this->voucher->id,
        ];
    }
}
