<?php

namespace App\Events\Customer;

use App\Models\TemporaryOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemporaryOrderEscalated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TemporaryOrder $temporaryOrder)
    {
        $this->temporaryOrder->load(['table.area']);
    }

    public function broadcastOn(): array
    {
        return [new Channel("restaurant.{$this->temporaryOrder->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'temporary_order.escalated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->temporaryOrder->id,
            'table_name' => $this->temporaryOrder->table?->name ?? 'Bàn trống',
            'area_name' => $this->temporaryOrder->table?->area?->name ?? 'Khu vực',
            'total_amount' => (float) $this->temporaryOrder->total_amount,
            'escalated_at' => now()->toIso8601String(),
        ];
    }
}
