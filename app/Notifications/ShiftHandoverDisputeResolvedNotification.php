<?php

namespace App\Notifications;

use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Thông báo khi quản lý/chủ đã phân xử xong tranh chấp bàn giao ca.
 */
class ShiftHandoverDisputeResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ShiftHandover $handover,
        private User $resolver,
        private string $notes,
        private ?float $finalCashAmount = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cashText = $this->finalCashAmount !== null
            ? ' Tiền mặt chốt: '.number_format($this->finalCashAmount).'đ.'
            : '';

        return [
            'handover_id' => $this->handover->id,
            'resolver_name' => $this->resolver->name,
            'notes' => $this->notes,
            'final_cash_amount' => $this->finalCashAmount,
            'message' => "Tranh chấp bàn giao ca #{$this->handover->id} đã được {$this->resolver->name} xử lý.{$cashText} Kết luận: {$this->notes}",
            'url' => '/shift-handovers',
        ];
    }
}
