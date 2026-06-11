<?php

namespace App\Events\Supplier;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast đến supplier khi nhà hàng gửi PO mới.
 * Supplier lắng nghe trên private-channel: supplier.{supplier_id}
 */
class PurchaseOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $po) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("supplier.{$this->po->supplier_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                    => $this->po->id,
            'po_number'             => $this->po->po_number,
            'restaurant_name'       => $this->po->restaurant?->name,
            'total_amount'          => (float) $this->po->total_amount,
            'items_count'           => $this->po->items()->count(),
            'expected_delivery_date'=> $this->po->expected_delivery_date?->toDateString(),
            'note'                  => $this->po->note,
            'created_at'            => $this->po->created_at->toIso8601String(),
        ];
    }
}
