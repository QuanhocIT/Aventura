<?php

namespace App\Models\Concerns;

use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToRestaurant
{
    protected static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope('restaurant', function (Builder $builder): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null) {
                $builder->where($builder->getModel()->getTable().'.restaurant_id', $restaurantId);
            }
        });

        static::creating(function ($model): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null && empty($model->restaurant_id)) {
                $model->restaurant_id = $restaurantId;
            }
        });
    }
}
