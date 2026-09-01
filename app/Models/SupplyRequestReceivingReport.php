<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequestReceivingReport extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    public const STATUS_PENDING_BRANCH_CONFIRMATION = 'pending_branch_confirmation';

    public const STATUS_CONFIRMED_PENDING_ACK = 'confirmed_pending_ack';

    public const STATUS_DRIVER_CONFIRMED = 'driver_confirmed';

    public const STATUS_RESOLVED = 'resolved';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'driver_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'temperature_min_c' => 'decimal:2',
            'temperature_max_c' => 'decimal:2',
        ];
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transporter_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function driverConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_confirmed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestReceivingReportItem::class, 'receiving_report_id');
    }

    public function problemItems(): HasMany
    {
        return $this->items()->where(function ($query): void {
            $query->where('submitted_damaged_quantity', '>', 0)
                ->orWhere('submitted_expired_quantity', '>', 0)
                ->orWhere('submitted_wrong_item_quantity', '>', 0)
                ->orWhere('submitted_shortage_quantity', '>', 0);
        });
    }

    public function isPendingBranchConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_BRANCH_CONFIRMATION;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED_PENDING_ACK,
            self::STATUS_DRIVER_CONFIRMED,
            self::STATUS_RESOLVED,
        ], true);
    }
}
