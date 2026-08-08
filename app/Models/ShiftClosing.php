<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ShiftClosing extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected static function booted(): void
    {
        $lockCheck = function (self $model) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $model->restaurant_id)
                ->where('user_id', $model->cashier_user_id)
                ->first();

            $employeeId = $employee ? $employee->id : null;
            // Fix #15: Đảm bảo timezone nhất quán khi parse closing_date
            $date = $model->closing_date instanceof Carbon
                ? $model->closing_date->toDateString()
                : Carbon::parse($model->closing_date, config('app.timezone'))->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, $employeeId, $date)) {
                throw new \Exception('Dữ liệu chấm công chốt ca đã bị khóa do bảng lương của kỳ này đã được phê duyệt.');
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);
    }

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'period_start_at' => 'datetime',
            'closed_at' => 'datetime',
            'expected_cash' => 'decimal:2',
            'cash_sales_amount' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'transfer_amount' => 'decimal:2',
            'actual_transfer_amount' => 'decimal:2',
            'transfer_difference' => 'decimal:2',
            'gross_revenue_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_revenue_amount' => 'decimal:2',
            'total_difference' => 'decimal:2',
            'responsibility_amount' => 'decimal:2',
            'other_expense_amount' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function salaryAdjustments(): MorphMany
    {
        return $this->morphMany(SalaryAdjustment::class, 'reference');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }
}
