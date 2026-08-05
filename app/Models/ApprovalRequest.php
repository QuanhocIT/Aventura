<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function operationLabel(): string
    {
        return match ($this->operation_type) {
            'inventory_purchase' => 'Nhập nguyên liệu',
            'inventory_waste' => 'Ghi hao hụt',
            'salary_adjustment' => 'Điều chỉnh lương',
            'shift_checkin' => 'Xác nhận vào ca',
            'employee_create' => 'Tạo nhân viên mới',
            default => $this->operation_type,
        };
    }
}
