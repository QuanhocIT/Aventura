<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class OvertimeHoliday extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'multiplier' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
