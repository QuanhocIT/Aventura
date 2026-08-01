<?php

namespace App\Notifications;

use App\Models\SalaryAdjustment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalaryDisputeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private SalaryAdjustment $adjustment,
        private User $sender,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabel = match ($this->adjustment->type) {
            'bonus' => 'Thưởng',
            'penalty' => 'Phạt',
            'cash_shortage' => 'Hụt két',
            'inventory_loss' => 'Hao hụt kho',
            'violation' => 'Vi phạm kỷ luật',
            default => 'Điều chỉnh lương',
        };

        return [
            'salary_id' => $this->adjustment->salary_id,
            'adjustment_id' => $this->adjustment->id,
            'employee_name' => $this->sender->name,
            'type' => $this->adjustment->type,
            'amount' => (float) $this->adjustment->amount,
            'dispute_reason' => $this->adjustment->dispute_reason,
            'message' => "Nhân viên {$this->sender->name} đã gửi khiếu nại khoản cấn trừ {$typeLabel} trị giá ".number_format($this->adjustment->amount)."đ: {$this->adjustment->dispute_reason}",
        ];
    }
}
