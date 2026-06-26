<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class OperationPolicy extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'staff_view_revenue' => 'boolean',
            'staff_view_salary' => 'boolean',
            'staff_view_cost_price' => 'boolean',
            'manager_view_other_salary' => 'boolean',
            'restrict_to_shift_hours' => 'boolean',
            'audit_all_changes' => 'boolean',
            'max_discount_percent_staff' => 'decimal:2',
            'max_discount_percent_manager' => 'decimal:2',
            'max_cancel_amount_staff' => 'decimal:2',
            'max_cancel_amount_manager' => 'decimal:2',
        ];
    }
}
