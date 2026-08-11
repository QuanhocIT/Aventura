<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bậc lương do CHỦ doanh nghiệp quy định. Quản lý khi tạo nhân viên chỉ được chọn
 * từ các bậc này (không tự nhập mức tuỳ ý). branch_id null = áp dụng toàn chuỗi.
 */
class WageTier extends Model
{
    use BelongsToRestaurant;

    /** Ước lượng số giờ/ca công trong tháng để quy đổi ra lương tháng khi so quỹ. */
    public const HOURS_PER_MONTH = 208;   // ~26 ngày × 8 giờ

    public const SHIFTS_PER_MONTH = 26;

    protected $fillable = [
        'restaurant_id', 'branch_id', 'name', 'compensation_type', 'rate', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Bậc dùng được cho một chi nhánh: của chính chi nhánh đó hoặc toàn chuỗi. */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    /** Quy đổi mức lương của bậc này về lương THÁNG để đối chiếu quỹ lương. */
    public function estimatedMonthly(): float
    {
        return match ($this->compensation_type) {
            'hourly' => (float) $this->rate * self::HOURS_PER_MONTH,
            'shift' => (float) $this->rate * self::SHIFTS_PER_MONTH,
            default => (float) $this->rate, // fixed (lương tháng)
        };
    }
}
