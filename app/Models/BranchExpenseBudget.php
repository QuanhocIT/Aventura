<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hạn mức chi tiêu tháng của một chi nhánh do Chủ đặt.
 */
class BranchExpenseBudget extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'effective_month' => 'date',
        'budget_amount' => 'decimal:2',
        'require_receipt' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
