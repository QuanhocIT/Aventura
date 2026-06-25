<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantFollowup extends Model
{
    use BelongsToRestaurant;

    protected $fillable = ['restaurant_id', 'assigned_to', 'note', 'remind_at', 'status'];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
