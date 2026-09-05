<?php

namespace App\Notifications;

use App\Models\WarehouseTaskAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WarehouseTaskOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(private WarehouseTaskAssignment $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $session = $this->task->countSession;

        return [
            'type' => 'warehouse_counting_task_overdue',
            'title' => 'Task kiểm kê Kho Tổng đã quá hạn',
            'message' => 'Task kiểm kê #'.$this->task->id
                .' của kỳ chốt #'.($session?->id ?? $this->task->count_session_id)
                .' đã quá hạn. Hãy nhắc nhở hoặc phân công lại.',
            'task_id' => $this->task->id,
            'count_session_id' => $this->task->count_session_id,
            'branch_id' => $session?->branch_id,
            'due_at' => $this->task->due_at?->toISOString(),
            'url' => '/inventory/central-warehouse/material-closing?session='.$this->task->count_session_id,
        ];
    }
}
