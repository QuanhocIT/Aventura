<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ShiftClosing extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closing_date'         => 'date',
            'closed_at'            => 'datetime',
            'expected_cash'        => 'decimal:2',
            'actual_cash'          => 'decimal:2',
            'cash_difference'      => 'decimal:2',
            'transfer_amount'      => 'decimal:2',
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

    public function salaryAdjustments(): MorphMany
    {
        return $this->morphMany(SalaryAdjustment::class, 'reference');
    }
}
