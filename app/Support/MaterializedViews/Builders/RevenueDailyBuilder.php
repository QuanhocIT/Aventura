<?php

namespace App\Support\MaterializedViews\Builders;

use App\Services\DailyReportService;
use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use App\Support\Tenant\TenantContext;
use Carbon\CarbonInterface;

/**
 * Cố ý KHÔNG viết lại logic tính doanh thu — delegate toàn bộ cho
 * DailyReportService::computeForRestaurant() (nguồn duy nhất tính đúng doanh
 * thu/COGS/top sản phẩm/ca làm) để tránh lệch số liệu giữa 2 nơi.
 *
 * Dùng computeForRestaurant() (tính thuần tuý, KHÔNG tự ghi DB) chứ không phải
 * generateForRestaurant() (bản public cũ, tự ghi DB bên trong) — nếu dùng bản tự
 * ghi, MaterializedViewRefresher sẽ ghi đè thêm 1 lần nữa qua
 * RevenueSummaryStore::upsert() ngay sau đó, tưởng vô hại nhưng thực tế 2 lần
 * updateOrCreate() liên tiếp trên cùng khoá unique có thể ném
 * UniqueConstraintViolationException (updateOrCreate không phải 1 câu UPSERT
 * nguyên tử) — đã bắt được lỗi này qua test tích hợp, xem
 * tests/Feature/MaterializedViewFrameworkTest.php.
 *
 * Hỗ trợ cả dòng tổng hợp toàn chuỗi và dòng theo từng chi nhánh. Null chỉ được
 * dùng cho dòng tổng hợp toàn chuỗi; dòng chi nhánh dùng scope_key branch:{id}.
 */
class RevenueDailyBuilder implements MaterializedViewBuilder
{
    public function __construct(
        private readonly DailyReportService $dailyReportService,
    ) {}

    public function scopeKey(?int $branchId): string
    {
        return TenantContext::summaryScopeKey($branchId);
    }

    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array
    {
        return $this->dailyReportService->computeForRestaurant($restaurantId, $date->toDateString(), $branchId);
    }
}
