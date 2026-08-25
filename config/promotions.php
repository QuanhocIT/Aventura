<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trần tổng giảm giá trên một hóa đơn
    |--------------------------------------------------------------------------
    |
    | Voucher được cộng dồn với các khoản giảm giá đã có trên đơn (ưu đãi hạng
    | hội viên Vàng/Kim Cương ở OrdersController, quy đổi điểm tích lũy qua
    | LoyaltyService, giảm giá thủ công của quản lý). Trước đây tổng chỉ bị chặn
    | ở mức bằng subtotal — tức một hóa đơn hoàn toàn có thể về 0đ một cách
    | "hợp lệ" mà không ai được cảnh báo.
    |
    | max_percent: mức trần cứng. Voucher sẽ bị cắt bớt để tổng giảm giá không
    |              vượt ngưỡng này. Để 100 nghĩa là giữ nguyên hành vi cũ.
    | warn_percent: ngưỡng cảnh báo. Vượt mức này thì thu ngân vẫn áp được nhưng
    |              nhận thông báo, và hệ thống ghi audit log để đối soát.
    |
    */

    'max_total_discount_percent' => (float) env('PROMOTION_MAX_TOTAL_DISCOUNT_PERCENT', 100),

    'warn_total_discount_percent' => (float) env('PROMOTION_WARN_TOTAL_DISCOUNT_PERCENT', 50),

];
