<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Thông báo dunning đa giai đoạn cho việc gia hạn subscription.
 *
 * Stage 1 (D-7): Nhắc nhở 7 ngày trước khi hết hạn
 * Stage 2 (D-3): Cảnh báo 3 ngày, kèm ưu đãi
 * Stage 3 (D0):  Hết hạn hôm nay, grace period
 * Stage 4 (D+3): Đã hết grace, tài khoản bị khóa
 */
class DunningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $restaurantName,
        public readonly string $expiresAt,
        public readonly int    $daysLeft,    // negative = đã quá hạn
        public readonly string $renewalUrl,
        public readonly string $stage,       // 'd7' | 'd3' | 'd0' | 'overdue'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->stage) {
            'd7'      => $this->buildD7Mail($notifiable),
            'd3'      => $this->buildD3Mail($notifiable),
            'd0'      => $this->buildD0Mail($notifiable),
            'overdue' => $this->buildOverdueMail($notifiable),
            default   => $this->buildD7Mail($notifiable),
        };
    }

    private function buildD7Mail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Aventura] Nhắc nhở: Gói dịch vụ của {$this->restaurantName} sắp hết hạn")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line("Gói dịch vụ của nhà hàng **{$this->restaurantName}** sẽ hết hạn vào ngày **{$this->expiresAt}** (còn **{$this->daysLeft} ngày**).")
            ->line("Để đảm bảo hoạt động kinh doanh không bị gián đoạn, vui lòng gia hạn trước ngày hết hạn.")
            ->action('Gia hạn ngay', $this->renewalUrl)
            ->line('Nếu bạn cần hỗ trợ, đừng ngần ngại liên hệ với chúng tôi.')
            ->salutation('Trân trọng, Đội ngũ Aventura');
    }

    private function buildD3Mail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Aventura] ⚠️ Còn 3 ngày — Gói dịch vụ {$this->restaurantName} sắp hết hạn")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line("⚠️ **Chỉ còn {$this->daysLeft} ngày** — Gói dịch vụ của **{$this->restaurantName}** sẽ hết hạn vào **{$this->expiresAt}**.")
            ->line("Gia hạn ngay để không bị gián đoạn các chức năng quan trọng như đặt bàn, quản lý đơn hàng và báo cáo.")
            ->action('Gia hạn ngay', $this->renewalUrl)
            ->line("Bạn có thể dùng mã giảm giá nếu có để tiết kiệm thêm.")
            ->salutation('Trân trọng, Đội ngũ Aventura');
    }

    private function buildD0Mail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Aventura] 🔴 Gói dịch vụ {$this->restaurantName} hết hạn hôm nay")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line("🔴 Gói dịch vụ của **{$this->restaurantName}** đã **hết hạn hôm nay** ({$this->expiresAt}).")
            ->line("Hệ thống sẽ bước vào **grace period 3 ngày** — bạn vẫn có thể sử dụng dịch vụ trong thời gian này.")
            ->line("Vui lòng gia hạn ngay để tránh mất quyền truy cập vào dữ liệu và các tính năng.")
            ->action('Gia hạn ngay', $this->renewalUrl)
            ->salutation('Trân trọng, Đội ngũ Aventura');
    }

    private function buildOverdueMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Aventura] 🚫 Tài khoản {$this->restaurantName} đã bị tạm khóa")
            ->greeting("Xin chào {$notifiable->name}!")
            ->line("🚫 Tài khoản của **{$this->restaurantName}** đã bị tạm khóa do gói dịch vụ quá hạn kể từ **{$this->expiresAt}**.")
            ->line("Để khôi phục đầy đủ chức năng, vui lòng gia hạn ngay.")
            ->action('Gia hạn để khôi phục', $this->renewalUrl)
            ->line("Dữ liệu của bạn vẫn được bảo toàn. Tài khoản sẽ được kích hoạt lại ngay sau khi thanh toán.")
            ->salutation('Trân trọng, Đội ngũ Aventura');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'dunning',
            'stage'           => $this->stage,
            'restaurant_name' => $this->restaurantName,
            'expires_at'      => $this->expiresAt,
            'days_left'       => $this->daysLeft,
            'renewal_url'     => $this->renewalUrl,
        ];
    }
}
