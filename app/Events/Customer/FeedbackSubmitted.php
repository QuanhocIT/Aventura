<?php

namespace App\Events\Customer;

use App\Models\CustomerFeedback;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedbackSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $restaurantId,
        public CustomerFeedback $feedback
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string
    {
        return 'feedback.submitted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->feedback->id,
            'rating' => (int) $this->feedback->rating,
            'content' => $this->feedback->content,
            'submitted_by_name' => $this->feedback->submitted_by_name ?: 'Ẩn danh',
            'created_at' => $this->feedback->created_at->toIso8601String(),
        ];
    }
}
