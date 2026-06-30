<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QrOrderTimeoutEscalated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->order->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'qr-order.timeout-escalated';
    }

    public function broadcastWith(): array
    {
        $this->order->load(['table.area', 'items.product']);

        $items = $this->order->items->map(fn ($item) => [
            'id' => $item->id,
            'product_name' => $item->product?->name ?? 'Món ăn',
            'quantity' => (float) $item->quantity,
            'price' => (float) ($item->unit_price ?? $item->product?->price ?? 0),
            'notes' => $item->notes,
        ]);

        return [
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'table_name' => $this->order->table?->name,
                'area_name' => $this->order->table?->area?->name,
                'total_amount' => (float) $this->order->total_amount,
                'items_count' => $this->order->items->count(),
                'created_at' => $this->order->created_at->format('H:i:s d/m/Y'),
                'items' => $items,
            ],
        ];
    }
}
