<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineStoreConfig extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enable_takeaway' => 'boolean',
            'enable_delivery' => 'boolean',
            'enable_preorder' => 'boolean',
            'accepted_payments' => 'array',
            'operating_hours' => 'array',
            'min_order_amount' => 'decimal:2',
            'delivery_fee_per_km' => 'decimal:2',
            'delivery_base_fee' => 'decimal:2',
            'max_delivery_km' => 'decimal:1',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class);
    }

    public function isOpen(): bool
    {
        if (! $this->operating_hours) {
            return true;
        }

        $day = strtolower(now()->format('D'));
        $dayMap = ['mon' => 'mon', 'tue' => 'tue', 'wed' => 'wed', 'thu' => 'thu', 'fri' => 'fri', 'sat' => 'sat', 'sun' => 'sun'];
        $key = $dayMap[$day] ?? null;

        if (! $key || ! isset($this->operating_hours[$key])) {
            return false;
        }

        $hours = $this->operating_hours[$key];
        $now = now()->format('H:i');

        return $now >= ($hours['open'] ?? '00:00') && $now <= ($hours['close'] ?? '23:59');
    }

    public function calculateDeliveryFee(float $distanceKm): float
    {
        return round((float) $this->delivery_base_fee + ($distanceKm * (float) $this->delivery_fee_per_km));
    }
}
