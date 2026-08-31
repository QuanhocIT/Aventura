<?php

namespace App\Events\SuperAdmin;

use App\Models\NotificationCampaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCampaignBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NotificationCampaign $campaign,
        public array $restaurantIds = [],
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('superadmin.campaigns')];

        foreach (array_unique(array_map('intval', $this->restaurantIds)) as $restaurantId) {
            if ($restaurantId > 0) {
                $channels[] = new PrivateChannel(
                    "restaurant.{$restaurantId}.campaigns.{$this->campaign->target_role}",
                );
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'campaign.broadcasted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->campaign->id,
            'title' => $this->campaign->title,
            'content' => $this->campaign->content,
            'target_type' => $this->campaign->target_type,
            'target_plan_id' => $this->campaign->target_plan_id,
            'target_role' => $this->campaign->target_role,
            'sent_at' => optional($this->campaign->sent_at)->toIso8601String(),
        ];
    }
}
