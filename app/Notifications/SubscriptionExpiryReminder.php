<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $restaurantName,
        public readonly ?string $dueDate,
        public readonly string $status = 'upcoming'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->status === 'expired'
            ? 'Aventura: Gói dịch vụ đã hết hạn'
            : 'Aventura: Nhắc hạn thanh toán gói dịch vụ';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Xin chào,')
            ->line('Doanh nghiệp '.$this->restaurantName.' sắp đến hạn hoặc đã hết hạn gói dịch vụ.')
            ->line('Mốc thời gian: '.($this->dueDate ?: 'N/A'))
            ->line('Vui lòng hoàn tất gia hạn để tránh bị giới hạn tài khoản hoặc chuyển sang trạng thái suspended.');
    }
}