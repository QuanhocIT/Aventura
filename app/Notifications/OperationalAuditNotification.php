<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Database notification for each hand-off in the audit/CAPA workflow. */
class OperationalAuditNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $type,
        private string $message,
        private string $url = '/operations/inspection-workspace',
        private array $payload = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge([
            'type' => $this->type,
            'message' => $this->message,
            'url' => $this->url,
        ], $this->payload);
    }
}
