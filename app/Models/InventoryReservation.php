<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at'   => 'datetime',
            'released_at'  => 'datetime',
            'quantity'     => 'decimal:3',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Đơn cấp phát liên kết (khi reservation_type = 'supply_request').
     */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Giải phóng reservation (gọi khi xuất kho thực tế hoặc hủy đơn).
     */
    public function release(): void
    {
        $this->update(['released_at' => now()]);
    }

    /**
     * Kiểm tra reservation còn hiệu lực không (chưa released, chưa expired).
     */
    public function isActive(): bool
    {
        if ($this->released_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
