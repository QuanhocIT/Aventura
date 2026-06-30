<?php

namespace App\Events;

use App\Models\RestaurantTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableInteraction implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RestaurantTable $table,
        public string $type,
        public string $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("restaurant.{$this->table->restaurant_id}"),
            new Channel("table.{$this->table->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'table.interaction';
    }

    public function broadcastWith(): array
    {
        return [
            'table_id' => $this->table->id,
            'table_name' => $this->table->name,
            'type' => $this->type,
            'message' => $this->message,
            'timestamp' => now()->format('H:i:s'),
        ];
    }
}
