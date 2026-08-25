<?php

namespace App\Notifications;

use App\Models\StockTransferRequest;
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
        $messages = [
            'requested' => "📦 Yêu cầu điều chuyển {$ing} — cần Chủ định tuyến chi nhánh cấp.",
            'routed' => "➡️ Chủ đã định tuyến điều chuyển {$ing} tới chi nhánh bạn — cần XUẤT hàng (mã: {$this->transfer->handover_code}).",
            'dispatched' => "📥 Hàng {$ing} đã xuất — nhập mã {$this->transfer->handover_code} để NHẬN.",
            'received' => "✅ Điều chuyển {$ing} đã được nhận đủ và cộng vào tồn kho.",
            'discrepancy' => "⚠️ Điều chuyển {$ing} phát sinh chênh lệch — cần kiểm tra và chốt biên bản.",
        ];

        return [
            'transfer_id' => $this->transfer->id,
            'stage' => $this->stage,
            'ingredient' => $ing,
            'handover_code' => $this->transfer->handover_code,
            'message' => ($messages[$this->stage] ?? 'Cập nhật điều chuyển').' ('.$this->byName.')',
            'url' => '/inventory/transfers',
        ];
    }
}
