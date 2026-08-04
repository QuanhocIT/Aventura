<?php

namespace App\Models\Concerns;

use App\Models\RestaurantBranch;
use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToRestaurant
{
    protected static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope('restaurant', function (Builder $builder): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null) {
                $model = $builder->getModel();
                $table = $model->getTable();

                if (method_exists($model, 'shouldIncludeSystemShared') && $model->shouldIncludeSystemShared()) {
                    $builder->where(function (Builder $query) use ($table, $restaurantId): void {
                        $query->whereNull($table.'.restaurant_id')
                            ->orWhere($table.'.restaurant_id', $restaurantId);
                    });
                } else {
                    $builder->where($table.'.restaurant_id', $restaurantId);
                }
            }
        });

        static::creating(function ($model): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null && empty($model->restaurant_id)) {
                $model->restaurant_id = $restaurantId;
            }

            // Every operational model that owns a branch_id receives the
            // active branch automatically. Controllers can still override it
            // explicitly for cross-branch operations such as transfers.
            if (
                Schema::hasColumn($model->getTable(), 'branch_id')
                && empty($model->branch_id)
                && app(TenantContext::class)->isBranchScoped()
            ) {
                $model->branch_id = app(TenantContext::class)->activeBranchId();
            }
        });
    }

    /**
     * Shared relation for branch-aware tenant models. Models that already
     * define a more specific branch relation keep their own implementation.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * Scope route-model-binding theo nhà hàng của người dùng đăng nhập.
     *
     * Lý do: middleware SetTenantContext (nguồn của global scope 'restaurant') chạy
     * SAU SubstituteBindings, nên global scope là no-op lúc bind → nếu chỉ dựa vào
     * nó, một owner có thể thao tác trên bản ghi của nhà hàng KHÁC qua {model} trên
     * URL (IDOR). Ở đây ta scope thủ công theo auth()->user() (auth đã chạy trước
     * binding). Super admin không bị scope; route công khai (guest) giữ nguyên.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?: $this->getRouteKeyName();
        $table = $this->getTable();
        $query = $this->newQuery()->where($table.'.'.$field, $value);

        $user = auth()->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        if ($user && $user->restaurant_id && ! $isSuperAdmin) {
            if (method_exists($this, 'shouldIncludeSystemShared') && $this->shouldIncludeSystemShared()) {
                $query->where(function ($q) use ($table, $user): void {
                    $q->whereNull($table.'.restaurant_id')
                        ->orWhere($table.'.restaurant_id', $user->restaurant_id);
                });
            } else {
                $query->where($table.'.restaurant_id', $user->restaurant_id);
            }
        }

        return $query->first();
    }
}
