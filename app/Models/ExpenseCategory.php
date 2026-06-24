<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    /**
     * Xác định đây là danh mục dùng chung hệ thống và riêng của nhà hàng.
     */
    public function shouldIncludeSystemShared(): bool
    {
        return true;
    }

    public function recurringExpenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class, 'category_id');
    }

    public function operatingExpenses(): HasMany
    {
        return $this->hasMany(OperatingExpense::class, 'category_id');
    }
}
