<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Biên bản chênh lệch nhận hàng: báo Chủ và Trưởng kho khi đơn nhập bị đóng băng do
 * lệch số lượng/giá so với đơn đặt.
 */
class PurchaseDiscrepancyNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $discrepancies
     */
    public function __construct(private PurchaseOrder $purchaseOrder, private array $discrepancies) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'supplier' => $this->purchaseOrder->supplier?->name,
            'discrepancy_count' => count($this->discrepancies),
            'message' => "⚠️ Đơn nhập PO #{$this->purchaseOrder->po_number} bị đóng băng do chênh lệch đối soát — cần xử lý.",
            'url' => '/suppliers',
        ];
    }
}
