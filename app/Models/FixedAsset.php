<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FixedAsset extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'in_service_date' => 'date',
            'disposed_at' => 'date',
            'cost' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'accumulated_depreciation' => 'decimal:2',
            'warranty_until' => 'date',
            'last_inspected_at' => 'datetime',
            'disposal_proceeds' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class, 'fixed_asset_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(FixedAssetHandover::class, 'fixed_asset_id');
    }

    public function latestHandover(): HasOne
    {
        return $this->hasOne(FixedAssetHandover::class, 'fixed_asset_id')->latestOfMany();
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(FixedAssetInspection::class, 'fixed_asset_id');
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(FixedAssetInspection::class, 'fixed_asset_id')->latestOfMany('inspected_at');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'account_payable_id');
    }
}
