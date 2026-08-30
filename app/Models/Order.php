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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Order extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $guarded = ['id'];

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
            'is_payment_requested' => 'boolean',
            'payment_requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'inventory_restored_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id')->withTrashed();
    }

    /**
     * Fix #9: Hiển thị tên bàn kèm ghi chú nếu bàn đã bị xóa mềm,
     * tránh nhân viên bối rối khi thấy bàn "không tồn tại" trên giao diện.
     */
    public function getTableDisplayNameAttribute(): ?string
    {
        $tableModel = $this->table()->first();
        if (! $tableModel) {
            return null;
        }

        return $tableModel->trashed()
            ? $tableModel->name.' (Đã xóa)'
            : $tableModel->name;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function eInvoice(): HasOne
    {
        return $this->hasOne(EInvoice::class);
    }

    /**
     * Đơn vẫn đang chiếm bàn về mặt vận hành.
     *
     * Thanh toán có thể hoàn tất trước khi bếp/phục vụ hoàn tất, vì vậy
     * payment_status không đủ để quyết định bàn đã được giải phóng hay chưa.
     */
    public function scopeActiveForService(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q): void {
                $q->whereNotIn('payment_status', ['paid', 'refunded'])
                    ->orWhereNull('payment_status')
                    ->orWhereHas('items', function (Builder $itemQuery): void {
                        $itemQuery
                            ->where('status', '!=', 'cancelled')
                            ->whereNull('served_at');
                    });
            });
    }

    /**
     * Chỉ lấy đơn hiện hành đầu tiên của một bàn.
     *
     * Điều này giúp các bản ghi trùng do dữ liệu cũ không làm nhân đôi đơn
     * trên màn hình quản lý/bếp. Nếu một đơn cũ được phục vụ sau thời điểm
     * đơn mới tạo ra, đơn mới vẫn được xem là bản ghi trùng và bị ẩn khỏi
     * luồng vận hành.
     */
    public function scopeCurrentTableOrder(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('table_id')
                ->orWhere(function ($tableOrder): void {
                    $tableOrder->where(function ($current): void {
                        // Đơn đã thanh toán nhưng còn món chưa phục vụ được
                        // ưu tiên giữ lại để không bị một bản ghi trùng mới che mất.
                        $current->where(function ($paidWithUnserved): void {
                            $paidWithUnserved
                                ->whereIn('orders.payment_status', ['paid', 'refunded'])
                                ->whereExists(function ($item): void {
                                    $item
                                        ->selectRaw('1')
                                        ->from('order_items as current_items')
                                        ->whereColumn('current_items.order_id', 'orders.id')
                                        ->where('current_items.status', '!=', 'cancelled')
                                        ->whereNull('current_items.served_at');
                                });
                        })->orWhere(function ($unpaidCurrent): void {
                            $unpaidCurrent
                                ->where(function ($payment): void {
                                    $payment
                                        ->whereNotIn('orders.payment_status', ['paid', 'refunded'])
                                        ->orWhereNull('orders.payment_status');
                                })
                                // Không để đơn chưa thanh toán mới che đơn đã
                                // thanh toán còn món đang chờ phục vụ.
                                ->whereNotExists(function ($previousPaid): void {
                                    $previousPaid
                                        ->selectRaw('1')
                                        ->from('orders as previous_paid_orders')
                                        ->whereColumn('previous_paid_orders.restaurant_id', 'orders.restaurant_id')
                                        ->whereColumn('previous_paid_orders.branch_id', 'orders.branch_id')
                                        ->whereColumn('previous_paid_orders.table_id', 'orders.table_id')
                                        ->whereColumn('previous_paid_orders.id', '<', 'orders.id')
                                        ->whereNull('previous_paid_orders.deleted_at')
                                        ->where('previous_paid_orders.status', '!=', 'cancelled')
                                        ->whereIn('previous_paid_orders.payment_status', ['paid', 'refunded'])
                                        ->whereExists(function ($item): void {
                                            $item
                                                ->selectRaw('1')
                                                ->from('order_items as previous_paid_items')
                                                ->whereColumn('previous_paid_items.order_id', 'previous_paid_orders.id')
                                                ->where('previous_paid_items.status', '!=', 'cancelled')
                                                ->whereNull('previous_paid_items.served_at');
                                        });
                                })
                                ->whereNotExists(function ($later): void {
                                    $later
                                        ->selectRaw('1')
                                        ->from('orders as later_orders')
                                        ->whereColumn('later_orders.restaurant_id', 'orders.restaurant_id')
                                        ->whereColumn('later_orders.branch_id', 'orders.branch_id')
                                        ->whereColumn('later_orders.table_id', 'orders.table_id')
                                        ->whereNull('later_orders.deleted_at')
                                        ->where('later_orders.status', '!=', 'cancelled')
                                        ->where(function ($active): void {
                                            $active
                                                ->whereNotIn('later_orders.payment_status', ['paid', 'refunded'])
                                                ->orWhereNull('later_orders.payment_status')
                                                ->orWhereExists(function ($item): void {
                                                    $item
                                                        ->selectRaw('1')
                                                        ->from('order_items as later_items')
                                                        ->whereColumn('later_items.order_id', 'later_orders.id')
                                                        ->where('later_items.status', '!=', 'cancelled')
                                                        ->whereNull('later_items.served_at');
                                                });
                                        })
                                        ->where(function ($newer): void {
                                            $newer
                                                ->whereColumn('later_orders.updated_at', '>', 'orders.updated_at')
                                                ->orWhere(function ($sameTime): void {
                                                    $sameTime
                                                        ->whereColumn('later_orders.updated_at', '=', 'orders.updated_at')
                                                        ->whereColumn('later_orders.id', '>', 'orders.id');
                                                });
                                        });
                                });
                        });
                    });
                });
        });
    }

    /**
     * Tính số điểm tích lũy theo tổng giá trị của hóa đơn này.
     */
    public function calculateTotalEarnPoints(): int
    {
        if ((float) $this->total_amount <= 0) {
            return 0;
        }

        $program = (new LoyaltyService)->getProgram($this->restaurant_id);

        if (! $program || ! $program->is_active) {
            return 0;
        }

        return (int) floor((float) $this->total_amount * (float) $program->points_per_vnd);
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
            if (blank($order->tracking_token)) {
                $order->tracking_token = bin2hex(random_bytes(32));
            }

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
        $clearTableCache = fn ($order) => RestaurantTable::forgetTableCachesFor(
            (int) $order->restaurant_id,
            $order->branch_id ? (int) $order->branch_id : null,
        );
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
