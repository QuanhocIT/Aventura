<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lượt áp mã khuyến mãi lên một đơn hàng.
 *
 * Nguồn sự thật cho: chống áp trùng mã, đếm lượt sử dụng (tổng và theo khách),
 * và quy doanh thu/chi phí giảm giá về đúng từng chương trình.
 */
class PromotionUsage extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'order_subtotal' => 'decimal:2',
        'used_bypass' => 'boolean',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
