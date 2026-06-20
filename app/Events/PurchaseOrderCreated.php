<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->purchaseOrder->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.created';
    }

    public function broadcastWith(): array
    {
        $this->purchaseOrder->load(['supplier', 'creator', 'items.ingredient.unit']);

        $items = $this->purchaseOrder->items->map(fn ($item) => [
            'id' => $item->id,
            'ingredient_name' => $item->ingredient?->name ?? 'Nguyên liệu',
            'quantity' => (float) $item->quantity,
            'unit_symbol' => $item->ingredient?->unit?->symbol ?? '',
            'unit_cost' => (float) $item->unit_cost,
        ])->toArray();

        return [
            'purchase_order' => [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status,
                'total_amount' => (float) $this->purchaseOrder->total_amount,
                'notes' => $this->purchaseOrder->notes,
                'created_at' => $this->purchaseOrder->created_at->format('Y-m-d H:i:s'),
                'creator_name' => $this->purchaseOrder->creator?->name ?? 'Hệ thống',
                'supplier_name' => $this->purchaseOrder->supplier?->name ?? 'Không có',
                'items_count' => count($items),
                'items' => $items,
            ],
        ];
    }
}
