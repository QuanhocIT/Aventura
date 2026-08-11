<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần tạm ngưng bán món tại MỘT chi nhánh cụ thể.
 */
class ProductBranchPause extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'paused_until' => 'datetime',
        'reopen_requested_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    /** Đang khóa: chưa mở lại và (không hạn hoặc còn trong hạn). */
    public function scopeActivePause(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'reopen_requested'])
            ->where(fn (Builder $q) => $q->whereNull('paused_until')->orWhere('paused_until', '>', now()));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }
}
