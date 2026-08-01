<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScheduleUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $customMessage,
        private ?string $scheduledDate = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->customMessage,
            'scheduled_date' => $this->scheduledDate,
            'type' => 'schedule_updated',
        ];
    }
}
