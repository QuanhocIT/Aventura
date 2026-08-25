<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\ScheduleAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceAutoCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ?ScheduleAssignment $assignment,
        private ApprovalRequest $approvalRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $shiftName = $this->assignment?->shift?->name ?? 'Ca trực';
        $scheduledDate = $this->assignment?->scheduled_date ?? today()->toDateString();

        return (new MailMessage)
            ->subject('[Aventura] Thông báo: Yêu cầu chấm công đã tự động bị hủy (Quá 24h)')
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line('Yêu cầu xác nhận chấm công cho ca làm việc "'.$shiftName.'" ngày '.$scheduledDate.' của bạn đã bị TỰ ĐỘNG HỦY.')
            ->line('Lý do: Đã quá 24 giờ kể từ lúc gửi nhưng Quản lý chi nhánh hoặc Chủ doanh nghiệp chưa thực hiện duyệt xác nhận.')
            ->line('Hệ thống đã tự động hủy bản ghi chấm công tạm thời và chuyển trạng thái ca làm việc.')
            ->action('Xem lịch làm việc của bạn', url('/schedules'))
            ->line('Nếu bạn thực tế có đi làm ca này, vui lòng liên hệ trực tiếp Quản lý chi nhánh hoặc Chủ doanh nghiệp để được hỗ trợ điều chỉnh.');
    }

    public function toArray(object $notifiable): array
    {
        $shiftName = $this->assignment?->shift?->name ?? 'Ca làm việc';

        return [
            'approval_id' => $this->approvalRequest->id,
            'assignment_id' => $this->assignment?->id,
            'message' => 'Chấm công ca "'.$shiftName.'" tự động bị hủy do Quản lý/Chủ không duyệt trong 24h.',
            'url' => '/schedules',
        ];
    }
}
