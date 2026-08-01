<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Support\Tenant\TenantContext;
use Database\Factories\Restaurant\AreaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

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
            self::forgetScopedCaches($area);
            Cache::forget("quota_summary:{$area->restaurant_id}");
        });
        static::deleted(function ($area) {
            self::forgetScopedCaches($area);
            Cache::forget("quota_summary:{$area->restaurant_id}");
        });
    }

    private static function forgetScopedCaches(self $area): void
    {
        Cache::forget("restaurant_{$area->restaurant_id}_areas");
        Cache::forget("restaurant_{$area->restaurant_id}_areas:scope:all");
        Cache::forget("restaurant_{$area->restaurant_id}_areas:scope:none");

        if ($area->branch_id) {
            Cache::forget("restaurant_{$area->restaurant_id}_areas:scope:".TenantContext::branchScopeKey((int) $area->branch_id));
        }

        $originalBranchId = $area->getOriginal('branch_id');
        if ($originalBranchId && (int) $originalBranchId !== (int) $area->branch_id) {
            Cache::forget("restaurant_{$area->restaurant_id}_areas:scope:".TenantContext::branchScopeKey((int) $originalBranchId));
        }
    }

    protected static function newFactory(): Factory
    {
        return AreaFactory::new();
    }
}
