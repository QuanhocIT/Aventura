<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use BelongsToRestaurant;

    /** Các sự kiện developer có thể đăng ký nhận. */
    public const EVENTS = [
        'order.created',
        'order.paid',
        'order.completed',
        'order.cancelled',
        'reservation.created',
        'inventory.low_stock',
    ];

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
