<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'discount_value' => 'float',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    /**
     * Các lần sử dụng coupon này (từ billing_adjustments).
     */
    public function usages(): HasMany
    {
        return $this->hasMany(BillingAdjustment::class, 'coupon_code', 'code');
    }

    /**
     * Check if coupon is currently valid.
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given price.
     */
    public function getDiscountAmount(float $price): float
    {
        if (! $this->isValid()) {
            return 0;
        }

        if ($this->discount_type === 'percent') {
            return round(($price * $this->discount_value) / 100);
        }

        // Fixed amount
        return min($this->discount_value, $price);
    }
}
