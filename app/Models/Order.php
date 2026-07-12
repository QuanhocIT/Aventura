<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Laravel\Scout\Searchable;

use Database\Factories\Restaurant\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Delivery\DeliveryBatchItem;
use App\Models\Delivery\DeliveryDetail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;
    use Searchable;

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
            'refunded_at'  => 'datetime',
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
        // Xóa cache danh sách bàn khi đơn thay đổi
        $clearTableCache = fn ($order) => \Illuminate\Support\Facades\Cache::forget("restaurant_{$order->restaurant_id}_tables");
        static::saved($clearTableCache);
        static::deleted($clearTableCache);

        // Invalidate live order stats cache + cập nhật intraday summary
        $invalidateStatsCache = function ($order) {
            /** @var \App\Services\OrderStatsCacheService $cacheService */
            $cacheService = app(\App\Services\OrderStatsCacheService::class);
            $cacheService->invalidate($order->restaurant_id, $order->branch_id ?? null);

            // Dispatch intraday summary job cho nhà hàng này sau khi transaction commit
            // withoutDelay() + afterCommit() đảm bảo job không chạy trước khi dữ liệu được ghi
            \App\Jobs\UpdateIntradaySummaryJob::dispatch($order->restaurant_id)
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

