<?php

namespace App\Notifications;

use App\Models\StockTransferRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo tiến trình điều chuyển hàng liên chi nhánh theo từng bước (yêu cầu / định tuyến
 * / xuất). Gửi tới đúng người cần hành động tiếp theo.
 */
class StockTransferStageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private StockTransferRequest $transfer,
        private string $stage,   // requested | routed | dispatched | received | discrepancy
        private string $byName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ing = $this->transfer->ingredient?->name ?? 'nguyên liệu';
        $isOwnerOrWarehouse = $notifiable instanceof User
            && $notifiable->hasAnyRole(['owner', 'warehouse_manager']);
        $isRequester = $notifiable instanceof User
            && (int) $this->transfer->requested_by === (int) $notifiable->id;
        $urgentLabel = ($this->transfer->priority ?? 'normal') === 'urgent'
            ? ' KHẨN CẤP'
            : '';
        $messages = [
            'requested' => $isOwnerOrWarehouse
                ? "📦 Yêu cầu bổ sung{$urgentLabel} {$ing} — cần xem xét và điều phối."
                : "📦 Yêu cầu bổ sung{$urgentLabel} {$ing} đã được gửi tới Chủ doanh nghiệp để xem xét.",
            'routed' => $isRequester
                ? "➡️ Yêu cầu bổ sung {$ing} đã được Chủ doanh nghiệp điều phối; đang chờ kho thực hiện."
                : "➡️ Điều chuyển {$ing} đã được điều phối — chờ kho thực hiện (mã: {$this->transfer->handover_code}).",
            'dispatched' => "📥 Hàng {$ing} đã xuất kho — đang chờ xác nhận nhận hàng (mã: {$this->transfer->handover_code}).",
            'received' => "✅ Điều chuyển {$ing} đã được nhận đủ và cộng vào tồn kho.",
            'discrepancy' => "⚠️ Điều chuyển {$ing} phát sinh chênh lệch — cần kiểm tra và chốt biên bản.",
        ];

        return [
            'transfer_id' => $this->transfer->id,
            'stage' => $this->stage,
            'priority' => $this->transfer->priority ?? 'normal',
            'ingredient' => $ing,
            'handover_code' => $this->transfer->handover_code,
            'message' => ($messages[$this->stage] ?? 'Cập nhật điều chuyển').' ('.$this->byName.')',
            'url' => '/inventory/transfers',
        ];
    }
}
