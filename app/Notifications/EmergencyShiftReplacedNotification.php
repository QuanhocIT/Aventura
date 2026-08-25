<?php

namespace App\Notifications;

use App\Models\ScheduleAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Báo Chủ có một ca vừa được xếp người thay khẩn cấp (minh bạch, chống lạm dụng).
 */
class EmergencyShiftReplacedNotification extends Notification
{
    use Queueable;

    public function __construct(private ScheduleAssignment $assignment, private string $byName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $date = $this->assignment->scheduled_date instanceof Carbon
            ? $this->assignment->scheduled_date->format('d/m/Y')
            : (string) $this->assignment->scheduled_date;

        return [
            'assignment_id' => $this->assignment->id,
            'employee' => $this->assignment->employee?->full_name,
            'date' => $date,
            'reason' => $this->assignment->replacement_reason,
            'message' => "🔁 Thay ca khẩn cấp ({$date}) — {$this->byName} xếp {$this->assignment->employee?->full_name} vào thay.",
            'url' => '/employees',
        ];
    }
}
