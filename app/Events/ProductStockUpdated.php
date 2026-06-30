<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public int $branchId,
        public int $productId,
        public bool $isAvailable
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("restaurant-public.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'product-stock.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'branch_id' => $this->branchId,
            'is_available' => $this->isAvailable,
        ];
    }
}
