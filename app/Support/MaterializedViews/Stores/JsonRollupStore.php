<?php

namespace App\Support\MaterializedViews\Stores;

use App\Models\AnalyticsRollup;
use App\Support\MaterializedViews\Contracts\RollupStore;
use App\Support\MaterializedViews\MaterializedView;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Lưu payload dạng JSON tự do (biểu đồ, top-N, ma trận...) vào bảng
 * analytics_rollups — dùng cho view KHÔNG hợp với hình dạng số tiền vô hướng
 * của RevenueSummaryStore/restaurant_revenue_summaries.
 *
 * `date` (period_start) là mốc "dòng này đại diện cho ngày nào", không phải
 * khoảng thời gian dữ liệu payload bao phủ — vd cash_flow_30d tính cửa sổ 30
 * ngày NHƯNG period_start vẫn là "hôm nay" (ngày tính), giống cách
 * summary_date hoạt động ở RevenueSummaryStore.
 */
class JsonRollupStore implements RollupStore
{
    public function upsert(
        MaterializedView $view,
        int $restaurantId,
        ?int $branchId,
        string $scopeKey,
        CarbonInterface $date,
        array $payload,
    ): void {
        $values = [
            'branch_id' => $branchId,
            'payload' => $payload,
            'calculated_at' => now(),
            'source' => 'system',
        ];

        // Cùng bẫy đã gặp ở RevenueSummaryStore (xem ghi chú ở đó): cột
        // period_start cast 'date' nên phải dùng whereDate() để tìm, không
        // dùng updateOrCreate() với chuỗi thô — khác định dạng lưu trữ giữa
        // SQLite/MySQL sẽ khiến không bao giờ tìm thấy dòng cũ.
        $existing = AnalyticsRollup::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('view_key', $view->name)
            ->where('scope_key', $scopeKey)
            ->whereDate('period_start', $date->toDateString())
            ->first();

        if ($existing) {
            $existing->update($values);

            return;
        }

        AnalyticsRollup::withoutGlobalScopes()->create($values + [
            'restaurant_id' => $restaurantId,
            'view_key' => $view->name,
            'scope_key' => $scopeKey,
            'period_start' => $date->toDateString(),
        ]);
    }

    public function read(
        MaterializedView $view,
        int $restaurantId,
        ?int $branchId,
        string $scopeKey,
        CarbonInterface $date,
    ): ?array {
        $row = AnalyticsRollup::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('view_key', $view->name)
            ->where('scope_key', $scopeKey)
            ->whereDate('period_start', $date->toDateString())
            ->first();

        if (! $row || ! $row->calculated_at) {
            return null;
        }

        return [
            'data' => $row->payload,
            'calculated_at' => $row->calculated_at,
        ];
    }

    public function countRows(MaterializedView $view): int
    {
        return AnalyticsRollup::withoutGlobalScopes()
            ->where('view_key', $view->name)
            ->count();
    }

    public function newestCalculatedAt(MaterializedView $view): ?CarbonInterface
    {
        $value = AnalyticsRollup::withoutGlobalScopes()
            ->where('view_key', $view->name)
            ->max('calculated_at');

        return $value ? Carbon::parse($value) : null;
    }
}
