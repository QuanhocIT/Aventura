<?php

namespace App\Support\MaterializedViews\Builders;

use App\Models\CashTransaction;
use App\Support\MaterializedViews\Contracts\MaterializedViewBuilder;
use Carbon\CarbonInterface;

/**
 * Materialize phần "chậm đổi" của CashFlowController: biểu đồ thu/chi 30 ngày
 * + trung bình ngày (avg_daily_in/out dùng cho dự báo 7 ngày tới). Đây là
 * phần nặng nhất trên trang Cash Flow (quét 30 ngày CashTransaction MỖI LẦN
 * tải trang, hoàn toàn KHÔNG cache trước khi có MV này).
 *
 * CỐ Ý KHÔNG materialize current_cash/projected_balance/status/message —
 * những giá trị đó phụ thuộc két tiền ĐANG MỞ (activeRegister), đổi theo từng
 * giao dịch trong ca. Nếu cache chung cả cụm 30 phút, thu ngân có thể thấy
 * "current_cash" cũ tới 30 phút — sai lệch thật, không chỉ là chậm hiển thị.
 * CashFlowController tự tính phần live này tại request-time (rẻ: chỉ 2 SUM
 * trên đúng 1 cash_register_id), kết hợp với avg_daily_in/out materialized ở
 * đây để ra forecast đầy đủ. Xem CashFlowController::calculateCashForecast().
 */
class CashFlowChartBuilder implements MaterializedViewBuilder
{
    public function scopeKey(?int $branchId): string
    {
        return $branchId ? "branch:{$branchId}" : 'restaurant';
    }

    public function build(int $restaurantId, ?int $branchId, CarbonInterface $date): array
    {
        $thirtyDaysAgo = $date->copy()->subDays(29)->startOfDay();

        $dailyCashFlow = CashTransaction::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(occurred_at) as date, type, SUM(amount) as total')
            ->groupBy('date', 'type')
            ->get();

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $date->copy()->subDays($i);
            $dateStr = $day->toDateString();
            $label = $day->format('d/m');
            $in = (float) $dailyCashFlow->where('date', $dateStr)->where('type', 'in')->sum('total');
            $out = (float) $dailyCashFlow->where('date', $dateStr)->where('type', 'out')->sum('total');
            $chartData[] = [
                'date' => $label,
                'in' => $in,
                'out' => $out,
                'net' => $in - $out,
            ];
        }

        $totalIn = (float) $dailyCashFlow->where('type', 'in')->sum('total');
        $totalOut = (float) $dailyCashFlow->where('type', 'out')->sum('total');

        return [
            'chart_data' => $chartData,
            'total_in_30d' => $totalIn,
            'total_out_30d' => $totalOut,
            'avg_daily_in' => $totalIn / 30,
            'avg_daily_out' => $totalOut / 30,
        ];
    }
}
