<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    const STATUS_DRAFT       = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    protected function casts(): array
    {
        return [
            'target_quantity'        => 'decimal:4',
            'actual_yield_quantity'  => 'decimal:4',
            'actual_wastage_quantity' => 'decimal:4',
            'actual_yield_percent'   => 'decimal:2',
            'production_date'        => 'date',
            'expiry_date'            => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function centralBom(): BelongsTo
    {
        return $this->belongsTo(CentralBom::class, 'central_bom_id');
    }

    public function outputIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'output_ingredient_id');
    }

    public function createdBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'created_batch_id');
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class, 'work_order_id');
    }
}
