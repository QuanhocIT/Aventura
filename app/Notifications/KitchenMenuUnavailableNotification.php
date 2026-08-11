<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KitchenMenuUnavailableNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $productNames
     */
    public function __construct(
        private array $productNames,
        private string $branchName,
        private string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $productCount = count($this->productNames);
        $productLabel = $productCount === 1
            ? 'món "'.$this->productNames[0].'"'
            : $productCount.' món ăn: '.implode(', ', array_slice($this->productNames, 0, 4));

        if ($productCount > 4) {
            $productLabel .= ' và '.($productCount - 4).' món khác';
        }

        return [
            'type' => 'kitchen_menu_unavailable',
            'title' => 'Bếp vừa tạm ngưng món',
            'message' => 'Đã tạm ngưng '.$productLabel.' tại chi nhánh '.$this->branchName.' đến hết ngày. Lý do: '.$this->reason,
            'branch_name' => $this->branchName,
            'product_names' => $this->productNames,
            'reason' => $this->reason,
            'url' => '/audit-logs',
        ];
    }
}
