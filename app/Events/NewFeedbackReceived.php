<?php

namespace App\Events;

use App\Models\CustomerFeedback;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewFeedbackReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CustomerFeedback $feedback) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->feedback->restaurant_id}")];
    }

    public function broadcastAs(): string
    {
        return 'feedback.received';
    }

    public function broadcastWith(): array
    {
        return [
            'feedback' => [
                'id' => $this->feedback->id,
                'submitted_by_name' => $this->feedback->is_anonymous ? 'Ẩn danh' : ($this->feedback->submitted_by_name ?? 'Khách vãng lai'),
                'rating' => (int) $this->feedback->rating,
                'content' => $this->feedback->content,
                'created_at' => $this->feedback->created_at->format('H:i d/m/Y'),
            ],
        ];
    }
}
