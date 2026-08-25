<?php

namespace App\Services;

use App\Jobs\FetchAiFraudAlertsJob;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    /** Ngưỡng % giảm giá trên một hóa đơn bị coi là bất thường. */
    public const DISCOUNT_RATIO_THRESHOLD = 20;

    /** Số lần áp mã liên tiếp trong 15 phút bị coi là bất thường. */
    public const DISCOUNT_BURST_COUNT = 3;

    // ── Summary (fast COUNT queries for KPI cards) ────────────────────────────

    public function getSummary(int $restaurantId, string $periodStart, string $periodEnd): array
    {
        $startDateTime = $periodStart.' 00:00:00';
        $endDateTime = $periodEnd.' 23:59:59';

        $cashCount = DB::selectOne(
            'SELECT COUNT(DISTINCT cashier_user_id) AS cnt
             FROM shift_closings
             WHERE restaurant_id = ? AND closing_date BETWEEN ? AND ? AND cash_difference < -50000',
            [$restaurantId, $periodStart, $periodEnd]
        );

        $discountCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT DATE(created_at)
                FROM orders
                WHERE restaurant_id = ? AND status = ? AND created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                HAVING SUM(discount_amount)/NULLIF(SUM(subtotal),0)*100 > 20
                    OR SUM(discount_amount) > 2000000
             ) AS t',
            [$restaurantId, 'completed', $startDateTime, $endDateTime]
        );

        // The returns first row only — use subquery for correct count
        $cancelCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT cancelled_by
                FROM orders
                WHERE restaurant_id = ? AND status = ? AND cancelled_at BETWEEN ? AND ?
                GROUP BY cancelled_by
                HAVING COUNT(*) > 3 OR SUM(total_amount) > 1000000
             ) AS t',
            [$restaurantId, 'cancelled', $startDateTime, $endDateTime]
        );

        $wasteCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM (
                SELECT id FROM inventory_transactions
                WHERE restaurant_id = ? AND type = ? AND total_cost > 500000
                  AND occurred_at BETWEEN ? AND ?
             ) AS t',
            [$restaurantId, 'waste', $startDateTime, $endDateTime]
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
            ['confirmed', $restaurantId, 'daily', 'restaurant', $periodStart, $periodEnd]
        );

        $aiCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM audit_logs
             WHERE restaurant_id = ? AND action IN (\'order_split\', \'discount_applied\', \'price_modified\')
               AND created_at BETWEEN ? AND ?',
            [$restaurantId, $startDateTime, $endDateTime]
        );

        $auditCount = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM audit_logs
             WHERE restaurant_id = ? AND created_at BETWEEN ? AND ?',
            [$restaurantId, $startDateTime, $endDateTime]
        );

        return [
            'cash_shortfall_cashiers' => (int) ($cashCount->cnt ?? 0),
            'discount_flagged_days' => (int) ($discountCount->cnt ?? 0),
            'cancellation_cashiers' => (int) ($cancelCount->cnt ?? 0),
            'waste_flagged_entries' => (int) ($wasteCount->cnt ?? 0),
            'revenue_flagged_days' => (int) ($revenueCount->cnt ?? 0),
            'ai_flagged_alerts' => (int) ($aiCount->cnt ?? 0),
            'audit_logs_count' => (int) ($auditCount->cnt ?? 0),
        ];
    }

    /**
     * Thuật toán AI quét audit_logs phát hiện sửa giá nhiều lần và hủy món sau thanh toán.
     */
    public function detectAiFraudAlerts(int $restaurantId, string $periodStart, string $periodEnd): array
    {
        $cacheKey = "fraud_alerts:{$restaurantId}:{$periodStart}:{$periodEnd}";

        // 1. Read from Redis Cache
        if (Cache::has($cacheKey)) {
            return $this->mergePoDiscrepancies($restaurantId, $periodStart, $periodEnd, Cache::get($cacheKey));
        }

        // 2. Cache Miss: Dispatch background job to fetch fresh alerts asynchronously
        // We use a lock flag to avoid dispatching multiple duplicate jobs simultaneously
        $lockKey = "fraud_alerts_job_dispatched:{$restaurantId}";
        $breakerAvailable = app(CircuitBreaker::class)->for('analytics_service')->isAvailable();
        if ($breakerAvailable && ! Cache::has($lockKey)) {
            Cache::put($lockKey, true, 60); // 1 minute lock
            dispatch(new FetchAiFraudAlertsJob($restaurantId, $periodStart, $periodEnd));

            if (Cache::has($cacheKey)) {
                return $this->mergePoDiscrepancies($restaurantId, $periodStart, $periodEnd, Cache::get($cacheKey));
            }
        }

        // 3. Fallback PHP: Phân tích audit_logs thực tế — KHÔNG dùng dữ liệu giả
        $logs = AuditLog::where('restaurant_id', $restaurantId)
            ->whereIn('action', ['price_modified', 'discount_applied', 'order_cancelled', 'order_split'])
            ->with(['user'])
            ->latest('created_at')
            ->take(15)
            ->get();

        // Bắt đầu với mảng trống — không inject hardcoded fake alerts
        $alerts = [];

        // Cache đếm theo user để không bắn N+1 query.
        $burstCountsByUser = [];
        $cancelCountsByUser = [];

        $userIds = $logs->pluck('user_id')->filter()->unique();
        $employeesByUserId = Employee::withoutGlobalScopes()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        foreach ($logs->take(15) as $log) {
            $emp = $employeesByUserId->get($log->user_id);

            $empId = $emp ? $emp->id : null;
            $empName = $log->user?->name ?? 'Nhân viên';

            if ($log->action === 'price_modified') {
                // Điểm rủi ro cũ là hằng số 92.5 cho MỌI lần sửa giá — sửa 1.000đ
                // và sửa 1.000.000đ đều hiện như nhau. Giờ tính theo biên độ thật.
                // OrderService::log('price_modified') ghi cặp unit_price; các nguồn
                // khác dùng price/total_amount — đọc lần lượt cả ba.
                $before = (float) ($log->old_values['unit_price'] ?? $log->old_values['price'] ?? $log->old_values['total_amount'] ?? 0);
                $after = (float) ($log->new_values['unit_price'] ?? $log->new_values['price'] ?? $log->new_values['total_amount'] ?? 0);
                $delta = abs($before - $after);
                $deltaRatio = $before > 0 ? ($delta / $before) * 100 : 0.0;

                $alerts[] = [
                    'id' => 'ai-log-'.$log->id,
                    'employee_id' => $empId,
                    'employee_name' => $empName,
                    'violation_type' => 'AI: Sửa đổi giá món nhạy cảm',
                    'category' => 'price',
                    'domain' => 'promotion',
                    'severity' => $deltaRatio >= 30 ? 'high' : 'medium',
                    'description' => "Thay đổi giá trên đơn hàng #{$log->subject_id} bởi {$empName}: "
                        .number_format($before).'đ → '.number_format($after).'đ. [Nguồn: Laravel Fallback]',
                    'penalty_amount' => $delta,
                    'occurred_at' => $log->created_at->format('H:i d/m/Y'),
                    'risk_score' => min(99.0, round(40 + $deltaRatio, 1)),
                    'reason' => 'Biên độ thay đổi '.round($deltaRatio, 1).'% so với giá trị gốc của đơn.',
                ];
            } elseif ($log->action === 'order_cancelled') {
                $cancelValue = (float) ($log->old_values['total_amount'] ?? $log->new_values['total_amount'] ?? 0);
                $cancelCount = $cancelCountsByUser[$log->user_id] ??= AuditLog::where('restaurant_id', $restaurantId)
                    ->where('user_id', $log->user_id)
                    ->where('action', 'order_cancelled')
                    ->where('created_at', '>=', $log->created_at->copy()->subHours(2))
                    ->where('created_at', '<=', $log->created_at)
                    ->count();

                $alerts[] = [
                    'id' => 'ai-log-'.$log->id,
                    'employee_id' => $empId,
                    'employee_name' => $empName,
                    'violation_type' => 'AI: Hủy đơn nhạy cảm',
                    'category' => 'order_cancel',
                    'domain' => 'promotion',
                    'severity' => $cancelCount >= 3 ? 'critical' : 'high',
                    'description' => "Hủy đơn hàng #{$log->subject_id} sau khi đã chốt phục vụ bởi {$empName}"
                        .($cancelValue > 0 ? ' (giá trị '.number_format($cancelValue).'đ)' : '').'. [Nguồn: Laravel Fallback]',
                    'penalty_amount' => $cancelValue,
                    'occurred_at' => $log->created_at->format('H:i d/m/Y'),
                    'risk_score' => min(99.0, round(60 + ($cancelCount * 10), 1)),
                    'reason' => "Tài khoản này đã hủy {$cancelCount} đơn trong vòng 2 giờ quanh thời điểm ghi nhận.",
                ];
            } elseif ($log->action === 'order_split') {
                $alerts[] = [
                    'id' => 'ai-log-'.$log->id,
                    'employee_id' => $empId,
                    'employee_name' => $empName,
                    'violation_type' => 'AI: Tách bàn đáng ngờ',
                    'category' => 'order_split',
                    'domain' => 'promotion',
                    'severity' => 'high',
                    'description' => "Tách hóa đơn #{$log->subject_id} ra bàn trống bởi {$empName}. [Nguồn: Laravel Fallback]",
                    'penalty_amount' => (float) ($log->new_values['total_amount'] ?? 0),
                    'occurred_at' => $log->created_at->format('H:i d/m/Y'),
                    'risk_score' => 96.5,
                    'reason' => 'Tách đơn ra bàn trống là hành vi luôn bị đánh dấu đỏ theo chính sách vận hành.',
                ];
            } elseif ($log->action === 'discount_applied') {
                // Chỉ gắn cờ khi THỰC SỰ bất thường. Trước đây mọi lần áp mã hợp lệ
                // đều bị đẩy vào danh sách "vi phạm nhạy cảm" → báo động giả 100%,
                // khiến Owner mất niềm tin vào toàn bộ tab kiểm toán.
                $discount = (float) ($log->new_values['discount_amount'] ?? 0);
                $beforeTotal = (float) ($log->old_values['total_amount'] ?? 0);
                $ratio = $beforeTotal > 0 ? ($discount / $beforeTotal) * 100 : 0.0;

                $burstCount = $burstCountsByUser[$log->user_id] ??= AuditLog::where('restaurant_id', $restaurantId)
                    ->where('user_id', $log->user_id)
                    ->where('action', 'discount_applied')
                    ->where('created_at', '>=', $log->created_at->copy()->subMinutes(15))
                    ->where('created_at', '<=', $log->created_at)
                    ->count();

                $overThreshold = $ratio > self::DISCOUNT_RATIO_THRESHOLD;
                $isBurst = $burstCount >= self::DISCOUNT_BURST_COUNT;

                if ($overThreshold || $isBurst) {
                    $reasons = [];
                    if ($overThreshold) {
                        $reasons[] = 'mức giảm '.round($ratio, 1).'% vượt ngưỡng '.self::DISCOUNT_RATIO_THRESHOLD.'%';
                    }
                    if ($isBurst) {
                        $reasons[] = "áp mã {$burstCount} lần trong 15 phút (ngưỡng ".self::DISCOUNT_BURST_COUNT.' lần)';
                    }

                    $alerts[] = [
                        'id' => 'ai-log-'.$log->id,
                        'employee_id' => $empId,
                        'employee_name' => $empName,
                        'violation_type' => 'AI: Áp mã giảm giá bất thường',
                        'category' => 'voucher',
                        'domain' => 'promotion',
                        'severity' => ($overThreshold && $isBurst) ? 'critical' : 'high',
                        'description' => 'Áp mã giảm giá '.number_format($discount)."đ cho đơn #{$log->subject_id} bởi {$empName}. [Nguồn: Laravel Fallback]",
                        'penalty_amount' => $discount,
                        'occurred_at' => $log->created_at->format('H:i d/m/Y'),
                        'risk_score' => min(99.0, round(50 + $ratio + ($burstCount * 5), 1)),
                        'reason' => 'Gắn cờ do '.implode(' và ', $reasons).'.',
                    ];
                }
            }
        }

        return $this->mergePoDiscrepancies($restaurantId, $periodStart, $periodEnd, $alerts);
    }

    private function mergePoDiscrepancies(int $restaurantId, string $periodStart, string $periodEnd, array $alerts): array
    {
        $poLogs = AuditLog::where('restaurant_id', $restaurantId)
            ->where('action', 'po_discrepancy')
            ->whereBetween('created_at', [$periodStart.' 00:00:00', $periodEnd.' 23:59:59'])
            ->latest('created_at')
            ->get();

        foreach ($poLogs as $log) {
            $alertId = 'po-discrepancy-'.$log->id;
            $exists = false;
            foreach ($alerts as $a) {
                if ($a['id'] === $alertId) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }

            $old = $log->old_values;
            $new = $log->new_values;
            $poNumber = $new['po_number'] ?? 'PO';
            $supplierName = $new['supplier_name'] ?? 'Nhà cung cấp';

            $desc = "Phát hiện chênh lệch dòng tiền/số lượng khi giao nhận đơn hàng PO #{$poNumber} từ nhà cung cấp {$supplierName}. ";
            $desc .= 'Giá niêm yết: '.number_format($old['price'] ?? 0).'đ vs Giá hóa đơn: '.number_format($new['price'] ?? 0).'đ. ';
            $desc .= 'Số lượng đặt: '.($old['qty'] ?? 0).' vs Số lượng giao nhận: '.($new['qty'] ?? 0).'. Giao dịch đã bị ĐÓNG BĂNG.';

            $alerts[] = [
                'id' => $alertId,
                'employee_id' => null,
                'employee_name' => $log->user?->name ?? 'Nhân viên kho',
                'violation_type' => 'AI: Đối soát mua hàng thất bại',
                'category' => 'purchasing',
                // Cảnh báo này thuộc module Kho & Mua hàng; trang Khuyến mãi
                // dùng cờ này để tách khỏi danh sách vi phạm bán hàng.
                'domain' => 'purchasing',
                'severity' => 'critical',
                'description' => $desc.' [Nguồn: Laravel Fallback]',
                'penalty_amount' => abs((float) ($new['total_cost'] ?? 0) - (float) ($old['total_cost'] ?? 0)),
                'occurred_at' => $log->created_at->format('H:i d/m/Y'),
                'risk_score' => 99.5,
                'reason' => 'Thuật toán đối chiếu chéo 3 bên phát hiện sai lệch dòng tiền/số lượng giữa niêm yết hệ thống, hóa đơn số và kiểm đếm kho thực tế.',
            ];
        }

        return $alerts;
    }

    /**
     * Lấy toàn bộ danh sách Nhật ký kiểm toán phân trang cho Quản lý.
     */
    public function getAuditLogs(int $restaurantId): array
    {
        $logs = AuditLog::where('restaurant_id', $restaurantId)
            ->with(['user'])
            ->latest('created_at')
            ->take(100)
            ->get();

        return $this->buildAuditLogPayload($restaurantId, $logs);
    }

    /**
     * Nhật ký áp mã giảm giá.
     *
     * Trước đây trang Khuyến mãi lấy 100 log gần nhất của MỌI action rồi mới lọc
     * discount_applied ở PHP — nhà hàng đông khách thì 100 log đó không còn dòng
     * voucher nào, bảng hiện "không có giao dịch" dù thực tế có hàng chục. Ở đây
     * lọc ngay trong SQL.
     *
     * @param  list<string>  $actions
     */
    public function getVoucherAuditLogs(int $restaurantId, int $limit = 100, array $actions = ['discount_applied', 'discount_applied_bypass']): array
    {
        $logs = AuditLog::where('restaurant_id', $restaurantId)
            ->whereIn('action', $actions)
            ->with(['user'])
            ->latest('created_at')
            ->take($limit)
            ->get();

        return $this->buildAuditLogPayload($restaurantId, $logs);
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     */
    private function buildAuditLogPayload(int $restaurantId, $logs): array
    {
        $assignments = collect();
        if ($logs->isNotEmpty()) {
            $timestamps = $logs->pluck('created_at');
            $minDate = $timestamps->min()->copy()->subDay()->toDateString();
            $maxDate = $timestamps->max()->copy()->addDay()->toDateString();

            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                ->whereBetween('scheduled_date', [$minDate, $maxDate])
                ->with(['employee', 'shift'])
                ->get();
        }

        $rows = $logs->map(fn ($log) => [
            'id' => $log->id,
            'user_name' => $log->user?->name ?? 'Hệ thống',
            'user_role' => $log->user_role ?? 'staff',
            'event' => $log->event,
            'action' => $log->action,
            'action_label' => $this->getActionLabel($log->action),
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : '—',
            'subject_id' => $log->subject_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address ?? '127.0.0.1',
            'user_agent' => $log->user_agent ? substr($log->user_agent, 0, 80).'...' : '—',
            'created_at' => $log->created_at->format('H:i:s d/m/Y'),
            'scheduled_employee' => ScheduleAssignment::findEmployeeOnShiftAtFromCollection($assignments, $log->created_at)?->full_name ?? 'Không xếp ca',
        ]);

        return [
            'logs' => $rows,
            'count' => $rows->count(),
        ];
    }

    private function getActionLabel(string $action): string
    {
        return match ($action) {
            'order_cancelled' => 'Hủy hóa đơn',
            'order_split' => 'Tách bàn',
            'order_split_override' => 'Chủ quán duyệt đơn tách',
            'price_modified' => 'Sửa giá món',
            'discount_applied' => 'Áp mã giảm giá',
            'discount_applied_bypass' => 'Áp mã giảm giá (vượt quyền)',
            'discount_stacked_high' => 'Cộng dồn giảm giá vượt ngưỡng',
            'order_updated' => 'Sửa đổi đơn hàng',
            'toggle_account_status' => 'Khóa/Mở tài khoản',
            'reset_password' => 'Reset mật khẩu',
            'disable_2fa' => 'Tắt 2FA',
            default => $action,
        };
    }

    // ── 1. Cash Shortfalls ────────────────────────────────────────────────────

    public function detectCashShortfalls(int $restaurantId, string $periodStart, string $periodEnd): array
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
            [$restaurantId, $periodStart, $periodEnd]
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
                [$restaurantId, $row->cashier_user_id, $periodStart, $periodEnd]
            );

            $abs = abs((float) $row->total_difference);
            $severity = match (true) {
                $abs >= 500000 => 'high',
                $abs >= 200000 => 'medium',
                default => 'low',
            };

            $cashiers[] = [
                'cashier_user_id' => $row->cashier_user_id,
                'cashier_name' => $row->cashier_name,
                'shortfall_count' => (int) $row->shortfall_count,
                'total_difference' => (float) $row->total_difference,
                'severity' => $severity,
                'closings' => array_map(fn ($c) => [
                    'id' => $c->id,
                    'closing_date' => $c->closing_date,
                    'shift_name' => $c->shift_name ?? '—',
                    'expected_cash' => (float) $c->expected_cash,
                    'actual_cash' => (float) $c->actual_cash,
                    'cash_difference' => (float) $c->cash_difference,
                    'status' => $c->status,
                ], $closings),
            ];
        }

        return [
            'flagged_count' => count($cashiers),
            'total_shortage' => (float) array_sum(array_column($cashiers, 'total_difference')),
            'cashiers' => $cashiers,
        ];
    }

    // ── 2. Discount Anomalies ─────────────────────────────────────────────────

    public function detectDiscountAnomalies(int $restaurantId, string $periodStart, string $periodEnd): array
    {
        $startDateTime = $periodStart.' 00:00:00';
        $endDateTime = $periodEnd.' 23:59:59';

        $flaggedDays = DB::select(
            'SELECT DATE(o.created_at) AS order_date,
                    COUNT(*) AS order_count,
                    SUM(o.subtotal) AS gross_revenue,
                    SUM(o.discount_amount) AS discount_total,
                    SUM(o.discount_amount)/NULLIF(SUM(o.subtotal),0)*100 AS discount_rate_pct
             FROM orders o
             WHERE o.restaurant_id = ? AND o.status = ?
               AND o.created_at BETWEEN ? AND ?
             GROUP BY DATE(o.created_at)
             HAVING (SUM(o.discount_amount)/NULLIF(SUM(o.subtotal),0)*100) > 20
                 OR SUM(o.discount_amount) > 2000000
             ORDER BY discount_rate_pct DESC',
            [$restaurantId, 'completed', $startDateTime, $endDateTime]
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
               AND o.created_at BETWEEN ? AND ?
             ORDER BY (o.discount_amount/o.subtotal) DESC
             LIMIT 200',
            [$restaurantId, 'completed', $startDateTime, $endDateTime]
        );

        // Group suspicious orders by date
        $ordersByDate = [];
        foreach ($suspiciousOrders as $o) {
            $ordersByDate[$o->order_date][] = [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'cashier_name' => $o->cashier_name ?? '—',
                'subtotal' => (float) $o->subtotal,
                'discount_amount' => (float) $o->discount_amount,
                'discount_rate_pct' => round((float) $o->discount_rate_pct, 1),
                'total_amount' => (float) $o->total_amount,
            ];
        }

        $days = [];
        $totalExcess = 0.0;
        foreach ($flaggedDays as $day) {
            $rate = (float) $day->discount_rate_pct;
            $total = (float) $day->discount_total;
            $byRate = $rate > 20;
            $byAmt = $total > 2000000;
            $reason = match (true) {
                $byRate && $byAmt => 'both',
                $byRate => 'rate',
                default => 'amount',
            };
            $totalExcess += $total;
            $days[] = [
                'order_date' => $day->order_date,
                'order_count' => (int) $day->order_count,
                'gross_revenue' => (float) $day->gross_revenue,
                'discount_total' => $total,
                'discount_rate_pct' => round($rate, 1),
                'flag_reason' => $reason,
                'orders' => $ordersByDate[$day->order_date] ?? [],
            ];
        }

        return [
            'flagged_days' => count($days),
            'total_excess_discount' => $totalExcess,
            'days' => $days,
        ];
    }

    // ── 3. Suspicious Cancellations ───────────────────────────────────────────

    public function detectSuspiciousCancellations(int $restaurantId, string $periodStart, string $periodEnd): array
    {
        $startDateTime = $periodStart.' 00:00:00';
        $endDateTime = $periodEnd.' 23:59:59';

        $cashierRows = DB::select(
            'SELECT o.cancelled_by, u.name AS cashier_name,
                    COUNT(*) AS cancelled_count,
                    SUM(o.total_amount) AS total_value_cancelled
             FROM orders o
             LEFT JOIN users u ON u.id = o.cancelled_by
             WHERE o.restaurant_id = ? AND o.status = ?
               AND o.cancelled_at BETWEEN ? AND ?
             GROUP BY o.cancelled_by, u.name
             HAVING COUNT(*) > 3 OR SUM(o.total_amount) > 1000000
             ORDER BY total_value_cancelled DESC',
            [$restaurantId, 'cancelled', $startDateTime, $endDateTime]
        );

        $highValueRows = DB::select(
            'SELECT o.id, o.order_number, o.total_amount,
                    DATE(o.cancelled_at) AS cancelled_date,
                    u.name AS cancelled_by_name, o.cancelled_by
             FROM orders o
             LEFT JOIN users u ON u.id = o.cancelled_by
             WHERE o.restaurant_id = ? AND o.status = ?
               AND o.total_amount > 500000
               AND o.cancelled_at BETWEEN ? AND ?
             ORDER BY o.total_amount DESC
             LIMIT 100',
            [$restaurantId, 'cancelled', $startDateTime, $endDateTime]
        );

        $flaggedCashierIds = array_column($cashierRows, 'cancelled_by');

        // Group high-value orders by cashier
        $ordersByCashier = [];
        $standalone = [];
        foreach ($highValueRows as $o) {
            $row = [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total_amount' => (float) $o->total_amount,
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
                $count > 3 => 'count',
                default => 'value',
            };
            $cashiers[] = [
                'cancelled_by' => $row->cancelled_by,
                'cashier_name' => $row->cashier_name ?? '—',
                'cancelled_count' => $count,
                'total_value_cancelled' => $value,
                'flag_reason' => $reason,
                'orders' => $ordersByCashier[$row->cancelled_by] ?? [],
            ];
        }

        return [
            'flagged_cashiers_count' => count($cashiers),
            'total_cancelled_value' => (float) array_sum(array_column($cashiers, 'total_value_cancelled')),
            'cashiers' => $cashiers,
            'standalone_high_value' => $standalone,
        ];
    }

    // ── 4. Inventory Waste Spikes ─────────────────────────────────────────────

    public function detectInventoryWasteSpikes(int $restaurantId, string $periodStart, string $periodEnd): array
    {
        $startDateTime = $periodStart.' 00:00:00';
        $endDateTime = $periodEnd.' 23:59:59';

        $topEmployees = DB::select(
            'SELECT it.performed_by, u.name AS employee_name,
                    COUNT(*) AS waste_entry_count,
                    SUM(it.total_cost) AS total_waste_cost
             FROM inventory_transactions it
             LEFT JOIN users u ON u.id = it.performed_by
             WHERE it.restaurant_id = ? AND it.type = ?
               AND it.occurred_at BETWEEN ? AND ?
             GROUP BY it.performed_by, u.name
             ORDER BY total_waste_cost DESC',
            [$restaurantId, 'waste', $startDateTime, $endDateTime]
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
               AND it.occurred_at BETWEEN ? AND ?
             ORDER BY it.total_cost DESC
             LIMIT 200',
            [$restaurantId, 'waste', $startDateTime, $endDateTime]
        );

        $flaggedDays = DB::select(
            'SELECT DATE(occurred_at) AS waste_date,
                     SUM(total_cost) AS daily_waste_cost,
                     COUNT(*) AS entry_count
             FROM inventory_transactions
             WHERE restaurant_id = ? AND type = ?
               AND occurred_at BETWEEN ? AND ?
             GROUP BY DATE(occurred_at)
             HAVING SUM(total_cost) > 1000000
             ORDER BY daily_waste_cost DESC',
            [$restaurantId, 'waste', $startDateTime, $endDateTime]
        );

        $assignments = collect();
        if (! empty($largeEntries)) {
            $occurredTimes = collect($largeEntries)->pluck('occurred_at');
            $minDate = Carbon::parse($occurredTimes->min())->copy()->subDay()->toDateString();
            $maxDate = Carbon::parse($occurredTimes->max())->copy()->addDay()->toDateString();

            $assignments = ScheduleAssignment::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['scheduled', 'checked_in', 'completed'])
                ->whereBetween('scheduled_date', [$minDate, $maxDate])
                ->with(['employee', 'shift'])
                ->get();
        }

        return [
            'total_waste_cost' => (float) array_sum(array_column($topEmployees, 'total_waste_cost')),
            'flagged_entries_count' => count($largeEntries),
            'flagged_days_count' => count($flaggedDays),
            'top_employees' => array_map(fn ($r) => [
                'performed_by' => $r->performed_by,
                'employee_name' => $r->employee_name ?? '—',
                'waste_entry_count' => (int) $r->waste_entry_count,
                'total_waste_cost' => (float) $r->total_waste_cost,
            ], $topEmployees),
            'large_entries' => array_map(fn ($r) => [
                'id' => $r->id,
                'employee_name' => $r->employee_name ?? '—',
                'scheduled_employee' => ScheduleAssignment::findEmployeeOnShiftAtFromCollection($assignments, $r->occurred_at)?->full_name ?? 'Không xếp ca',
                'ingredient_name' => $r->ingredient_name,
                'quantity' => (float) $r->quantity,
                'unit_cost' => (float) $r->unit_cost,
                'total_cost' => (float) $r->total_cost,
                'occurred_at' => $r->occurred_at,
                'notes' => $r->notes,
            ], $largeEntries),
            'flagged_days' => array_map(fn ($r) => [
                'waste_date' => $r->waste_date,
                'daily_waste_cost' => (float) $r->daily_waste_cost,
                'entry_count' => (int) $r->entry_count,
            ], $flaggedDays),
        ];
    }

    // ── 5. Revenue Discrepancies ──────────────────────────────────────────────

    public function detectRevenueDiscrepancies(int $restaurantId, string $periodStart, string $periodEnd): array
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
            ['confirmed', $restaurantId, 'daily', 'restaurant', $periodStart, $periodEnd]
        );

        $maxPct = 0.0;
        $days = [];
        foreach ($rows as $row) {
            $pct = round((float) $row->difference_pct, 1);
            if ($pct > $maxPct) {
                $maxPct = $pct;
            }
            $days[] = [
                'summary_date' => $row->summary_date,
                'summary_net_revenue' => (float) $row->summary_net_revenue,
                'summary_gross_revenue' => (float) $row->summary_gross_revenue,
                'shift_total' => (float) $row->shift_total,
                'difference' => (float) $row->difference,
                'difference_pct' => $pct,
                'severity' => $pct >= 15 ? 'danger' : 'warning',
            ];
        }

        return [
            'flagged_days_count' => count($days),
            'max_discrepancy_pct' => $maxPct,
            'days' => $days,
        ];
    }
}
