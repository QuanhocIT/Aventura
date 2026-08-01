<?php

namespace App\Models;

use App\Jobs\UpdateIntradaySummaryJob;
use App\Models\Concerns\BelongsToRestaurant;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\DeliveryDetail;
use App\Services\LoyaltyService;
use App\Services\OrderStatsCacheService;
use App\Support\Tenant\TenantContext;
use Database\Factories\Restaurant\OrderFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Order extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'restaurant_id' => (int) $this->restaurant_id,
            'table_id' => (int) $this->table_id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->note,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Tính tổng số điểm tích lũy của toàn bộ các món trong hóa đơn này.
     */
    public function calculateTotalEarnPoints(): int
    {
        $this->loadMissing('items.product');
        $totalEarn = 0;
        foreach ($this->items as $item) {
            $earnPoints = (int) ($item->product?->earn_points ?? 0);
            if ($earnPoints > 0) {
                $totalEarn += $earnPoints * (int) $item->quantity;
            }
        }

        if ($totalEarn === 0 && (float) $this->total_amount > 0) {
            $program = (new LoyaltyService)->getProgram($this->restaurant_id);
            if ($program && $program->is_active) {
                $totalEarn = (int) floor((float) $this->total_amount * (float) $program->points_per_vnd);
            }
        }

        return $totalEarn;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveryDetail(): HasOne
    {
        return $this->hasOne(DeliveryDetail::class);
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(DeliveryBatchItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (! $order->restaurant_id) {
                return;
            }

            $branchBelongsToRestaurant = $order->branch_id
                && RestaurantBranch::where('restaurant_id', $order->restaurant_id)
                    ->whereKey($order->branch_id)
                    ->exists();

            if ($branchBelongsToRestaurant) {
                return;
            }

            $contextBranchId = app(TenantContext::class)->activeBranchId();
            $order->branch_id = $contextBranchId
                ?: RestaurantBranch::where('restaurant_id', $order->restaurant_id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->value('id');
        });

        // Xóa cache danh sách bàn khi đơn thay đổi
        $clearTableCache = fn ($order) => Cache::forget("restaurant_{$order->restaurant_id}_tables");
        static::saved($clearTableCache);
        static::deleted($clearTableCache);

        // Invalidate live order stats cache + cập nhật intraday summary
        $invalidateStatsCache = function ($order) {
            /** @var OrderStatsCacheService $cacheService */
            $cacheService = app(OrderStatsCacheService::class);
            $cacheService->invalidate($order->restaurant_id, $order->branch_id ?? null);

            // Dispatch intraday summary job cho nhà hàng này sau khi transaction commit
            // withoutDelay() + afterCommit() đảm bảo job không chạy trước khi dữ liệu được ghi
            UpdateIntradaySummaryJob::dispatch($order->restaurant_id)
                ->afterCommit()
                ->onQueue('low');
        };

        static::saved($invalidateStatsCache);
        static::deleted($invalidateStatsCache);
    }

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
