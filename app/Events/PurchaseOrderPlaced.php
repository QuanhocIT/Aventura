<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("supplier.{$this->purchaseOrder->supplier_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'total_amount' => (float) $this->purchaseOrder->total_amount,
            'status' => $this->purchaseOrder->status,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
