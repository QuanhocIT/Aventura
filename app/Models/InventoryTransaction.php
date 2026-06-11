<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected static function booted(): void
    {
        $lockCheck = function (self $model) {
            $date = $model->occurred_at instanceof Carbon
                ? $model->occurred_at->toDateString()
                : Carbon::parse($model->occurred_at)->toDateString();

            if (Salary::isPeriodLocked($model->restaurant_id, null, $date)) {
                throw new \Exception('Giao dịch kho đã bị khóa do bảng lương của kỳ này đã được phê duyệt.');
            }
        };

        static::updating($lockCheck);
        static::deleting($lockCheck);
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'quantity' => 'decimal:3',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
