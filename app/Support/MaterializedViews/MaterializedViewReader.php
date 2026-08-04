<?php

namespace App\Support\MaterializedViews;

use App\Jobs\RefreshMaterializedViewJob;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Đường đọc chung cho mọi materialized view: đọc dòng đã tổng hợp nếu còn "tươi"
 * (trong ngưỡng stale_after của view); nếu chưa có hoặc đã cũ thì (a) tự dispatch
 * job refresh nền để tự hồi phục lần sau, và (b) trả kết quả live-query ngay lập
 * tức từ chính builder — TRANG KHÔNG BAO GIỜ BỊ HỎNG hay chờ job chạy xong, kể cả
 * khi tắt hẳn refresh theo lịch. Đây là thuộc tính an toàn khi rollout từng view:
 * bật/tắt 1 view trong config không thể làm hỏng trang đang dùng nó.
 */
class MaterializedViewReader
{
    public function __construct(
        private readonly MaterializedViewRegistry $registry,
    ) {}

    /** @return array<string, mixed> */
    public function read(
        string $viewName,
        int $restaurantId,
        ?int $branchId = null,
        ?CarbonInterface $date = null,
        bool $allowStale = false,
    ): array
    {
        $view = $this->registry->get($viewName);
        $date ??= Carbon::today();
        $builder = $view->builder();
        $scopeKey = $builder->scopeKey($branchId);

        if ($view->enabled) {
            $row = $view->store()->read($view, $restaurantId, $branchId, $scopeKey, $date);

            // absolute: true — Carbon 3.x diffInSeconds() mặc định TRẢ VỀ SỐ CÓ DẤU
            // (âm khi mốc so sánh ở quá khứ), khác Carbon 2.x. Không truyền tham số này
            // thì dòng dữ liệu cũ bao nhiêu cũng bị coi là "tươi" (âm luôn <= ngưỡng dương),
            // vô hiệu hoá toàn bộ cơ chế phát hiện dữ liệu cũ.
            if ($row && now()->diffInSeconds($row['calculated_at'], absolute: true) <= $view->staleAfter) {
                return $row['data'];
            }

            RefreshMaterializedViewJob::dispatch($viewName, $restaurantId, $date->toDateString());

            // Các dashboard có dữ liệu ít thay đổi có thể dùng snapshot cũ ngay
            // trong lúc job refresh chạy nền, tránh biến thời gian chờ thành thời
            // gian phản hồi của người dùng. Các view khác vẫn giữ fallback live mặc định.
            if ($allowStale && $row) {
                return $row['data'];
            }
        }

        return $builder->build($restaurantId, $branchId, $date);
    }
}
