<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'branch_id',
        'requester_id',
        'reviewer_id',
        'operation_type',
        'operation_data',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'operation_data' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function operationLabel(): string
    {
        return match ($this->operation_type) {
            'inventory_create' => 'Thêm nguyên liệu mới',
            'inventory_update' => 'Cập nhật thông tin nguyên liệu',
            'inventory_delete' => 'Xóa nguyên liệu khỏi kho',
            'inventory_adjustment' => 'Điều chỉnh tồn kho',
            'inventory_purchase' => 'Nhập nguyên liệu kho',
            'inventory_waste' => 'Ghi nhận hủy hàng / hao hụt',
            'inventory_stocktake' => 'Kiểm kê và điều chỉnh tồn kho',
            'inventory_recipe_save' => 'Cập nhật công thức định lượng',
            'inventory_recipe_delete' => 'Xóa công thức định lượng',
            'warehouse_set_central' => 'Thiết lập Kho Tổng',
            'warehouse_price_update' => 'Cập nhật đơn giá nguyên liệu toàn chuỗi',
            'warehouse_supply_approve' => 'Duyệt đơn cấp phát từ chi nhánh',
            'warehouse_supply_dispatch' => 'Xuất Kho Tổng giao chi nhánh',
            'warehouse_supply_reject' => 'Từ chối đơn cấp phát',
            'supply_request' => 'Đơn cấp phát nguyên liệu',
            'salary_adjustment' => 'Điều chỉnh lương nhân sự',
            'shift_checkin' => 'Xác nhận vào ca',
            'shift_checkout' => 'Xác nhận hết ca',
            'employee_create' => 'Tạo nhân viên mới',
            'order_refund' => 'Hoàn tiền đơn hàng POS',
            'order_item_cancel' => 'Hủy món / ghi nhận tổn thất',
            default => $this->operation_type,
        };
    }
}
