<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardMetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $reason) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('superadmin.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'superadmin.dashboard.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
