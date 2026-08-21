<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Lớp phân tích AI/decision-support dành cho Kho Tổng.
 *
 * Dữ liệu đầu vào đã được giới hạn bởi SupplyRequestAnalyticsService ở đúng
 * tenant và đúng Kho Tổng. Lớp này chỉ đánh giá, xếp ưu tiên và đưa khuyến
 * nghị; không tự thay đổi tồn kho, đơn cấp phát hay đơn mua hàng.
 */
class CentralWarehouseAiService
{
    /**
     * @param  array<string, mixed>  $warehouseProps
     * @return array<string, mixed>
     */
    public function analyze(array $warehouseProps): array
    {
        $supply = $warehouseProps['supplyAnalytics'] ?? [];
        $summary = $supply['summary'] ?? [];
        $inventory = $warehouseProps['inventorySummary'] ?? [];
        $receiving = $warehouseProps['receivingSummary'] ?? [];
        $kpi = $warehouseProps['centralWarehouseAnalytics'] ?? [];
        $recommendations = collect($supply['recommendations'] ?? []);
        $tasks = collect($warehouseProps['warehouseTasks'] ?? []);

        $signals = [];
        $score = 100;

        $addSignal = function (
            string $severity,
            string $title,
            string $evidence,
            string $advice,
            string $nextStep,
            string $metric,
            int|float|string $value,
            string $source,
        ) use (&$signals, &$score): void {
            $weights = [
                'critical' => 25,
                'high' => 18,
                'medium' => 10,
                'low' => 5,
            ];

            $score -= $weights[$severity] ?? 5;
            $signals[] = [
                'severity' => $severity,
                'title' => $title,
                'evidence' => $evidence,
                'advice' => $advice,
                'next_step' => $nextStep,
                'metric' => $metric,
                'value' => $value,
                'source' => $source,
            ];
        };

        $urgentRecommendations = (int) ($summary['urgent_recommendations'] ?? $recommendations->where('priority', 'urgent')->count());
        $lowStock = (int) ($inventory['low_stock_count'] ?? 0);
        $zeroStock = (int) ($inventory['zero_stock_count'] ?? 0);
        if ($urgentRecommendations > 0 || $zeroStock > 0) {
            $addSignal(
                $zeroStock > 0 ? 'critical' : 'high',
                'Nguy cơ thiếu hàng tại Kho Tổng',
                sprintf('%d mặt hàng dưới ngưỡng; %d mặt hàng đang bằng 0; %d mặt hàng được dự báo cần nhập gấp.', $lowStock, $zeroStock, $urgentRecommendations),
                'Ưu tiên đối chiếu tồn khả dụng sau khi trừ giữ chỗ, sau đó lập kế hoạch bổ sung theo mức độ ảnh hưởng đến các chi nhánh.',
                'Mở danh sách khuyến nghị tồn kho và xác nhận nhà cung cấp, lead time trước khi tạo đề nghị mua.',
                'urgent_recommendations',
                $urgentRecommendations,
                'inventory_forecast',
            );
        }

        $overdueRequests = (int) ($summary['overdue_requests'] ?? 0);
        $openRequests = (int) ($summary['open_requests'] ?? 0);
        if ($overdueRequests > 0) {
            $addSignal(
                $overdueRequests >= 3 ? 'critical' : 'high',
                'Đơn cấp phát có nguy cơ vi phạm SLA',
                sprintf('%d/%d đơn đang mở đã quá hạn giao dự kiến.', $overdueRequests, $openRequests),
                'Điều phối theo thứ tự quá hạn, mức độ thiếu hàng của chi nhánh và khả năng hoàn tất soạn hàng.',
                'Mở workspace đơn cấp phát, lọc các đơn quá hạn và ghi nhận nguyên nhân trước khi điều chỉnh cam kết.',
                'overdue_requests',
                $overdueRequests,
                'supply_request_sla',
            );
        }

        $disputed = (int) ($summary['disputed_requests'] ?? 0);
        $receivingDiscrepancies = (int) ($receiving['discrepancy_vouchers'] ?? 0);
        $discrepancyQuantity = (float) ($receiving['discrepancy_quantity'] ?? 0);
        if ($disputed > 0 || $receivingDiscrepancies > 0 || $discrepancyQuantity > 0) {
            $addSignal(
                ($disputed > 0 && $receivingDiscrepancies > 0) ? 'high' : 'medium',
                'Có chênh lệch cần xác minh',
                sprintf('%d đơn tranh chấp, %d phiếu nhận cần rà soát, tổng chênh lệch %.3f đơn vị.', $disputed, $receivingDiscrepancies, $discrepancyQuantity),
                'Tách riêng hàng thiếu, hàng hỏng và sai đơn vị tính; chỉ điều chỉnh tồn sau khi đủ bằng chứng và phê duyệt.',
                'Mở workspace tiếp nhận, kiểm tra ảnh/chữ ký/biên bản và phân công người xử lý từng ngoại lệ.',
                'discrepancies',
                $receivingDiscrepancies + $disputed,
                'receiving_control',
            );
        }

        $expiredBatches = (int) ($inventory['expired_batch_count'] ?? 0);
        $expiringSoon = (int) ($inventory['expiring_soon_count'] ?? 0);
        if ($expiredBatches > 0 || $expiringSoon > 0) {
            $addSignal(
                $expiredBatches > 0 ? 'critical' : 'medium',
                'Rủi ro hạn sử dụng và FEFO',
                sprintf('%d lô đã hết hạn, %d lô sẽ hết hạn trong thời gian cảnh báo.', $expiredBatches, $expiringSoon),
                'Khóa xuất nhầm lô, ưu tiên FEFO và cách ly lô hết hạn/thu hồi trước khi tiếp tục phân bổ.',
                'Mở tồn kho theo lô, lập danh sách xử lý và lưu bằng chứng tiêu hủy hoặc thu hồi nếu cần.',
                'expiry_batches',
                $expiredBatches + $expiringSoon,
                'fefo_monitoring',
            );
        }

        $overdueTasks = $tasks->filter(function ($task): bool {
            $status = (string) data_get($task, 'status', '');
            $dueAt = data_get($task, 'due_at');

            return ! in_array($status, ['completed', 'cancelled'], true)
                && $dueAt
                && Carbon::parse($dueAt)->isPast();
        })->count();
        if ($overdueTasks > 0) {
            $addSignal(
                $overdueTasks >= 3 ? 'high' : 'medium',
                'Năng lực xử lý nhiệm vụ kho đang bị nghẽn',
                sprintf('%d nhiệm vụ chưa hoàn tất đã quá hạn.', $overdueTasks),
                'Phân bổ lại nhiệm vụ theo mức độ ưu tiên và năng lực thực tế, không chỉ theo số lượng task đang mở.',
                'Mở bảng nhiệm vụ, phân công lại task quá hạn và ghi nhận lý do thiếu nhân sự hoặc thiếu hàng.',
                'overdue_tasks',
                $overdueTasks,
                'warehouse_workload',
            );
        }

        $fillRate = (float) ($summary['fill_rate_percent'] ?? 100);
        $otif = (float) ($kpi['otif_percent'] ?? 100);
        if ($fillRate < 95 || $otif < 95) {
            $addSignal(
                ($fillRate < 85 || $otif < 85) ? 'high' : 'medium',
                'Chất lượng phục vụ chi nhánh cần cải thiện',
                sprintf('Fill rate %.1f%% và OTIF %.1f%% trong kỳ đo hiện tại.', $fillRate, $otif),
                'Phân biệt nguyên nhân do thiếu tồn, chậm soạn, chậm bàn giao hay chi nhánh nhận thiếu để chọn biện pháp đúng.',
                'Đối chiếu các đơn không đạt, sau đó lập hành động khắc phục theo từng nguyên nhân thay vì tăng tồn kho đồng loạt.',
                'service_quality',
                round(min($fillRate, $otif), 1),
                'fulfillment_kpi',
            );
        }

        $trendLeader = $recommendations
            ->sortByDesc(fn (array $item): float => (float) ($item['trend_percent'] ?? 0))
            ->first();
        if ($trendLeader && (float) ($trendLeader['trend_percent'] ?? 0) >= 20) {
            $addSignal(
                'low',
                'Nhu cầu một số nguyên liệu đang tăng',
                sprintf('%s tăng %.1f%% so với giai đoạn trước.', $trendLeader['name'] ?? 'Một nguyên liệu', (float) $trendLeader['trend_percent']),
                'Kiểm tra lead time và giá nhập trước khi nhu cầu tăng làm giảm tồn an toàn.',
                'Theo dõi mặt hàng này trong kế hoạch bổ sung 7 ngày và xác nhận năng lực nhà cung cấp.',
                'demand_trend',
                round((float) $trendLeader['trend_percent'], 1),
                'demand_trend',
            );
        }

        usort($signals, static function (array $left, array $right): int {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return ($rank[$left['severity']] ?? 9) <=> ($rank[$right['severity']] ?? 9);
        });

        $score = max(0, min(100, $score));
        $level = match (true) {
            $score >= 85 => 'stable',
            $score >= 70 => 'watch',
            $score >= 50 => 'risk',
            default => 'critical',
        };

        $summaryText = match ($level) {
            'stable' => 'Kho Tổng đang vận hành ổn định; tiếp tục theo dõi các tín hiệu nhỏ trước khi chúng trở thành ngoại lệ.',
            'watch' => 'Kho Tổng có một số tín hiệu cần theo dõi; nên xử lý các khuyến nghị ưu tiên trong ngày.',
            'risk' => 'Kho Tổng đang có rủi ro vận hành đáng kể; cần lập kế hoạch khắc phục và người chịu trách nhiệm cụ thể.',
            default => 'Kho Tổng có tín hiệu khẩn cấp; cần ưu tiên xử lý các ngoại lệ ảnh hưởng trực tiếp đến khả năng cấp phát.',
        };

        $periodDays = (int) ($supply['period_days'] ?? 0);
        $confidence = $periodDays >= 28 && ($summary['last7_requests'] ?? 0) >= 3 ? 0.86 : ($periodDays >= 14 ? 0.7 : 0.52);

        return [
            'engine' => 'Aventura Warehouse AI v1',
            'mode' => 'hybrid_decision_support',
            'score' => $score,
            'level' => $level,
            'label' => match ($level) {
                'stable' => 'Ổn định',
                'watch' => 'Cần theo dõi',
                'risk' => 'Rủi ro cao',
                default => 'Khẩn cấp',
            },
            'summary' => $summaryText,
            'signals' => array_slice($signals, 0, 6),
            'signal_count' => count($signals),
            'confidence' => $confidence,
            'data_period_days' => $periodDays,
            'generated_at' => now()->toISOString(),
            'disclaimer' => 'Khuyến nghị được suy ra từ dữ liệu vận hành hiện có và cần Trưởng kho xác nhận trước khi thực hiện.',
        ];
    }
}
