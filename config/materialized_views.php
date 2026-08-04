<?php

use App\Support\MaterializedViews\Builders\CashFlowChartBuilder;
use App\Support\MaterializedViews\Builders\CdpRfmBuilder;
use App\Support\MaterializedViews\Builders\EmployeePortalBuilder;
use App\Support\MaterializedViews\Builders\GeoAnalyticsBuilder;
use App\Support\MaterializedViews\Builders\RevenueDailyBuilder;
use App\Support\MaterializedViews\Stores\JsonRollupStore;
use App\Support\MaterializedViews\Stores\RevenueSummaryStore;

/**
 * Registry của các "Materialized View" trong hệ thống (MySQL không có MV gốc
 * như Postgres — đây là bảng tổng hợp + refresh theo lịch, tổng quát hoá pattern
 * đã có sẵn ở app/Services/DailyReportService.php + bảng restaurant_revenue_summaries).
 *
 * Mỗi view khai báo tần suất refresh RIÊNG (cron string) — đúng yêu cầu "tần suất
 * refresh cấu hình theo từng chức năng, cái nào cần dữ liệu mới thường xuyên thì
 * refresh nhanh hơn, cái nào ít thay đổi thì refresh thưa hơn". Có thể override
 * qua .env mà không cần deploy lại code.
 *
 * Xem app/Support/MaterializedViews/MaterializedViewRegistry.php để biết cách đọc,
 * app/Services/MaterializedViewRefresher.php để biết cách refresh.
 */
return [
    'defaults' => [
        'queue' => 'low',
        'chunk' => 50,     // số nhà hàng active xử lý mỗi batch (khớp DailyReportService)
        'lock_seconds' => 600,    // ShouldBeUnique TTL — phải NHỎ HƠN khoảng cách cron
        'tries' => 3,
        'timeout' => 300,
        'stale_after' => 3600,   // sau bao nhiêu giây thì coi dòng tổng hợp là "cũ", kích hoạt refresh nền + fallback live
    ],

    'views' => [
        'revenue_daily' => [
            'builder' => RevenueDailyBuilder::class,
            'store' => RevenueSummaryStore::class,
            'summary_type' => 'daily',
            // Tần suất NHANH: doanh thu đổi theo từng đơn hàng hoàn tất, người dùng
            // kỳ vọng số liệu gần như tức thời trên dashboard.
            'cron' => env('MV_REVENUE_DAILY_CRON', '*/15 * * * *'),
            'between' => ['06:00', '23:59'],
            'stale_after' => 900,
            'branch_scoped' => true,
            'enabled' => (bool) env('MV_REVENUE_DAILY_ENABLED', true),
        ],

        'cash_flow_30d' => [
            'builder' => CashFlowChartBuilder::class,
            'store' => JsonRollupStore::class,
            // Tần suất TRUNG BÌNH: biểu đồ 30 ngày + trung bình ngày không đổi
            // trong vài phút, nhưng CashFlowController trước đây KHÔNG cache gì
            // cả — đây là trang nặng nhất chưa được tối ưu, ưu tiên số 1 của đợt MV này.
            'cron' => env('MV_CASH_FLOW_30D_CRON', '*/30 * * * *'),
            'stale_after' => 1800,
            'branch_scoped' => true,
            'enabled' => (bool) env('MV_CASH_FLOW_30D_ENABLED', true),
        ],

        'cdp_rfm' => [
            'builder' => CdpRfmBuilder::class,
            'store' => JsonRollupStore::class,
            // Tần suất CHẬM: RFM là chỉ số vòng đời khách hàng, chuẩn ngành là
            // refresh hàng ngày. Từng chạy đồng bộ (đồng bộ toàn bộ khách chưa
            // tính RFM) ngay trên request tải trang CdpController::index() — xem
            // CdpRfmBuilder để biết vì sao an toàn khi chuyển ra job nền.
            'cron' => env('MV_CDP_RFM_CRON', '30 3 * * *'),
            'stale_after' => 129600, // 36h — đủ dung sai nếu job chạy trễ 1 đêm
            'branch_scoped' => false,
            'enabled' => (bool) env('MV_CDP_RFM_ENABLED', true),
        ],

        'geo_analytics' => [
            'builder' => GeoAnalyticsBuilder::class,
            'store' => JsonRollupStore::class,
            // Tần suất CHẬM NHẤT: địa lý khách hàng đổi theo tuần, không theo giờ.
            // zoneStats/topAreas/channels/branchSuggestions trước đây HOÀN TOÀN
            // KHÔNG cache — xem GeoAnalyticsBuilder về giới hạn phạm vi days=30.
            'cron' => env('MV_GEO_ANALYTICS_CRON', '0 4 * * *'),
            'stale_after' => 129600,
            'branch_scoped' => false,
            'enabled' => (bool) env('MV_GEO_ANALYTICS_ENABLED', true),
        ],

        'employee_portal' => [
            'builder' => EmployeePortalBuilder::class,
            'store' => JsonRollupStore::class,
            // Lịch, KPI và lương cơ bản thay đổi ít; thông báo vẫn được đọc live.
            'cron' => env('MV_EMPLOYEE_PORTAL_CRON', '*/15 * * * *'),
            'stale_after' => 1800,
            'branch_scoped' => true,
            'enabled' => (bool) env('MV_EMPLOYEE_PORTAL_ENABLED', true),
        ],
    ],
];
