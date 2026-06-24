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
        });
    }
}
