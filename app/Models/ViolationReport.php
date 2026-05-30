<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
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
            $date = $model->occurred_at instanceof \Carbon\Carbon
                ? $model->occurred_at->toDateString()
                : \Carbon\Carbon::parse($model->occurred_at)->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, $model->employee_id, $date)) {
                throw new \Exception("Dữ liệu vi phạm đã bị khóa do bảng lương của kỳ này đã được phê duyệt.");
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);
    }

    protected function casts(): array
    {
        return [
            'occurred_at'    => 'datetime',
            'penalty_amount' => 'decimal:2',
            'is_anonymous'   => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class, 'reference_id')
            ->where('reference_type', self::class);
    }
}
