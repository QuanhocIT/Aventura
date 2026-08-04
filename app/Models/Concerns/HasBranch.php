<?php

namespace App\Models\Concerns;

use App\Models\RestaurantBranch;
use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait HasBranch
{
    protected static function bootHasBranch(): void
    {
        static::creating(function ($model): void {
            if (
                Schema::hasColumn($model->getTable(), 'branch_id')
                && empty($model->branch_id)
                && app(TenantContext::class)->isBranchScoped()
            ) {
                $model->branch_id = app(TenantContext::class)->activeBranchId();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
