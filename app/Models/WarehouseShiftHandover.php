<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseShiftHandover extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'warehouse_shift_handovers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'starting_stock_value' => 'decimal:2',
            'ending_stock_value' => 'decimal:2',
            'signed_at' => 'datetime',
            'open_tasks_json' => 'array',
            'handover_items_json' => 'array',
            'incidents_json' => 'array',
            'stock_snapshot_json' => 'array',
            'is_system_locked' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function handoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
