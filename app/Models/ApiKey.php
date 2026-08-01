<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'rotated_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /** Sinh key mới — trả về [plainKey, model]. Plain key chỉ hiển thị một lần. */
    public static function generate(int $restaurantId, string $name): array
    {
        $plainKey = static::newPlainKey();

        $key = static::create([
            'restaurant_id' => $restaurantId,
            'name' => $name,
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => hash('sha256', $plainKey),
        ]);

        return [$plainKey, $key];
    }

    /** Rotate a key and return the new plain value. It is never recoverable afterwards. */
    public static function rotate(self $key): string
    {
        $plainKey = static::newPlainKey();

        $key->update([
            'key_prefix' => substr($plainKey, 0, 12),
            'key_hash' => hash('sha256', $plainKey),
            'last_used_at' => null,
            'rotated_at' => now(),
            'is_active' => true,
        ]);

        return $plainKey;
    }

    private static function newPlainKey(): string
    {
        return 'avk_'.Str::random(40);
    }

    public static function findByPlainKey(string $plainKey): ?self
    {
        return static::withoutGlobalScopes()
            ->where('key_hash', hash('sha256', $plainKey))
            ->where('is_active', true)
            ->first();
    }
}
