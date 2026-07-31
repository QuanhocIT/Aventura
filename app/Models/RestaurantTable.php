<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use App\Support\Tenant\TenantContext;

class RestaurantTable extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('payment_status', 'unpaid');
    }

    protected static function booted(): void
    {
        static::saved(function ($table) {
            self::forgetScopedCaches($table);
            Cache::forget("quota_summary:{$table->restaurant_id}");
        });
        static::deleted(function ($table) {
            self::forgetScopedCaches($table);
            Cache::forget("quota_summary:{$table->restaurant_id}");
        });
    }

    private static function forgetScopedCaches(self $table): void
    {
        foreach (['tables', 'areas'] as $resource) {
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}");
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:all");
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:none");

            if ($table->branch_id) {
                Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:".TenantContext::branchScopeKey((int) $table->branch_id));
            }

            $originalBranchId = $table->getOriginal('branch_id');
            if ($originalBranchId && (int) $originalBranchId !== (int) $table->branch_id) {
                Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:".TenantContext::branchScopeKey((int) $originalBranchId));
            }
        }
    }

    protected static function newFactory(): Factory
    {
        return RestaurantTableFactory::new();
    }
}
