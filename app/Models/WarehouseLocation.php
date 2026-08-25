<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseLocation extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'warehouse_locations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_cold_storage' => 'boolean',
            'is_quarantine' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
