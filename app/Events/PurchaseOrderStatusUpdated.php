<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->purchaseOrder->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'status' => $this->purchaseOrder->status,
            'updated_at' => $this->purchaseOrder->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
