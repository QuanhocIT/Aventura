<?php

namespace App\Support\MaterializedViews\Contracts;

use Carbon\CarbonInterface;

/**
 * Tính toán dữ liệu "sống" cho 1 view — được gọi bởi MaterializedViewRefresher
 * (lúc refresh theo lịch) VÀ bởi MaterializedViewReader (lúc fallback live-query
 * khi dòng tổng hợp chưa có/đã cũ). Cùng 1 nguồn logic cho cả 2 đường, tránh lệch kết quả.
 */
interface MaterializedViewBuilder
{
    /**
     * Khoá phạm vi dữ liệu: 'restaurant' cho tổng nhà hàng, "branch:{id}" cho 1 chi nhánh.
     */
    public function scopeKey(?int $branchId): string;

    /**
     * @return array<string, mixed> payload — hình dạng tuỳ thuộc RollupStore đích
     *                              (cột thật cho RevenueSummaryStore, mảng JSON tự do cho JsonRollupStore).
     */
    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array;
}
