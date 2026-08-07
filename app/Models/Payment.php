<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Support\Tenant\TenantContext;
use Database\Factories\Restaurant\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if ($payment->branch_id !== null || ! $payment->order_id) {
                return;
            }

            $orderQuery = Order::withoutGlobalScopes()
                ->whereKey($payment->order_id);

            if ($payment->restaurant_id !== null) {
                $orderQuery->where('restaurant_id', $payment->restaurant_id);
            }

            $payment->branch_id = $orderQuery->value('branch_id')
                ?? app(TenantContext::class)->activeBranchId();
        });
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function newFactory(): Factory
    {
        return PaymentFactory::new();
    }
}
