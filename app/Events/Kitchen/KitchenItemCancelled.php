<?php

namespace App\Events\Kitchen;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenItemCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public string $productName,
        public string $tableName,
        public float $quantity,
        public string $scope,
        public ?string $reason,
        public string $cancelledByName,
        public int $cancelledCount,
        public ?int $orderId = null,
        public array $orderIds = [],
        public ?string $tableToken = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("kitchen.{$this->restaurantId}"),
            new PrivateChannel("restaurant.{$this->restaurantId}"),
        ];

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'kitchen.item_cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'restaurant_id' => $this->restaurantId,
            'product_name' => $this->productName,
            'table_name' => $this->tableName,
            'quantity' => $this->quantity,
            'scope' => $this->scope,
            'reason' => $this->reason,
            'cancelled_by_name' => $this->cancelledByName,
            'cancelled_count' => $this->cancelledCount,
            'order_id' => $this->orderId,
            'order_ids' => $this->orderIds,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
