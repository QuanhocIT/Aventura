<?php

namespace App\Notifications;

use App\Models\InventoryBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Báo Chủ / Trưởng kho có một lô nguyên liệu bị yêu cầu thu hồi để xử lý.
 */
class BatchRecallRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private InventoryBatch $batch, private string $requestedByName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ingredient = $this->batch->ingredient?->name ?? 'nguyên liệu';

        return [
            'batch_id' => $this->batch->id,
            'batch_number' => $this->batch->batch_number,
            'ingredient' => $ingredient,
            'quantity_remaining' => (float) $this->batch->quantity_remaining,
            'message' => "♻️ Yêu cầu THU HỒI lô {$this->batch->batch_number} ({$ingredient}) — {$this->requestedByName} đề nghị xử lý.",
            'url' => '/inventory',
        ];
    }
}
