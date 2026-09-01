<?php

namespace App\Notifications;

use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupplyRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private SupplyRequest $supplyRequest,
        private string $stage,
        private ?string $customMessage = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $requestCode = $this->supplyRequest->request_code;
        $branchName = $this->supplyRequest->toBranch?->name ?? 'Chi nhánh';

        [$title, $defaultMsg] = match ($this->stage) {
            'approved' => [
                'Đơn cấp phát đã được duyệt',
                "Đơn cấp phát #{$requestCode} gửi tới {$branchName} đã được Trưởng kho phê duyệt.",
            ],
            'rejected' => [
                'Đơn cấp phát bị từ chối',
                "Đơn cấp phát #{$requestCode} của {$branchName} đã bị từ chối: ".($this->supplyRequest->rejection_reason ?? 'Không đủ điều kiện.'),
            ],
            'preparing' => [
                'Đơn cấp phát đang được soạn hàng',
                "Đơn cấp phát #{$requestCode} đang được nhân viên kho soạn hàng tại Kho Tổng.",
            ],
            'prepared' => [
                'Đơn cấp phát đã soạn xong',
                "Đơn cấp phát #{$requestCode} đã soạn xong, đang chờ Trưởng kho duyệt xuất.",
            ],
            'dispatch_approved' => [
                'Đã duyệt xuất kho',
                "Đơn cấp phát #{$requestCode} đã được duyệt số lượng xuất, sẵn sàng bàn giao vận chuyển.",
            ],
            'dispatched' => [
                'Hàng đang được vận chuyển',
                "Đơn cấp phát #{$requestCode} đã xuất kho và đang trên đường giao tới {$branchName}.",
            ],
            'delivery_confirmed' => [
                'Đã giao hàng tới chi nhánh',
                "Nhân viên giao hàng đã xác nhận đơn #{$requestCode} tới {$branchName}; chi nhánh có thể kiểm đếm và nghiệm thu.",
            ],
            'delivery_assigned' => [
                'Đã phân công giao hàng',
                "Đơn cấp phát #{$requestCode} đã được phân công nhân viên giao tới {$branchName}.",
            ],
            'received' => [
                'Chi nhánh đã nhận hàng',
                "Chi nhánh {$branchName} đã nhận hàng cho đơn cấp phát #{$requestCode}.",
            ],
            'received_clean' => [
                'Đã tự động nhập kho',
                "Đơn cấp phát #{$requestCode} đã đối soát đạt toàn bộ; hệ thống đã tự động nhập kho các nguyên liệu đạt tại {$branchName}.",
            ],
            'disputed' => [
                'Cảnh báo tranh chấp giao nhận kho',
                "Phát hiện chênh lệch / thiếu hàng khi nhận đơn #{$requestCode} tại {$branchName}.",
            ],
            'cancelled' => [
                'Đơn cấp phát đã bị hủy',
                "Đơn cấp phát #{$requestCode} đã bị hủy bỏ.",
            ],
            'backorder_created' => [
                'Tạo đơn giao bù hàng (Backorder)',
                "Đã tự động tạo đơn giao bù #{$requestCode} cho phần nguyên vật liệu còn thiếu.",
            ],
            'overdue_alert' => [
                'Cảnh báo đơn cấp phát quá hạn',
                "Đơn cấp phát #{$requestCode} đã quá hạn xử lý so với thời gian quy định.",
            ],
            default => [
                'Cập nhật đơn cấp phát',
                "Đơn cấp phát #{$requestCode} có cập nhật trạng thái mới.",
            ],
        };

        $isCentralUser = $notifiable instanceof User && ($notifiable->isSuperAdmin() || $notifiable->isOwner() || $notifiable->hasRole('warehouse_manager'));

        $url = $isCentralUser
            ? '/inventory/central-warehouse'
            : '/inventory/branch-requisition?branch_id='.($notifiable->assignedBranchId() ?? $this->supplyRequest->to_branch_id).'&request_id='.$this->supplyRequest->id;

        return [
            'type' => 'supply_request_status_'.$this->stage,
            'stage' => $this->stage,
            'title' => $title,
            'message' => $this->customMessage ?? $defaultMsg,
            'supply_request_id' => $this->supplyRequest->id,
            'request_code' => $requestCode,
            'branch_id' => $this->supplyRequest->to_branch_id,
            'url' => $url,
        ];
    }
}
