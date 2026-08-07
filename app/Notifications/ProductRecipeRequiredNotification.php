<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductRecipeRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(private Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_recipe_required',
            'title' => 'Món mới cần tạo công thức',
            'message' => "Món \"{$this->product->name}\" đang bị ẩn khỏi menu. Hãy thiết lập định lượng nguyên liệu trước khi bán.",
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'url' => '/inventory',
        ];
    }
}
