<?php

namespace App\Support\MaterializedViews\Builders;

use App\Services\CdpService;
use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use Carbon\CarbonInterface;

/**
 * Delegate toàn bộ cho CdpService::getRfmMetrics() (nguồn duy nhất tính đúng
 * RFM/segment/CLV) để tránh lệch số liệu — giống cách RevenueDailyBuilder
 * delegate cho DailyReportService.
 *
 * getRfmMetrics() vốn TỰ kiểm tra và đồng bộ (chunk 100, tính lại RFM) cho mọi
 * khách hàng CHƯA từng được tính — đây là lưới an toàn hợp lý để chạy trong
 * JOB NỀN (refresh theo lịch), nhưng trước đây chạy đồng bộ ngay trên mỗi lần
 * tải trang CdpController::index(), chặn request nếu có khách mới chưa tính.
 * Với hầu hết trường hợp RFM từng khách ĐÃ được cập nhật phản ứng (reactive)
 * ngay khi đơn hàng hoàn tất — xem CdpService::calculateRfmForCustomer() được
 * gọi từ ProcessPostPaymentActions/OrderService/OrdersController — nên lưới an
 * toàn này hiếm khi cần chạy, nhưng khi cần thì giờ chạy nền, không chặn người dùng.
 *
 * Đánh đổi đã biết: 'recent_logs' (hoạt động gần đây) bên trong kết quả cũng bị
 * "đông cứng" theo lịch refresh hàng ngày thay vì luôn tức thời — chấp nhận được
 * vì RFM tổng thể vốn là chỉ số vòng đời khách hàng, không cần tức thời; nếu cần
 * hoạt động thời gian thực, xem trực tiếp customer_behavior_logs.
 */
class CdpRfmBuilder implements MaterializedViewBuilder
{
    public function scopeKey(?int $branchId): string
    {
        return 'restaurant';
    }

    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array
    {
        return CdpService::getRfmMetrics($restaurantId);
    }
}
