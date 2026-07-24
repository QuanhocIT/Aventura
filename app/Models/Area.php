<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    protected static function booted(): void
    {
        static::saved(function ($area) {
            \Illuminate\Support\Facades\Cache::forget("restaurant_{$area->restaurant_id}_areas");
            \Illuminate\Support\Facades\Cache::forget("quota_summary:{$area->restaurant_id}");
        });
        static::deleted(function ($area) {
            \Illuminate\Support\Facades\Cache::forget("restaurant_{$area->restaurant_id}_areas");
            \Illuminate\Support\Facades\Cache::forget("quota_summary:{$area->restaurant_id}");
        });
    }

    protected static function newFactory(): Factory
    {
        return AreaFactory::new();
    }
}

