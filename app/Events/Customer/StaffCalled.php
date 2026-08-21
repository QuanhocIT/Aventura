<?php

namespace App\Events\Customer;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public string $tableName,
        public string $areaName,
        public string $message = 'Khách gọi nhân viên'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'staff.called';
    }

    public function broadcastWith(): array
    {
        return [
            'table_name' => $this->tableName,
            'area_name' => $this->areaName,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
