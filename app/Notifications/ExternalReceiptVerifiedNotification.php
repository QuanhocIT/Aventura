<?php

namespace App\Notifications;

use App\Models\WarehouseReceivingVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExternalReceiptVerifiedNotification extends Notification
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
        $checker = $this->voucher->verifiedBy?->name ?: 'nhân viên Kho Tổng';
        $discrepancy = (float) $this->voucher->total_discrepancy_qty;

        return [
            'type' => 'external_receipt_verified',
            'title' => 'Phiếu nhập nguyên liệu ngoài đã được xác nhận',
            'message' => "Phiếu {$this->voucher->voucher_code} từ {$source} đã được {$checker} kiểm kê và xác nhận; tồn Kho Tổng đã được cập nhật.",
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->voucher_code,
            'source_name' => $source,
            'verified_by' => $checker,
            'received_by' => $this->voucher->receivedBy?->name,
            'total_actual_qty' => (float) $this->voucher->total_actual_qty,
            'total_value' => (float) $this->voucher->invoice_total_amount,
            'discrepancy_qty' => $discrepancy,
            'has_discrepancy' => $discrepancy !== 0.0,
            'url' => '/inventory/central-warehouse/receiving?voucher='.$this->voucher->id,
        ];
    }
}
