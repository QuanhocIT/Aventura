<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationReport extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected static function booted(): void
    {
        $lockCheck = function (self $model) {
            $date = $model->occurred_at instanceof Carbon
                ? $model->occurred_at->toDateString()
                : Carbon::parse($model->occurred_at)->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, $model->employee_id, $date)) {
                throw new \Exception('Dữ liệu vi phạm đã bị khóa do bảng lương của kỳ này đã được phê duyệt.');
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'penalty_amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'appealed_at' => 'datetime',
            'appeal_reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** Cửa sổ cho phép kháng cáo sau khi biên bản được xử lý (ngày). */
    public const APPEAL_WINDOW_DAYS = 7;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function appealReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appeal_reviewed_by');
    }

    /**
     * Biên bản có được kháng cáo không: đã xử lý (resolved) và có phạt tiền,
     * chưa từng kháng cáo, còn trong cửa sổ APPEAL_WINDOW_DAYS.
     */
    public function isAppealable(): bool
    {
        return $this->status === 'resolved'
            && (float) $this->penalty_amount > 0
            && $this->appeal_status === 'none'
            && $this->updated_at?->greaterThanOrEqualTo(now()->subDays(self::APPEAL_WINDOW_DAYS));
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
