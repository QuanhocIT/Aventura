<?php

namespace App\Support\MaterializedViews\Stores;

use App\Models\RestaurantRevenueSummary;
use App\Support\MaterializedViews\Contracts\RollupStore;
use App\Support\MaterializedViews\MaterializedView;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Lưu vào cùng schema mà DailyReportService::upsertSummary() dùng
 * (app/Services/DailyReportService.php) để mọi view "dạng số tiền vô hướng theo
 * ngày/scope" (revenue_daily, và các view money-shaped khác ở Phase 3 như
 * owner_summary) dùng chung 1 bảng restaurant_revenue_summaries đã có sẵn — đúng
 * cột scope_key/summary_type/summary_date.
 *
 * Payload đầu vào PHẢI có đúng các khoá mà DailyReportService::computeForRestaurant()
 * trả về (gross_revenue, net_revenue, cogs_amount, top_products, peak_hour...).
 */
class RevenueSummaryStore implements RollupStore
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
            'order_count' => $payload['order_count'],
            'completed_order_count' => $payload['completed_count'],
            'cancelled_order_count' => $payload['cancelled_count'],
            'gross_revenue' => $payload['gross_revenue'],
            'discount_total' => $payload['discount_total'],
            'service_charge_total' => $payload['service_charge'],
            'tax_total' => $payload['tax_total'],
            'net_revenue' => $payload['net_revenue'],
            'cogs_amount' => $payload['cogs_amount'],
            'gross_profit' => $payload['gross_profit'],
            'cash_revenue' => $payload['cash_revenue'],
            'bank_transfer_revenue' => $payload['bank_transfer_revenue'],
            'card_revenue' => $payload['card_revenue'],
            'ewallet_revenue' => $payload['ewallet_revenue'],
            'mixed_revenue' => $payload['mixed_revenue'],
            'average_order_value' => $payload['average_order_value'],
            'first_order_at' => $payload['first_order_at'] ?? null,
            'last_order_at' => $payload['last_order_at'] ?? null,
            'calculated_at' => now(),
            'source' => 'system',
            'meta' => [
                'peak_hour' => $payload['peak_hour'] ?? null,
                'top_products' => array_slice($payload['top_products'] ?? [], 0, 5),
            ],
        ];

        // KHÔNG dùng updateOrCreate([..., 'summary_date' => $date->toDateString()], ...):
        // cột summary_date cast 'date' trên Model, Eloquent LUÔN serialize cast
        // date/datetime thành "Y-m-d H:i:s" khi ghi DB — nhưng where() tìm kiếm của
        // updateOrCreate() dùng chuỗi thô "Y-m-d" tôi truyền vào, KHÔNG qua cast.
        // MySQL (cột DATE thật) tự cắt phần giờ khi lưu nên 2 dạng chuỗi vẫn khớp —
        // che mất lỗi. SQLite (bộ test mặc định, phpunit.xml) lưu nguyên văn chuỗi đã
        // cast, không cắt — 2 lần gọi liên tiếp không bao giờ tìm thấy dòng cũ, dẫn tới
        // insert trùng khoá unique và ném UniqueConstraintViolationException. Dùng
        // whereDate() để tìm (đúng theo cách DailyReportService::getComparison() đã
        // làm ở chỗ tương tự) rồi tự update/create thủ công, tránh phụ thuộc vào việc
        // 2 bên "tình cờ" cùng định dạng.
        $existing = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('scope_key', $scopeKey)
            ->where('summary_type', $view->summaryType)
            ->whereDate('summary_date', $date->toDateString())
            ->first();

        if ($existing) {
            $existing->update($values);

            return;
        }

        RestaurantRevenueSummary::withoutGlobalScopes()->create($values + [
            'restaurant_id' => $restaurantId,
            'scope_key' => $scopeKey,
            'summary_type' => $view->summaryType,
            'summary_date' => $date->toDateString(),
        ]);
    }

    public function read(
        MaterializedView $view,
        int $restaurantId,
        ?int $branchId,
        string $scopeKey,
        CarbonInterface $date,
    ): ?array {
        $row = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('scope_key', $scopeKey)
            ->where('summary_type', $view->summaryType)
            ->whereDate('summary_date', $date->toDateString())
            ->first();

        if (! $row || ! $row->calculated_at) {
            return null;
        }

        return [
            'data' => $row->only([
                'order_count', 'completed_order_count', 'cancelled_order_count',
                'gross_revenue', 'discount_total', 'service_charge_total', 'tax_total',
                'net_revenue', 'cogs_amount', 'gross_profit', 'cash_revenue',
                'bank_transfer_revenue', 'card_revenue', 'ewallet_revenue', 'mixed_revenue',
                'average_order_value', 'first_order_at', 'last_order_at', 'meta',
            ]),
            'calculated_at' => $row->calculated_at,
        ];
    }

    /**
     * LƯU Ý: lọc theo summary_type — nếu Phase 3 thêm view thứ 2 dùng chung
     * RevenueSummaryStore VÀ chung summary_type (vd 2 view cùng 'daily' nhưng
     * khác scope_key cố định như 'owner_summary'), số liệu ở đây sẽ gộp chung cả
     * 2 view. Tại Phase 1 chỉ có revenue_daily nên chưa xảy ra; nếu thêm view
     * mới trùng summary_type, cân nhắc lọc thêm theo scope_key cố định của view đó.
     */
    public function countRows(MaterializedView $view): int
    {
        return RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('summary_type', $view->summaryType)
            ->count();
    }

    public function newestCalculatedAt(MaterializedView $view): ?CarbonInterface
    {
        // ->max() là aggregate của query builder — trả về string thô, KHÔNG đi qua
        // cast 'datetime' của Eloquent model, nên phải tự parse.
        $value = RestaurantRevenueSummary::withoutGlobalScopes()
            ->where('summary_type', $view->summaryType)
            ->max('calculated_at');

        return $value ? Carbon::parse($value) : null;
    }
}
