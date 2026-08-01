<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialOnboardingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $dayNumber,
        private readonly string $restaurantName,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return match ($this->dayNumber) {
            1 => $this->day1($notifiable),
            3 => $this->day3($notifiable),
            7 => $this->day7($notifiable),
            12 => $this->day12($notifiable),
            default => $this->day1($notifiable),
        };
    }

    private function day1($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Chào mừng {$this->restaurantName} đến Aventura!")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line('Cảm ơn bạn đã đăng ký Aventura. Tài khoản dùng thử 14 ngày của bạn đã sẵn sàng.')
            ->line('Bước tiếp theo: Thêm thực đơn và cấu hình bàn ăn để bắt đầu nhận đơn.')
            ->action('Bắt đầu thiết lập', url('/dashboard'))
            ->line('Cần hỗ trợ? Chatbot AI của chúng tôi sẵn sàng 24/7.');
    }

    private function day3($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Bạn đã thử QR Order chưa? — {$this->restaurantName}")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line('Bạn đã dùng Aventura được 3 ngày. Đây là những tính năng bạn chưa khám phá:')
            ->line('• QR Order — Khách tự gọi món từ điện thoại')
            ->line('• Kitchen Display — Bếp nhận đơn real-time')
            ->line('• Quản lý Kho — Tự động trừ nguyên liệu')
            ->action('Khám phá ngay', url('/dashboard'))
            ->line('Nâng cấp gói Cơ Bản để mở khóa tất cả.');
    }

    private function day7($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Còn 7 ngày dùng thử — {$this->restaurantName}")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line('Bạn đã qua nửa thời gian dùng thử. Đây là lúc để:')
            ->line('• Thêm nhân viên và phân quyền')
            ->line('• Cấu hình ca làm việc')
            ->line('• Xem báo cáo doanh thu đầu tiên')
            ->action('Nâng cấp ngay - Giảm 20% năm đầu', url('/billing/history'))
            ->line('Thanh toán năm tiết kiệm 20%. Không mất dữ liệu khi nâng gói.');
    }

    private function day12($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚡ Còn 2 ngày — Nâng cấp để không gián đoạn')
            ->greeting("Xin chào {$notifiable->name}!")
            ->line("Thời gian dùng thử của {$this->restaurantName} sắp hết.")
            ->line('Nếu không nâng cấp, tài khoản sẽ chuyển về gói Free với giới hạn:')
            ->line('• 1 chi nhánh, 15 bàn, 5 nhân viên')
            ->line('• Không có QR Order, Kitchen Display, Kho')
            ->action('Nâng cấp ngay →', url('/billing/history'))
            ->line('Toàn bộ dữ liệu được bảo toàn khi nâng gói.');
    }
}
