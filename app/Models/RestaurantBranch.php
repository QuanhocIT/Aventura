<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\RestaurantBranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantBranch extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        // Xóa cache branches trong Inertia share khi branch thay đổi
        $invalidate = function (self $branch) {
            \Illuminate\Support\Facades\Cache::forget("tenant_branches:{$branch->restaurant_id}");
        };

        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'branch_id');
    }

    protected static function newFactory(): Factory
    {
        return RestaurantBranchFactory::new();
    }
}

