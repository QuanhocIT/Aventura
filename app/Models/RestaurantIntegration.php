<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantIntegration extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Lấy integration đã bật của một nhà hàng (bỏ qua tenant scope — dùng được
     * trong webhook công khai và queue job không có tenant context).
     */
    public static function findEnabled(int $restaurantId, string $provider): ?self
    {
        return static::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->first();
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        $value = $this->credentials[$key] ?? $default;

        return $value !== null && $value !== '' ? (string) $value : $default;
    }
}
