<?php

namespace App\Events\Customer;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public ?int $branchId,
        public array $shiftIds,
        public array $recipientUserIds,
        public string $tableName,
        public string $areaName,
        public string $message = 'Khách gọi nhân viên'
    ) {}

    public function broadcastOn(): array
    {
        return collect($this->recipientUserIds)
            ->unique()
            ->map(fn (int $userId) => new PrivateChannel("App.Models.User.{$userId}"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'staff.called';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'branch_id' => $this->branchId,
            'shift_ids' => $this->shiftIds,
            'table_name' => $this->tableName,
            'area_name' => $this->areaName,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
