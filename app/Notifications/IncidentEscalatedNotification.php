<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo Chủ nhà hàng có sự cố khẩn cấp vừa được báo lên (tự động escalate).
 */
class IncidentEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(private Incident $incident) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabels = [
            'accident' => 'Tai nạn',
            'food_poisoning' => 'Ngộ độc thực phẩm',
            'fire' => 'Cháy nổ',
            'security' => 'An ninh',
            'equipment_failure' => 'Hỏng thiết bị',
            'theft' => 'Trộm cắp',
            'other' => 'Khác',
        ];
        $type = $typeLabels[$this->incident->type] ?? $this->incident->type;

        return [
            'incident_id' => $this->incident->id,
            'type' => $this->incident->type,
            'severity' => $this->incident->severity,
            'title' => $this->incident->title,
            'message' => "🚨 SỰ CỐ KHẨN CẤP [{$type}]: {$this->incident->title} — cần bạn xử lý ngay.",
            'url' => '/incidents',
        ];
    }
}
