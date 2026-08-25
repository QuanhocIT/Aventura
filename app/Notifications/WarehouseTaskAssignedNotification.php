<?php

namespace App\Notifications;

use App\Models\WarehouseTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseTaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private WarehouseTaskAssignment $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'warehouse_task_assigned',
            'title' => 'Bạn được phân công task Kho Tổng',
            'message' => 'Task '.($this->task->task_type ?: 'kho').' #'.$this->task->id.' cần được xử lý.',
            'task_id' => $this->task->id,
            'task_type' => $this->task->task_type,
            'url' => '/inventory/staff-portal?tab=today',
        ];
    }
}
