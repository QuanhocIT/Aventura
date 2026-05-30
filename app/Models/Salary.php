<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salary extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pay_period_start' => 'date',
            'pay_period_end'   => 'date',
            'paid_at'          => 'datetime',
            'base_salary'      => 'decimal:2',
            'bonus_amount'     => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_salary'       => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    /**
     * Kiểm tra xem một ngày cụ thể của nhân viên hoặc nhà hàng có bị khóa bảng lương hay chưa.
     */
    public static function isPeriodLocked(int $restaurantId, ?int $employeeId, string $date): bool
    {
        $targetDate = \Carbon\Carbon::parse($date)->toDateString();

        $query = self::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['approved', 'paid'])
            ->where('pay_period_start', '<=', $targetDate)
            ->where('pay_period_end', '>=', $targetDate);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        return $query->exists();
    }
}
