<?php

/**
 * Đăng ký các bảng đã/cần partition theo tháng (MySQL RANGE partitioning) —
 * dùng chung bởi migration ban đầu (xem database/migrations/analytics/*_partition_log_tables.php)
 * và app/Console/Commands/ManagePartitions.php (đảm bảo luôn có sẵn partition
 * tương lai + dọn dẹp partition quá hạn retention).
 *
 * 'type' quyết định cú pháp DDL:
 *   - 'timestamp': cột kiểu TIMESTAMP → PARTITION BY RANGE (UNIX_TIMESTAMP(col))
 *     (giống audit_logs/orders_archive đã làm sẵn từ trước).
 *   - 'datetime': cột kiểu DATETIME/DATE → PHẢI dùng RANGE COLUMNS(col), vì
 *     UNIX_TIMESTAMP() chỉ hợp lệ làm partition expression trên cột TIMESTAMP.
 *
 * 'retention_months' => null nghĩa là GIỮ VĨNH VIỄN (không tự động DROP
 * partition cũ) — dùng cho dữ liệu có ràng buộc pháp lý/kế toán (tiền mặt,
 * hoa hồng, điểm thưởng khách hàng), theo đúng tinh thần
 * PurgeOldOrderArchives đã cố tình loại trừ bảng payments.
 *
 * KHÔNG có 'orders' hay 'salaries' trong danh sách này — cả 2 đều có bảng
 * khác giữ khoá ngoại TRỎ VÀO chúng (orders: 12 bảng, salaries: salary_adjustments)
 * nên không partition được (InnoDB cấm FK cùng lúc với partition). Quyết định
 * giữ nguyên FK, không partition orders — xem docs plan.
 */
return [
    // Số tháng partition xây sẵn khi migration chạy lần đầu.
    'months_back' => (int) env('PARTITION_MONTHS_BACK', 24),
    'months_forward' => (int) env('PARTITION_MONTHS_FORWARD', 6),

    'tables' => [
        'audit_logs' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 24],
        'orders_archive' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 36],
        'order_items_archive' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 36],
        'order_related_archives' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 36],

        // Bảng log tăng trưởng vô hạn — trước đây KHÔNG partition, mọi truy vấn
        // theo khoảng ngày quét toàn bảng dù chỉ cần 1 tháng dữ liệu.
        'search_query_logs' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 6],
        'notifications' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 6],
        'customer_behavior_logs' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 12],
        'webhook_deliveries' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => 3],
        'commission_logs' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => null], // tài chính, giữ vĩnh viễn
        'loyalty_transactions' => ['column' => 'created_at', 'type' => 'timestamp', 'retention_months' => null], // nghĩa vụ điểm thưởng khách hàng
        'cash_transactions' => ['column' => 'occurred_at', 'type' => 'datetime', 'retention_months' => null], // lưu trữ thuế 7 năm, giữ vĩnh viễn
        'inventory_transactions' => ['column' => 'occurred_at', 'type' => 'datetime', 'retention_months' => null],
    ],
];
