<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmployeeTrustScore;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/**
 * EmployeeTrustScoreService
 *
 * Tính toán và lưu trữ điểm tín nhiệm nhân viên (0–100) dựa trên
 * hành vi thực tế trong 30 ngày gần nhất.
 *
 * Ngưỡng điểm:
 *   ≥ 85 → Xuất sắc  (xanh lá)
 *   ≥ 70 → Tốt       (xanh dương)
 *   ≥ 55 → Cần chú ý (vàng) — tăng tần suất giám sát
 *   ≥ 40 → Nguy cơ cao (cam) — yêu cầu Manager Pin cho thao tác nhạy cảm
 *   < 40 → Nghiêm trọng (đỏ) — cần xem xét kỷ luật
 *
 * Hệ số trừ điểm:
 *   Âm két ca        : -15 / lần
 *   AI gắn cờ gian lận: -10 / lần
 *   Hủy đơn bất thường: -8 / lần
 *   Đăng nhập ngoài giờ: -20 / lần (nghiêm trọng nhất)
 *   Lạm dụng voucher   : -10 / lần
 */
class EmployeeTrustScoreService
{
    /** Số ngày nhìn lại để tính điểm */
    private const LOOKBACK_DAYS = 30;

    /** Ngưỡng phân loại điểm */
    public const THRESHOLDS = [
        'excellent' => 85,
        'good'      => 70,
        'warning'   => 55,
        'critical'  => 40,
    ];

    /** Hệ số trừ điểm theo từng loại vi phạm */
    public const PENALTIES = [
        'cash_shortfall'   => -15,
        'fraud_flag'       => -10,
        'suspicious_cancel' => -8,
        'offhour_login'    => -20,
        'voucher_abuse'    => -10,
    ];

    /**
     * Tính lại điểm tín nhiệm cho một nhân viên cụ thể.
     * Dùng updateOrCreate để an toàn khi chạy nhiều lần.
     */
    public function recalculate(int $employeeId, int $restaurantId): EmployeeTrustScore
    {
        $since = now()->subDays(self::LOOKBACK_DAYS);

        // 1. Âm két ca (cash_difference < -50,000 VNĐ)
        $cashShortfalls = DB::table('shift_closings')
            ->where('restaurant_id', $restaurantId)
            ->where('cashier_user_id', function ($q) use ($employeeId) {
                $q->select('user_id')
                  ->from('employees')
                  ->where('id', $employeeId)
                  ->limit(1);
            })
            ->where('cash_difference', '<', -50000)
            ->where('closing_date', '>=', $since->toDateString())
            ->count();

        // 2. AI/Audit gắn cờ gian lận
        $fraudFlags = AuditLog::whereHas('user', function ($q) use ($employeeId) {
                $q->whereHas('employee', fn($e) => $e->where('id', $employeeId));
            })
            ->where('restaurant_id', $restaurantId)
            ->whereIn('action', ['price_modified', 'discount_applied', 'order_split'])
            ->where('created_at', '>=', $since)
            ->count();

        // 3. Hủy đơn bất thường (> 3 lần hoặc tổng > 1 triệu)
        $cancelStats = DB::table('orders')
            ->join('employees', function ($join) use ($employeeId) {
                $join->on('orders.cancelled_by', '=', 'employees.user_id')
                     ->where('employees.id', $employeeId);
            })
            ->where('orders.restaurant_id', $restaurantId)
            ->where('orders.status', 'cancelled')
            ->where('orders.cancelled_at', '>=', $since)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $suspiciousCancels = 0;
        if ($cancelStats) {
            if ($cancelStats->count > 3) $suspiciousCancels += (int) floor($cancelStats->count / 3);
            if ($cancelStats->total > 1000000) $suspiciousCancels += 1;
        }

        // 4. Đăng nhập ngoài giờ ca (audit_log action 'off_hour_access')
        $offhourLogins = AuditLog::whereHas('user', function ($q) use ($employeeId) {
                $q->whereHas('employee', fn($e) => $e->where('id', $employeeId));
            })
            ->where('restaurant_id', $restaurantId)
            ->where('action', 'off_hour_access')
            ->where('created_at', '>=', $since)
            ->count();

        // ── Tính điểm ────────────────────────────────────────────────────────

        $penalties = [
            'cash_shortfall'    => $cashShortfalls   * self::PENALTIES['cash_shortfall'],
            'fraud_flags'       => $fraudFlags        * self::PENALTIES['fraud_flag'],
            'cancels'           => $suspiciousCancels * self::PENALTIES['suspicious_cancel'],
            'offhour_logins'    => $offhourLogins      * self::PENALTIES['offhour_login'],
        ];

        $score = 100 + array_sum($penalties);
        $score = (float) max(0, min(100, $score)); // Clamp [0, 100]

        $totalPenalty = array_sum(array_filter($penalties, fn($v) => $v < 0));

        return EmployeeTrustScore::updateOrCreate(
            ['employee_id' => $employeeId, 'restaurant_id' => $restaurantId],
            [
                'score'                 => $score,
                'score_breakdown'       => $penalties,
                'cash_shortfall_count'  => $cashShortfalls,
                'fraud_flag_count'      => $fraudFlags,
                'cancel_flag_count'     => $suspiciousCancels,
                'offhour_login_count'   => $offhourLogins,
                'total_penalty_amount'  => abs($totalPenalty),
                'last_calculated_at'    => today(),
            ]
        );
    }

    /**
     * Tính lại điểm cho toàn bộ nhân viên của một nhà hàng.
     */
    public function recalculateAll(int $restaurantId): array
    {
        $results = [];
        $employees = DB::table('employees')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($employees as $employeeId) {
            $score = $this->recalculate($employeeId, $restaurantId);
            $results[] = [
                'employee_id' => $employeeId,
                'score'       => $score->score,
                'level'       => $score->level['label'],
            ];
        }

        return $results;
    }

    /**
     * Lấy nhãn mức điểm để hiển thị.
     */
    public function getLevelLabel(float $score): string
    {
        return match (true) {
            $score >= self::THRESHOLDS['excellent'] => 'Xuất sắc',
            $score >= self::THRESHOLDS['good']      => 'Tốt',
            $score >= self::THRESHOLDS['warning']   => 'Cần chú ý',
            $score >= self::THRESHOLDS['critical']  => 'Nguy cơ cao',
            default                                  => 'Nghiêm trọng',
        };
    }

    /**
     * Kiểm tra có nên yêu cầu Manager Pin không.
     * Threshold: điểm < 40 (mức critical).
     */
    public function requiresManagerPin(int $employeeId, int $restaurantId): bool
    {
        $trustScore = EmployeeTrustScore::where('employee_id', $employeeId)
            ->where('restaurant_id', $restaurantId)
            ->value('score');

        return $trustScore !== null && $trustScore < self::THRESHOLDS['critical'];
    }
}
