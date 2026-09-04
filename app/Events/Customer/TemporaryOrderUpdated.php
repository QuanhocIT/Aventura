<?php

namespace App\Events\Customer;

use App\Models\TemporaryOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemporaryOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TemporaryOrder $temporaryOrder)
    {
        $this->temporaryOrder->loadMissing('table');
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("restaurant.{$this->temporaryOrder->restaurant_id}")];

        /** @var \App\Models\RestaurantTable|\App\Models\Table|null $table */
        $table = $this->temporaryOrder->table;
        if ($table && ! empty($table->qr_token)) {
            $channels[] = new Channel('table.'.$table->qr_token);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'temporary_order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->temporaryOrder->id,
            'table_id' => $this->temporaryOrder->table_id,
            'status' => $this->temporaryOrder->status,
            'order_id' => $this->temporaryOrder->order_id,
            'awaiting_customer_confirmation' => (bool) $this->temporaryOrder->awaiting_customer_confirmation,
            'revision_note' => $this->temporaryOrder->revision_note,
            'total_amount' => (float) $this->temporaryOrder->total_amount,
            'cart_data' => $this->temporaryOrder->cart_data,
        ];
    }
}
