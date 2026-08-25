<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quỹ lương THÁNG cho một chi nhánh, do CHỦ doanh nghiệp đặt. Tổng lương (quy đổi
 * tháng) của nhân viên đang hoạt động ở chi nhánh không được vượt quỹ này.
 */
class BranchPayrollBudget extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'branch_id', 'effective_month', 'budget_amount', 'notes', 'created_by',
    ];

    protected $casts = [
        'effective_month' => 'date',
        'budget_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
