<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStaffSupervisorHistory extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'warehouse_branch_id',
        'warehouse_staff_id',
        'supervisor_user_id',
        'assigned_by',
        'effective_from',
        'effective_to',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function warehouseBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'warehouse_branch_id');
    }

    public function warehouseStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_staff_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
