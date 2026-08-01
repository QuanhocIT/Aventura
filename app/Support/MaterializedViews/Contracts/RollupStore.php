<?php

namespace App\Support\MaterializedViews\Contracts;

use App\Support\MaterializedViews\MaterializedView;
use Carbon\CarbonInterface;

/**
 * Nơi lưu/đọc dữ liệu đã "materialize". 2 cách triển khai:
 *  - RevenueSummaryStore: bọc lại restaurant_revenue_summaries (dữ liệu dạng số
 *    tiền vô hướng theo ngày/scope — đúng hình dạng bảng đã có sẵn).
 *  - JsonRollupStore (Phase 3): bảng analytics_rollups mới, payload JSON tự do
 *    cho dữ liệu dạng chuỗi/mảng (biểu đồ, top-N, ma trận cohort...).
 */
interface RollupStore
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(
        MaterializedView $view,
        int $restaurantId,
        ?int $branchId,
        string $scopeKey,
        CarbonInterface $date,
        array $payload,
    ): void;

    /**
     * @return array{data: array<string, mixed>, calculated_at: CarbonInterface}|null
     */
    public function read(
        MaterializedView $view,
        int $restaurantId,
        ?int $branchId,
        string $scopeKey,
        CarbonInterface $date,
    ): ?array;

    /** Dùng bởi lệnh `mv:status` — tổng số dòng đã materialize cho view này. */
    public function countRows(MaterializedView $view): int;

    /** Dùng bởi lệnh `mv:status` — thời điểm refresh gần nhất trên toàn bộ view. */
    public function newestCalculatedAt(MaterializedView $view): ?CarbonInterface;
}
