<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetInspection extends Model
{
    use BelongsToRestaurant;

    public const RESULT_PASS = 'pass';

    public const RESULT_NEEDS_ACTION = 'needs_action';

    public const RESULT_FAIL = 'fail';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'score' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(FixedAssetHandover::class, 'fixed_asset_handover_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
