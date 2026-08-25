<?php

namespace App\Events\Support;

use App\Models\SupportAnnouncement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportAnnouncementPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportAnnouncement $announcement) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('support.announcements')];
    }

    public function broadcastAs(): string
    {
        return 'support.announcement.published';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => $this->announcement->message,
            'audience' => $this->announcement->audience,
            'level' => $this->announcement->level,
            'starts_at' => optional($this->announcement->starts_at)?->toIso8601String(),
            'ends_at' => optional($this->announcement->ends_at)?->toIso8601String(),
            'published_at' => optional($this->announcement->published_at)?->toIso8601String(),
        ];
    }
}
