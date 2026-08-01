<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StorageQuotaWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $restaurantName,
        public readonly float $usedMb,
        public readonly int $limitMb,
        public readonly int $percentage
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aventura: Cảnh báo dung lượng lưu trữ vượt 90%')
            ->greeting('Xin chào,')
            ->line('Nhà hàng "'.$this->restaurantName.'" đang sử dụng dung lượng lưu trữ vượt quá giới hạn an toàn.')
            ->line('Dung lượng đã dùng: '.$this->usedMb.' MB / '.$this->limitMb.' MB ('.$this->percentage.'%).')
            ->line('Để tránh việc không thể tải lên các ảnh thực đơn, hóa đơn hoặc món ăn mới, vui lòng dọn dẹp các tệp cũ hoặc liên hệ Admin để nâng cấp gói dịch vụ.')
            ->action('Đến trang quản lý', url('/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Cảnh báo dung lượng lưu trữ',
            'message' => 'Nhà hàng của bạn đã sử dụng '.$this->usedMb.' MB / '.$this->limitMb.' MB ('.$this->percentage.'%).',
            'restaurant_name' => $this->restaurantName,
            'used_mb' => $this->usedMb,
            'limit_mb' => $this->limitMb,
            'percentage' => $this->percentage,
        ];
    }
}
