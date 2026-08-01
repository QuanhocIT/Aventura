<?php

namespace App\Events\Customer;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public string $tableName,
        public string $areaName
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'payment.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'table_name' => $this->tableName,
            'area_name' => $this->areaName,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
