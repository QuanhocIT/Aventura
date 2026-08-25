<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WarehouseReceivingVoucher extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'warehouse_receiving_vouchers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
            'evidence_paths' => 'array',
            'total_expected_qty' => 'decimal:3',
            'total_actual_qty' => 'decimal:3',
            'total_discrepancy_qty' => 'decimal:3',
            'invoice_date' => 'date',
            'invoice_total_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'putaway_started_at' => 'datetime',
            'putaway_completed_at' => 'datetime',
            'temperature_min_c' => 'decimal:2',
            'temperature_max_c' => 'decimal:2',
            'disposition_evidence_paths' => 'array',
            'disposed_at' => 'datetime',
        ];
    }

    // ── Lifecycle hook ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->voucher_code)) {
                $model->voucher_code = self::generateVoucherCode($model->restaurant_id);
            }
            if (empty($model->idempotency_key)) {
                $model->idempotency_key = Str::uuid()->toString();
            }
        });
    }

    public static function generateVoucherCode(int $restaurantId): string
    {
        $date = now()->format('Ymd');
        $seq = self::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return 'GRN-'.$date.'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function disposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseReceivingVoucherItem::class, 'voucher_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(WarehouseReceivingDocument::class, 'voucher_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePendingVerification($query)
    {
        return $query->whereIn('status', ['discrepancy', 'pending_review']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('received_at', now()->toDateString());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function hasDiscrepancy(): bool
    {
        return (float) $this->total_discrepancy_qty !== 0.0;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'discrepancy', 'rejected']);
    }

    public function isAwaitingApproval(): bool
    {
        return in_array($this->status, ['draft', 'discrepancy', 'pending_review'], true);
    }

    public function requiresDisposition(): bool
    {
        return $this->quality_status === 'failed'
            || $this->status === 'pending_disposition'
            || ($this->hasDiscrepancy() && $this->disposition === 'pending');
    }
}
