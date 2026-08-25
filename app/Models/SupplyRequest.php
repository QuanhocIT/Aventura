<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequest extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'central_supply_requests';

    protected $guarded = [];

    // Trạng thái hợp lệ theo luồng
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_PREPARING = 'preparing';

    const STATUS_DISPATCH_PENDING = 'dispatch_pending_approval';

    const STATUS_DISPATCHED = 'dispatched';

    const STATUS_PARTIAL_RECEIVED = 'partial_received';

    const STATUS_DISPUTED = 'disputed';

    const STATUS_COMPLETED = 'completed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'branch_monthly_limit_snapshot' => 'decimal:2',
            'branch_monthly_total_before' => 'decimal:2',
            'discrepancy_flag' => 'boolean',
            'requested_delivery_date' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'prepared_at' => 'datetime',
            'dispatch_approved_at' => 'datetime',
            'handover_at' => 'datetime',
            'last_overdue_alert_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'to_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function dispatchApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatch_approved_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function handoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class, 'supply_request_id');
    }

    public function warehouseTasks(): HasMany
    {
        return $this->hasMany(WarehouseTaskAssignment::class, 'supply_request_id');
    }

    /**
     * Reservations (giữ chỗ tồn) liên kết với đơn này.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'supply_request_id');
    }

    /**
     * Đơn gốc nếu đây là backorder.
     */
    public function originalRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_request_id');
    }

    /**
     * Các đơn backorder từ đơn này.
     */
    public function backorders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_request_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDispatched(): bool
    {
        return in_array($this->status, [self::STATUS_DISPATCHED, self::STATUS_PARTIAL_RECEIVED, self::STATUS_DISPUTED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PREPARING]);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
