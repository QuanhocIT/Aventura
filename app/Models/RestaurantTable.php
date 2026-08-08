<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Support\Tenant\TenantContext;
use Database\Factories\Restaurant\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class RestaurantTable extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'table_id')
            ->activeForService()
            ->currentTableOrder()
            ->latest('updated_at')
            ->latest('id');
    }

    protected static function booted(): void
    {
        static::creating(function ($table) {
            if (empty($table->qr_token)) {
                $table->qr_token = \Illuminate\Support\Str::random(32);
            }
        });

        static::saved(function ($table) {
            self::forgetScopedCaches($table);
            Cache::forget("quota_summary:{$table->restaurant_id}");
        });

        static::deleting(function ($table) {
            // Khi xóa bàn: đổi qr_token sang chuỗi ngẫu nhiên mới & đặt inactive
            // để tất cả mã QR/link cũ đã tạo trước đó bị vô hiệu hóa hoàn toàn
            $table->qr_token = \Illuminate\Support\Str::random(32);
            $table->status = 'inactive';
            $table->saveQuietly();

            \App\Models\TemporaryOrder::where('table_id', $table->id)
                ->whereIn('status', ['waiting_verification', 'escalated'])
                ->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Bàn ăn đã bị xóa bởi chủ nhà hàng/quản lý',
                ]);
        });

        static::deleted(function ($table) {
            self::forgetScopedCaches($table);
            Cache::forget("quota_summary:{$table->restaurant_id}");
        });
    }

    /**
     * Xóa các cache sơ đồ bàn sau khi đơn/món ăn thay đổi.
     *
     * Đơn hàng không tự lưu lại model bàn, nên cache theo scope của bàn
     * trước đây có thể giữ trạng thái "trống" dù đã có món mới.
     */
    public static function forgetTableCachesFor(int $restaurantId, ?int $branchId = null): void
    {
        Cache::forget("restaurant_{$restaurantId}_tables");
        Cache::forget("restaurant_{$restaurantId}_tables:scope:all");
        Cache::forget("restaurant_{$restaurantId}_tables:scope:none");

        if ($branchId) {
            Cache::forget("restaurant_{$restaurantId}_tables:scope:".TenantContext::branchScopeKey($branchId));
        }
    }

    private static function forgetScopedCaches(self $table): void
    {
        self::forgetTableCachesFor((int) $table->restaurant_id, $table->branch_id ? (int) $table->branch_id : null);

        foreach (['areas'] as $resource) {
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}");
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:all");
            Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:none");

            if ($table->branch_id) {
                Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:".TenantContext::branchScopeKey((int) $table->branch_id));
            }

            $originalBranchId = $table->getOriginal('branch_id');
            if ($originalBranchId && (int) $originalBranchId !== (int) $table->branch_id) {
                Cache::forget("restaurant_{$table->restaurant_id}_{$resource}:scope:".TenantContext::branchScopeKey((int) $originalBranchId));
            }
        }
    }

    protected static function newFactory(): Factory
    {
        return RestaurantTableFactory::new();
    }
}
