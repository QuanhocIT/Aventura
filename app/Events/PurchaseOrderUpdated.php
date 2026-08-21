<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->purchaseOrder->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'status' => $this->purchaseOrder->status,
            'is_frozen' => $this->purchaseOrder->is_frozen,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
