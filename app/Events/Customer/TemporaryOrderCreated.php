<?php

namespace App\Events\Customer;

use App\Models\TemporaryOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemporaryOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TemporaryOrder $temporaryOrder)
    {
        $this->temporaryOrder->load(['table.area']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->temporaryOrder->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'temporary_order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->temporaryOrder->id,
            'table_name' => $this->temporaryOrder->table?->name ?? 'Bàn trống',
            'area_name' => $this->temporaryOrder->table?->area?->name ?? 'Khu vực',
            'total_amount' => (float) $this->temporaryOrder->total_amount,
            'customer_name' => $this->temporaryOrder->customer_name,
            'customer_phone' => $this->temporaryOrder->customer_phone,
            'items' => $this->temporaryOrder->cart_data,
            'created_at' => $this->temporaryOrder->created_at->toIso8601String(),
        ];
    }
}
