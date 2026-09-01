<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceivingReportItem extends Model
{
    use HasFactory;

    protected $table = 'warehouse_receiving_report_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'actual_quantity' => 'decimal:3',
            'difference_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'line_value' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceivingReport::class, 'report_id');
    }

    public function voucherItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceivingVoucherItem::class, 'voucher_item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
