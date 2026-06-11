<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchQueryLog extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'float',
            'restaurant_id' => 'integer',
            'user_id' => 'integer',
        ];
    }
}