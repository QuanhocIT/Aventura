<?php

namespace App\Models\Concerns;

use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Biến thể của BelongsToRestaurant dành cho các model mà branch_id NULL mang ý
 * nghĩa riêng ("áp dụng toàn chuỗi") thay vì "chưa gán".
 *
 * BelongsToRestaurant tự điền branch_id đang hoạt động khi tạo bản ghi. Với
 * approval_policies, hành vi đó sẽ biến một chính sách toàn chuỗi thành chính
 * sách của đúng chi nhánh mà Chủ đang mở — sai lệch âm thầm và rất khó lần ra.
 * Trait này giữ nguyên phần cách ly nhà hàng và chống IDOR, chỉ bỏ phần tự điền
 * chi nhánh.
 */
trait BelongsToRestaurantOnly
{
    protected static function bootBelongsToRestaurantOnly(): void
    {
        static::addGlobalScope('restaurant', function (Builder $builder): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null) {
                $builder->where($builder->getModel()->getTable().'.restaurant_id', $restaurantId);
            }
        });

        static::creating(function ($model): void {
            $restaurantId = app(TenantContext::class)->restaurantId();

            if ($restaurantId !== null && empty($model->restaurant_id)) {
                $model->restaurant_id = $restaurantId;
            }
        });
    }

    /**
     * Scope route-model-binding theo nhà hàng của người dùng đăng nhập.
     *
     * SetTenantContext chạy sau SubstituteBindings nên global scope là no-op lúc
     * bind; nếu chỉ dựa vào nó, một owner có thể thao tác trên bản ghi của nhà
     * hàng khác qua {model} trên URL.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?: $this->getRouteKeyName();
        $table = $this->getTable();
        $query = $this->newQuery()->where($table.'.'.$field, $value);

        $user = auth()->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

        if ($user && $user->restaurant_id && ! $isSuperAdmin) {
            $query->where($table.'.restaurant_id', $user->restaurant_id);
        }

        return $query->first();
    }
}
