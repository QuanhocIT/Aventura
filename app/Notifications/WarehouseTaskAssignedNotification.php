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
        $taskLabel = match ($this->task->task_type) {
            'delivery' => 'Giao hàng tới chi nhánh',
            'picking' => 'Soạn hàng theo đơn',
            'handover' => 'Bàn giao xuất kho',
            default => $this->task->task_type ?: 'kho',
        };

        return [
            'type' => 'warehouse_task_assigned',
            'title' => 'Bạn được phân công task Kho Tổng',
            'message' => $taskLabel.' #'.$this->task->id.' cần được xử lý.',
            'task_id' => $this->task->id,
            'task_type' => $this->task->task_type,
            'url' => '/inventory/staff-portal?tab='.($this->task->task_type === 'delivery' ? 'delivery' : 'today'),
        ];
    }
}
