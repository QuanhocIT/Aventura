<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date',
            'received_at'            => 'datetime',
            'preparing_at'           => 'datetime',
            'in_transit_at'          => 'datetime',
            'delivered_at'           => 'datetime',
            'cancelled_at'           => 'datetime',
            'total_amount'           => 'decimal:2',
        ];
    }

    /** Workflow: trạng thái tiếp theo hợp lệ */
    public static array $transitions = [
        'pending'    => 'received',
        'received'   => 'preparing',
        'preparing'  => 'in_transit',
        'in_transit' => 'delivered',
    ];

    public static array $statusLabels = [
        'pending'    => 'Chờ tiếp nhận',
        'received'   => 'Đã tiếp nhận',
        'preparing'  => 'Đang chuẩn bị hàng',
        'in_transit' => 'Đang vận chuyển',
        'delivered'  => 'Đã hạ hàng tại kho',
        'cancelled'  => 'Đã hủy',
    ];

    public function nextStatus(): ?string
    {
        return self::$transitions[$this->status] ?? null;
    }

    public function canAdvance(): bool
    {
        return isset(self::$transitions[$this->status]);
    }

    // ── Relationships ─────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusLog::class)->latest('occurred_at');
    }

    // ── Helpers ───────────────────────────────────────────────

    public static function generatePoNumber(): string
    {
        $date     = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;

        return sprintf('PO-%s-%04d', $date, $sequence);
    }
}
