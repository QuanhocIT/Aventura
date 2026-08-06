<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductSoldOutNotification extends Notification
{
    use Queueable;

    public function __construct(
        private int $productId,
        private string $productName,
        private int $branchId,
        private string $branchName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inventory_product_sold_out',
            'product_id' => $this->productId,
            'branch_id' => $this->branchId,
            'branch_name' => $this->branchName,
            'title' => 'Món ăn đã hết',
            'message' => "Món {$this->productName} đã hết suất phục vụ tại chi nhánh {$this->branchName}.",
            'url' => '/cashier',
        ];
    }
}
