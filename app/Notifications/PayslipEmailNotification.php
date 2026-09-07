<?php

namespace App\Notifications;

use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayslipEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Salary $salary,
        public array $breakdown
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->salary->employee;
        $period = $this->salary->pay_period_start ? Carbon::parse($this->salary->pay_period_start)->format('m/Y') : '';
        $netSalaryFormatted = number_format((float) $this->salary->net_salary).' VNĐ';
        $restaurantName = $this->salary->restaurant?->name ?? 'Aventura Restaurant';

        return (new MailMessage)
            ->subject("[$restaurantName] Phiếu Lương Chi Tiết Kỳ {$period} - {$employee?->full_name}")
            ->greeting("Kính gửi {$employee?->full_name},")
            ->line("Hệ thống quản trị {$restaurantName} xin gửi đến bạn chi tiết phiếu lương kỳ tháng {$period}:")
            ->line("• **Mã nhân viên:** " . ($employee?->employee_code ?? 'NV-' . $this->salary->employee_id))
            ->line("• **Chức vụ:** " . ($employee?->job_title ?? 'Nhân viên'))
            ->line("• **Lương cơ bản / Lương theo công:** " . number_format((float) $this->salary->base_salary) . " VNĐ")
            ->line("• **Tổng phụ cấp (Ăn ca, xăng xe, trách nhiệm):** +" . number_format((float) $this->salary->allowance_amount) . " VNĐ")
            ->line("• **Tiền tăng ca & Phụ cấp ca đêm:** +" . number_format((float) ($this->salary->overtime_amount + $this->salary->night_shift_amount)) . " VNĐ")
            ->line("• **Thưởng & Hoa hồng:** +" . number_format((float) $this->salary->bonus_amount) . " VNĐ")
            ->line("• **Tổng khấu trừ (Đi muộn, phạt, hao hụt):** -" . number_format((float) $this->salary->deduction_amount) . " VNĐ")
            ->line("• **Tạm ứng đã chi:** -" . number_format((float) $this->salary->advance_amount) . " VNĐ")
            ->line("• **LƯƠNG THỰC NHẬN (NET):** **{$netSalaryFormatted}**")
            ->action('Xem Chi Tiết Phiếu Lương Trên Portal', url('/portal/dashboard'))
            ->line('Nếu có bất kỳ thắc mắc hoặc khiếu nại nào về các khoản cấn trừ, vui lòng gửi phản hồi trên Cổng nhân viên (Portal) trước ngày thanh toán.')
            ->salutation("Trân trọng,\nBan Quản Trị {$restaurantName}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'salary_id' => $this->salary->id,
            'message' => "Phiếu lương kỳ tháng " . ($this->salary->pay_period_start ? Carbon::parse($this->salary->pay_period_start)->format('m/Y') : '') . " đã được gửi đến bạn.",
            'net_salary' => (float) $this->salary->net_salary,
            'pay_period' => $this->salary->pay_period_start ? Carbon::parse($this->salary->pay_period_start)->format('m/Y') : '',
            'type' => 'payslip_sent',
        ];
    }
}
