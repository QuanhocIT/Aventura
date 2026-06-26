<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_to_kitchen_at' => 'datetime',
            'prepared_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        $clearCache = fn ($item) => \Illuminate\Support\Facades\Cache::forget("restaurant_{$item->restaurant_id}_tables");
        static::saved($clearCache);
        static::deleted($clearCache);
    }

    protected static function newFactory(): Factory
    {
        return OrderItemFactory::new();
    }
}

