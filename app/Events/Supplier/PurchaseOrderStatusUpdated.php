<?php

namespace App\Events\Supplier;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast đến nhà hàng khi supplier cập nhật trạng thái PO.
 * Bộ phận kho lắng nghe trên private-channel: warehouse.{restaurant_id}
 */
class PurchaseOrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PurchaseOrder $po,
        public string $fromStatus,
        public string $toStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("warehouse.{$this->po->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.status-updated';
    }

    public function broadcastWith(): array
    {
        $labels = PurchaseOrder::$statusLabels;

        return [
            'id'               => $this->po->id,
            'po_number'        => $this->po->po_number,
            'supplier_name'    => $this->po->supplier?->name,
            'from_status'      => $this->fromStatus,
            'to_status'        => $this->toStatus,
            'from_label'       => $labels[$this->fromStatus] ?? $this->fromStatus,
            'to_label'         => $labels[$this->toStatus] ?? $this->toStatus,
            'supplier_note'    => $this->po->supplier_note,
            'updated_at'       => $this->po->updated_at->toIso8601String(),
        ];
    }
}
