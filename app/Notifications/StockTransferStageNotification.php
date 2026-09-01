<?php

namespace App\Notifications;

use App\Models\StockTransferRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo tiến trình điều chuyển hàng liên chi nhánh theo từng bước (yêu cầu / định tuyến / xuất / nhận).
 * Gửi tới đúng người cần hành động tiếp theo.
 */
class StockTransferStageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private StockTransferRequest $transfer,
        private string $stage,   // requested | routed | dispatched | received | discrepancy | rejected | cancelled
        private string $byName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->transfer->loadMissing(['toBranch', 'fromBranch', 'ingredient.unit']);

        $ing = $this->transfer->ingredient?->name ?? 'nguyên liệu';
        $unit = $this->transfer->ingredient?->unit?->symbol ?? '';
        $qty = rtrim(rtrim((string) $this->transfer->quantity_requested, '0'), '.');
        $toBranchName = $this->transfer->toBranch?->name ?? 'Chi nhánh nhận';
        $fromBranchName = $this->transfer->fromBranch?->name ?? 'Kho cấp';
        $isSourceBranchUser = $notifiable instanceof User && (int) $notifiable->assignedBranchId() === (int) $this->transfer->from_branch_id;
        $isDestBranchUser = $notifiable instanceof User && (int) $notifiable->assignedBranchId() === (int) $this->transfer->to_branch_id;
        $isRequester = $notifiable instanceof User && (int) $this->transfer->requested_by === (int) $notifiable->id;
        $isOwnerOrWarehouse = $notifiable instanceof User && $notifiable->hasAnyRole(['owner', 'warehouse_manager']);
        $urgentLabel = ($this->transfer->priority ?? 'normal') === 'urgent' ? ' [Khẩn cấp]' : '';

        $title = match ($this->stage) {
            'requested' => "Yêu cầu điều chuyển hàng{$urgentLabel}",
            'routed' => $isSourceBranchUser ? "Yêu cầu xuất kho điều chuyển{$urgentLabel}" : "Đã định tuyến điều chuyển",
            'dispatched' => "Hàng điều chuyển đang vận chuyển",
            'received' => "Hoàn tất nhận hàng điều chuyển",
            'discrepancy' => "Cảnh báo chênh lệch điều chuyển",
            'rejected' => "Yêu cầu điều chuyển bị từ chối",
            'cancelled' => "Yêu cầu điều chuyển đã hủy",
            default => "Cập nhật điều chuyển hàng",
        };

        $message = match ($this->stage) {
            'requested' => "Chi nhánh {$toBranchName} vừa tạo yêu cầu bổ sung {$qty} {$unit} {$ing} (Người tạo: {$this->byName}).",
            'routed' => $isSourceBranchUser
                ? "Chi nhánh {$toBranchName} yêu cầu xuất kho {$qty} {$unit} {$ing} từ chi nhánh của bạn (Người tạo: {$this->byName})."
                : ($isRequester
                    ? "Yêu cầu điều chuyển {$qty} {$unit} {$ing} tới {$fromBranchName} đã được định tuyến; đang chờ kho cấp xuất hàng."
                    : "Đơn điều chuyển {$qty} {$unit} {$ing} đã được chỉ định kho cấp {$fromBranchName}."),
            'dispatched' => "Kho {$fromBranchName} đã xuất {$qty} {$unit} {$ing} giao tới {$toBranchName} (Mã bàn giao: {$this->transfer->handover_code}).",
            'received' => "Chi nhánh {$toBranchName} đã kiểm đếm và nhận đủ {$qty} {$unit} {$ing} vào tồn kho.",
            'discrepancy' => "Phát hiện lệch số lượng khi nhận {$ing} tại {$toBranchName} — cần kiểm tra và chốt biên bản.",
            'rejected' => "Yêu cầu điều chuyển {$ing} tới {$fromBranchName} đã bị từ chối: ".($this->transfer->reject_reason ?? 'Không đủ điều kiện.'),
            'cancelled' => "Yêu cầu điều chuyển {$ing} đã bị hủy bởi {$this->byName}.",
            default => "Đơn điều chuyển {$ing} có cập nhật trạng thái mới.",
        };

        return [
            'type' => 'stock_transfer_'.$this->stage,
            'stage' => $this->stage,
            'title' => $title,
            'message' => $message,
            'transfer_id' => $this->transfer->id,
            'request_group_id' => $this->transfer->request_group_id,
            'priority' => $this->transfer->priority ?? 'normal',
            'ingredient' => $ing,
            'from_branch_id' => $this->transfer->from_branch_id,
            'to_branch_id' => $this->transfer->to_branch_id,
            'handover_code' => $this->transfer->handover_code,
            'url' => '/inventory/transfers',
        ];
    }
}
