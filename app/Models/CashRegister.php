<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'closing_date'             => 'date',
            'opening_balance'          => 'decimal:2',
            'closing_balance'          => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'expense_budget'           => 'decimal:2',
            'difference'               => 'decimal:2',
            'opened_at'                => 'datetime',
            'closed_at'                => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'cash_register_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
