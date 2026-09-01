<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceivingReport extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    public const STATUS_EMPLOYEE_CONFIRMED = 'employee_confirmed';

    protected $table = 'warehouse_receiving_reports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'employee_confirmed_at' => 'datetime',
            'evidence_paths' => 'array',
            'total_expected_qty' => 'decimal:3',
            'total_actual_qty' => 'decimal:3',
            'total_discrepancy_qty' => 'decimal:3',
            'total_value' => 'decimal:2',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceivingVoucher::class, 'voucher_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function employeeConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseReceivingReportItem::class, 'report_id');
    }

    public function hasQuantityDifference(): bool
    {
        return abs((float) $this->total_discrepancy_qty) > 0.0005;
    }
}
