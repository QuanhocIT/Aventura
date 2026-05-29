<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    public function __construct(
        private int $restaurantId,
        private string $periodStart,
        private string $periodEnd
    ) {}

    // ── Summary (fast COUNT queries for KPI cards) ────────────────────────────

    public function getSummary(): array
    {
        $cashCount = DB::selectOne(
            'SELECT COUNT(DISTINCT cashier_user_id) AS cnt
             FROM shift_closings
             WHERE restaurant_id = ? AND closing_date BETWEEN ? AND ? AND cash_difference < -50000',
            [$this->restaurantId, $this->periodStart, $this->periodEnd]
        );

        $discountCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT DATE(created_at)
                FROM orders
                WHERE restaurant_id = ? AND status = ? AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                HAVING SUM(discount_amount)/NULLIF(SUM(subtotal),0)*100 > 20
                    OR SUM(discount_amount) > 2000000
             ) AS t',
            [$this->restaurantId, 'completed', $this->periodStart, $this->periodEnd]
        );

        $cancelCount = DB::selectOne(
            'SELECT COUNT(DISTINCT cancelled_by) AS cnt
             FROM orders
             WHERE restaurant_id = ? AND status = ? AND DATE(cancelled_at) BETWEEN ? AND ?
             GROUP BY cancelled_by HAVING COUNT(*) > 3 OR SUM(total_amount) > 1000000',
            [$this->restaurantId, 'cancelled', $this->periodStart, $this->periodEnd]
        );
        // The above returns first row only — use subquery for correct count
        $cancelCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT cancelled_by
                FROM orders
                WHERE restaurant_id = ? AND status = ? AND DATE(cancelled_at) BETWEEN ? AND ?
                GROUP BY cancelled_by
                HAVING COUNT(*) > 3 OR SUM(total_amount) > 1000000
             ) AS t',
            [$this->restaurantId, 'cancelled', $this->periodStart, $this->periodEnd]
        );

        $wasteCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT id FROM inventory_transactions
                WHERE restaurant_id = ? AND type = ? AND total_cost > 500000
                  AND DATE(occurred_at) BETWEEN ? AND ?
             ) AS t',
            [$this->restaurantId, 'waste', $this->periodStart, $this->periodEnd]
        );

        $revenueCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT rrs.summary_date
                FROM restaurant_revenue_summaries rrs
                LEFT JOIN shift_closings sc
                    ON sc.restaurant_id = rrs.restaurant_id
                   AND sc.closing_date = rrs.summary_date AND sc.status = ?
                WHERE rrs.restaurant_id = ? AND rrs.summary_type = ?
                  AND rrs.scope_key = ? AND rrs.summary_date BETWEEN ? AND ?
                GROUP BY rrs.summary_date, rrs.net_revenue
                HAVING rrs.net_revenue > 0
                   AND ABS(rrs.net_revenue - COALESCE(SUM(sc.expected_cash + sc.transfer_amount),0))
                       / rrs.net_revenue * 100 > 5
             ) AS t',
            ['confirmed', $this->restaurantId, 'daily', 'restaurant', $this->periodStart, $this->periodEnd]
        );

        return [
            'cash_shortfall_cashiers' => (int) ($cashCount->cnt ?? 0),
            'discount_flagged_days'   => (int) ($discountCount->cnt ?? 0),
            'cancellation_cashiers'   => (int) ($cancelCount->cnt ?? 0),
            'waste_flagged_entries'   => (int) ($wasteCount->cnt ?? 0),
            'revenue_flagged_days'    => (int) ($revenueCount->cnt ?? 0),
        ];
    }

    // ── 1. Cash Shortfalls ────────────────────────────────────────────────────

    public function detectCashShortfalls(): array
    {
        $rows = DB::select(
            'SELECT sc.cashier_user_id, u.name AS cashier_name,
                    COUNT(*) AS shortfall_count,
                    SUM(sc.cash_difference) AS total_difference
             FROM shift_closings sc
             JOIN users u ON u.id = sc.cashier_user_id
             WHERE sc.restaurant_id = ? AND sc.closing_date BETWEEN ? AND ?
               AND sc.cash_difference < -50000
             GROUP BY sc.cashier_user_id, u.name
             ORDER BY total_difference ASC',
            [$this->restaurantId, $this->periodStart, $this->periodEnd]
        );

        $cashiers = [];
        foreach ($rows as $row) {
            $closings = DB::select(
                'SELECT sc.id, sc.closing_date, sc.cash_difference,
                        sc.expected_cash, sc.actual_cash, sc.status,
                        ws.name AS shift_name
                 FROM shift_closings sc
                 LEFT JOIN work_shifts ws ON ws.id = sc.shift_id
                 WHERE sc.restaurant_id = ? AND sc.cashier_user_id = ?
                   AND sc.closing_date BETWEEN ? AND ? AND sc.cash_difference < -50000
                 ORDER BY sc.closing_date DESC',
                [$this->restaurantId, $row->cashier_user_id, $this->periodStart, $this->periodEnd]
            );

            $abs = abs((float) $row->total_difference);
            $severity = match (true) {
                $abs >= 500000 => 'high',
                $abs >= 200000 => 'medium',
                default        => 'low',
            };

            $cashiers[] = [
                'cashier_user_id' => $row->cashier_user_id,
                'cashier_name'    => $row->cashier_name,
                'shortfall_count' => (int) $row->shortfall_count,
                'total_difference' => (float) $row->total_difference,
                'severity'        => $severity,
                'closings'        => array_map(fn ($c) => [
                    'id'              => $c->id,
                    'closing_date'    => $c->closing_date,
                    'shift_name'      => $c->shift_name ?? '—',
                    'expected_cash'   => (float) $c->expected_cash,
                    'actual_cash'     => (float) $c->actual_cash,
                    'cash_difference' => (float) $c->cash_difference,
                    'status'          => $c->status,
                ], $closings),
            ];
        }

        return [
            'flagged_count'  => count($cashiers),
            'total_shortage' => (float) array_sum(array_column($cashiers, 'total_difference')),
            'cashiers'       => $cashiers,
        ];
    }

    // ── 2. Discount Anomalies ─────────────────────────────────────────────────

    public function detectDiscountAnomalies(): array
    {
        $flaggedDays = DB::select(
            'SELECT DATE(o.created_at) AS order_date,
                    COUNT(*) AS order_count,
                    SUM(o.subtotal) AS gross_revenue,
                    SUM(o.discount_amount) AS discount_total,
                    SUM(o.discount_amount)/NULLIF(SUM(o.subtotal),0)*100 AS discount_rate_pct
             FROM orders o
             WHERE o.restaurant_id = ? AND o.status = ?
               AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY DATE(o.created_at)
             HAVING (SUM(o.discount_amount)/NULLIF(SUM(o.subtotal),0)*100) > 20
                 OR SUM(o.discount_amount) > 2000000
             ORDER BY discount_rate_pct DESC',
            [$this->restaurantId, 'completed', $this->periodStart, $this->periodEnd]
        );

        if (empty($flaggedDays)) {
            return ['flagged_days' => 0, 'total_excess_discount' => 0.0, 'days' => []];
        }

        $suspiciousOrders = DB::select(
            'SELECT o.id, o.order_number, DATE(o.created_at) AS order_date,
                    o.subtotal, o.discount_amount,
                    o.discount_amount/o.subtotal*100 AS discount_rate_pct,
                    o.total_amount, u.name AS cashier_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.cashier_user_id
             WHERE o.restaurant_id = ? AND o.status = ?
               AND o.discount_amount > 0 AND o.subtotal > 0
               AND (o.discount_amount/o.subtotal) > 0.15
               AND DATE(o.created_at) BETWEEN ? AND ?
             ORDER BY (o.discount_amount/o.subtotal) DESC
             LIMIT 200',
            [$this->restaurantId, 'completed', $this->periodStart, $this->periodEnd]
        );

        // Group suspicious orders by date
        $ordersByDate = [];
        foreach ($suspiciousOrders as $o) {
            $ordersByDate[$o->order_date][] = [
                'id'               => $o->id,
                'order_number'     => $o->order_number,
                'cashier_name'     => $o->cashier_name ?? '—',
                'subtotal'         => (float) $o->subtotal,
                'discount_amount'  => (float) $o->discount_amount,
                'discount_rate_pct' => round((float) $o->discount_rate_pct, 1),
                'total_amount'     => (float) $o->total_amount,
            ];
        }

        $days = [];
        $totalExcess = 0.0;
        foreach ($flaggedDays as $day) {
            $rate    = (float) $day->discount_rate_pct;
            $total   = (float) $day->discount_total;
            $byRate  = $rate > 20;
            $byAmt   = $total > 2000000;
            $reason  = match (true) {
                $byRate && $byAmt => 'both',
                $byRate           => 'rate',
                default           => 'amount',
            };
            $totalExcess += $total;
            $days[] = [
                'order_date'        => $day->order_date,
                'order_count'       => (int) $day->order_count,
                'gross_revenue'     => (float) $day->gross_revenue,
                'discount_total'    => $total,
                'discount_rate_pct' => round($rate, 1),
                'flag_reason'       => $reason,
                'orders'            => $ordersByDate[$day->order_date] ?? [],
            ];
        }

        return [
            'flagged_days'         => count($days),
            'total_excess_discount' => $totalExcess,
            'days'                 => $days,
        ];
    }

    // ── 3. Suspicious Cancellations ───────────────────────────────────────────

    public function detectSuspiciousCancellations(): array
    {
        $cashierRows = DB::select(
            'SELECT o.cancelled_by, u.name AS cashier_name,
                    COUNT(*) AS cancelled_count,
                    SUM(o.total_amount) AS total_value_cancelled
             FROM orders o
             LEFT JOIN users u ON u.id = o.cancelled_by
             WHERE o.restaurant_id = ? AND o.status = ?
               AND DATE(o.cancelled_at) BETWEEN ? AND ?
             GROUP BY o.cancelled_by, u.name
             HAVING COUNT(*) > 3 OR SUM(o.total_amount) > 1000000
             ORDER BY total_value_cancelled DESC',
            [$this->restaurantId, 'cancelled', $this->periodStart, $this->periodEnd]
        );

        $highValueRows = DB::select(
            'SELECT o.id, o.order_number, o.total_amount,
                    DATE(o.cancelled_at) AS cancelled_date,
                    u.name AS cancelled_by_name, o.cancelled_by
             FROM orders o
             LEFT JOIN users u ON u.id = o.cancelled_by
             WHERE o.restaurant_id = ? AND o.status = ?
               AND o.total_amount > 500000
               AND DATE(o.cancelled_at) BETWEEN ? AND ?
             ORDER BY o.total_amount DESC
             LIMIT 100',
            [$this->restaurantId, 'cancelled', $this->periodStart, $this->periodEnd]
        );

        $flaggedCashierIds = array_column($cashierRows, 'cancelled_by');

        // Group high-value orders by cashier
        $ordersByCashier = [];
        $standalone = [];
        foreach ($highValueRows as $o) {
            $row = [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'total_amount'   => (float) $o->total_amount,
                'cancelled_date' => $o->cancelled_date,
            ];
            if (in_array($o->cancelled_by, $flaggedCashierIds)) {
                $ordersByCashier[$o->cancelled_by][] = $row;
            } else {
                $standalone[] = array_merge($row, ['cancelled_by_name' => $o->cancelled_by_name ?? '—']);
            }
        }

        $cashiers = [];
        foreach ($cashierRows as $row) {
            $count = (int) $row->cancelled_count;
            $value = (float) $row->total_value_cancelled;
            $reason = match (true) {
                $count > 3 && $value > 1000000 => 'both',
                $count > 3                      => 'count',
                default                         => 'value',
            };
            $cashiers[] = [
                'cancelled_by'          => $row->cancelled_by,
                'cashier_name'          => $row->cashier_name ?? '—',
                'cancelled_count'       => $count,
                'total_value_cancelled' => $value,
                'flag_reason'           => $reason,
                'orders'                => $ordersByCashier[$row->cancelled_by] ?? [],
            ];
        }

        return [
            'flagged_cashiers_count' => count($cashiers),
            'total_cancelled_value'  => (float) array_sum(array_column($cashiers, 'total_value_cancelled')),
            'cashiers'               => $cashiers,
            'standalone_high_value'  => $standalone,
        ];
    }

    // ── 4. Inventory Waste Spikes ─────────────────────────────────────────────

    public function detectInventoryWasteSpikes(): array
    {
        $topEmployees = DB::select(
            'SELECT it.performed_by, u.name AS employee_name,
                    COUNT(*) AS waste_entry_count,
                    SUM(it.total_cost) AS total_waste_cost
             FROM inventory_transactions it
             LEFT JOIN users u ON u.id = it.performed_by
             WHERE it.restaurant_id = ? AND it.type = ?
               AND DATE(it.occurred_at) BETWEEN ? AND ?
             GROUP BY it.performed_by, u.name
             ORDER BY total_waste_cost DESC',
            [$this->restaurantId, 'waste', $this->periodStart, $this->periodEnd]
        );

        $largeEntries = DB::select(
            'SELECT it.id, it.performed_by, u.name AS employee_name,
                    i.name AS ingredient_name,
                    it.quantity, it.unit_cost, it.total_cost,
                    it.occurred_at, it.notes
             FROM inventory_transactions it
             JOIN ingredients i ON i.id = it.ingredient_id
             LEFT JOIN users u ON u.id = it.performed_by
             WHERE it.restaurant_id = ? AND it.type = ?
               AND it.total_cost > 500000
               AND DATE(it.occurred_at) BETWEEN ? AND ?
             ORDER BY it.total_cost DESC
             LIMIT 200',
            [$this->restaurantId, 'waste', $this->periodStart, $this->periodEnd]
        );

        $flaggedDays = DB::select(
            'SELECT DATE(occurred_at) AS waste_date,
                    SUM(total_cost) AS daily_waste_cost,
                    COUNT(*) AS entry_count
             FROM inventory_transactions
             WHERE restaurant_id = ? AND type = ?
               AND DATE(occurred_at) BETWEEN ? AND ?
             GROUP BY DATE(occurred_at)
             HAVING SUM(total_cost) > 1000000
             ORDER BY daily_waste_cost DESC',
            [$this->restaurantId, 'waste', $this->periodStart, $this->periodEnd]
        );

        return [
            'total_waste_cost'      => (float) array_sum(array_column($topEmployees, 'total_waste_cost')),
            'flagged_entries_count' => count($largeEntries),
            'flagged_days_count'    => count($flaggedDays),
            'top_employees'         => array_map(fn ($r) => [
                'performed_by'    => $r->performed_by,
                'employee_name'   => $r->employee_name ?? '—',
                'waste_entry_count' => (int) $r->waste_entry_count,
                'total_waste_cost'  => (float) $r->total_waste_cost,
            ], $topEmployees),
            'large_entries'  => array_map(fn ($r) => [
                'id'              => $r->id,
                'employee_name'   => $r->employee_name ?? '—',
                'ingredient_name' => $r->ingredient_name,
                'quantity'        => (float) $r->quantity,
                'unit_cost'       => (float) $r->unit_cost,
                'total_cost'      => (float) $r->total_cost,
                'occurred_at'     => $r->occurred_at,
                'notes'           => $r->notes,
            ], $largeEntries),
            'flagged_days'   => array_map(fn ($r) => [
                'waste_date'       => $r->waste_date,
                'daily_waste_cost' => (float) $r->daily_waste_cost,
                'entry_count'      => (int) $r->entry_count,
            ], $flaggedDays),
        ];
    }

    // ── 5. Revenue Discrepancies ──────────────────────────────────────────────

    public function detectRevenueDiscrepancies(): array
    {
        $rows = DB::select(
            'SELECT rrs.summary_date,
                    rrs.net_revenue AS summary_net_revenue,
                    rrs.gross_revenue AS summary_gross_revenue,
                    COALESCE(SUM(sc.expected_cash + sc.transfer_amount), 0) AS shift_total,
                    rrs.net_revenue - COALESCE(SUM(sc.expected_cash + sc.transfer_amount), 0) AS difference,
                    CASE WHEN rrs.net_revenue > 0
                         THEN ABS(rrs.net_revenue - COALESCE(SUM(sc.expected_cash + sc.transfer_amount), 0))
                              / rrs.net_revenue * 100
                         ELSE 0 END AS difference_pct
             FROM restaurant_revenue_summaries rrs
             LEFT JOIN shift_closings sc
                 ON sc.restaurant_id = rrs.restaurant_id
                AND sc.closing_date = rrs.summary_date
                AND sc.status = ?
             WHERE rrs.restaurant_id = ? AND rrs.summary_type = ?
               AND rrs.scope_key = ? AND rrs.summary_date BETWEEN ? AND ?
             GROUP BY rrs.summary_date, rrs.net_revenue, rrs.gross_revenue
             HAVING difference_pct > 5
             ORDER BY difference_pct DESC',
            ['confirmed', $this->restaurantId, 'daily', 'restaurant', $this->periodStart, $this->periodEnd]
        );

        $maxPct = 0.0;
        $days = [];
        foreach ($rows as $row) {
            $pct = round((float) $row->difference_pct, 1);
            if ($pct > $maxPct) {
                $maxPct = $pct;
            }
            $days[] = [
                'summary_date'        => $row->summary_date,
                'summary_net_revenue' => (float) $row->summary_net_revenue,
                'summary_gross_revenue' => (float) $row->summary_gross_revenue,
                'shift_total'         => (float) $row->shift_total,
                'difference'          => (float) $row->difference,
                'difference_pct'      => $pct,
                'severity'            => $pct >= 15 ? 'danger' : 'warning',
            ];
        }

        return [
            'flagged_days_count'    => count($days),
            'max_discrepancy_pct'   => $maxPct,
            'days'                  => $days,
        ];
    }
}
