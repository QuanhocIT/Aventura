<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantDailyCheck extends Model
{
    use BelongsToRestaurant;

    protected $table = 'restaurant_daily_checks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_date' => 'date',
            'orders_count' => 'integer',
            'dishes_prepared_count' => 'integer',
            'revenue' => 'decimal:2',
            'had_activity' => 'boolean',
            'is_flagged' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
