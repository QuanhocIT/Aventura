<?php

namespace App\Notifications;

use App\Models\Salary;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalaryReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Salary $salary,
        private string $customMessage
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'salary_id' => $this->salary->id,
            'message' => $this->customMessage,
            'net_salary' => (float)$this->salary->net_salary,
            'pay_period' => $this->salary->pay_period_start ? \Carbon\Carbon::parse($this->salary->pay_period_start)->format('m/Y') : '',
            'type' => 'salary_ready',
        ];
    }
}
