<?php

namespace App\Observers;

use App\Models\Restaurant;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Support\Facades\Log;

class RestaurantObserver
{
    /**
     * Nạp ma trận thẩm quyền mặc định cho nhà hàng mới.
     *
     * Không có bước này thì nhà hàng vừa tạo sẽ không có chính sách nào, và vì
     * hệ thống mặc định "thiếu cấu hình = chỉ Chủ được duyệt", Quản lý chi nhánh
     * sẽ không duyệt được gì cả — tính năng trông như bị hỏng.
     */
    public function created(Restaurant $restaurant): void
    {
        try {
            ApprovalPolicyDefaults::applyTo((int) $restaurant->id);
        } catch (\Throwable $e) {
            // Không chặn việc tạo nhà hàng nếu bước này lỗi; Chủ vẫn có thể nạp
            // lại từ màn hình Thẩm quyền phê duyệt.
            Log::warning('Không nạp được chính sách phê duyệt mặc định', [
                'restaurant_id' => $restaurant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
