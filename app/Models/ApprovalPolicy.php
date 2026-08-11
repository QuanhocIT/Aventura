<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurantOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chính sách thẩm quyền cho một loại thao tác.
 *
 * branch_id = null nghĩa là áp dụng toàn chuỗi. Dòng của chi nhánh cụ thể luôn
 * thắng dòng toàn chuỗi (xem resolve()).
 */
class ApprovalPolicy extends Model
{
    use BelongsToRestaurantOnly;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manager_can_approve' => 'boolean',
            'requires_owner_countersign' => 'boolean',
            'is_active' => 'boolean',
            'manager_limit_amount' => 'decimal:2',
            'manager_daily_limit' => 'decimal:2',
            'manager_monthly_limit' => 'decimal:2',
            'auto_escalate_after_minutes' => 'integer',
            'conditions' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Tìm chính sách áp dụng: ưu tiên dòng riêng của chi nhánh, sau đó tới dòng
     * toàn chuỗi. Trả về null khi Chủ chưa cấu hình gì — khi đó hệ thống mặc
     * định là chỉ Chủ được duyệt (an toàn hơn là mở sẵn).
     */
    public static function resolve(int $restaurantId, string $operationType, ?int $branchId): ?self
    {
        return static::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('operation_type', $operationType)
            ->where('is_active', true)
            ->when(
                $branchId !== null,
                fn ($q) => $q->where(fn ($sub) => $sub->where('branch_id', $branchId)->orWhereNull('branch_id')),
                fn ($q) => $q->whereNull('branch_id'),
            )
            // Biểu thức trả 0 cho dòng có branch_id, 1 cho dòng toàn chuỗi; sắp
            // tăng dần nên dòng của chi nhánh luôn đứng trước. Đúng trên cả
            // MySQL lẫn SQLite.
            ->orderByRaw('branch_id IS NULL')
            ->first();
    }

    /**
     * Ảnh chụp hạn mức tại thời điểm ra quyết định, lưu vào sổ phê duyệt.
     */
    public function snapshot(): array
    {
        return [
            'policy_id' => $this->id,
            'branch_id' => $this->branch_id,
            'manager_can_approve' => $this->manager_can_approve,
            'manager_limit_amount' => $this->manager_limit_amount,
            'manager_daily_limit' => $this->manager_daily_limit,
            'manager_monthly_limit' => $this->manager_monthly_limit,
            'requires_owner_countersign' => $this->requires_owner_countersign,
            'conditions' => $this->conditions,
        ];
    }
}
