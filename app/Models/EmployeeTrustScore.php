<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EmployeeTrustScore
 *
 * Lưu trữ điểm tín nhiệm tổng hợp (0–100) của từng nhân viên,
 * được tính lại định kỳ bởi RecalculateTrustScoresJob.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $restaurant_id
 * @property float $score
 * @property array|null $score_breakdown
 * @property int $cash_shortfall_count
 * @property int $fraud_flag_count
 * @property int $cancel_flag_count
 * @property int $offhour_login_count
 * @property int $voucher_abuse_count
 * @property float $total_penalty_amount
 * @property string|null $last_calculated_at
 */
class EmployeeTrustScore extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'employee_id',
        'restaurant_id',
        'score',
        'score_breakdown',
        'cash_shortfall_count',
        'fraud_flag_count',
        'cancel_flag_count',
        'offhour_login_count',
        'voucher_abuse_count',
        'total_penalty_amount',
        'last_calculated_at',
    ];

    protected $casts = [
        'score' => 'float',
        'score_breakdown' => 'array',
        'total_penalty_amount' => 'float',
        'last_calculated_at' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Trả về màu sắc và nhãn hiển thị theo mức điểm.
     */
    public function getLevelAttribute(): array
    {
        return match (true) {
            $this->score >= 85 => ['label' => 'Xuất sắc',   'color' => 'emerald'],
            $this->score >= 70 => ['label' => 'Tốt',         'color' => 'blue'],
            $this->score >= 55 => ['label' => 'Cần chú ý',  'color' => 'amber'],
            $this->score >= 40 => ['label' => 'Nguy cơ cao', 'color' => 'orange'],
            default => ['label' => 'Nghiêm trọng', 'color' => 'red'],
        };
    }

    /**
     * Trả về mức cảnh báo: normal / watch / danger
     */
    public function getAlertLevelAttribute(): string
    {
        return match (true) {
            $this->score >= 70 => 'normal',
            $this->score >= 40 => 'watch',
            default => 'danger',
        };
    }
}
