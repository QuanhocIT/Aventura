<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosDevice extends Model
{
    use BelongsToRestaurant;

    public const TYPE_POS = 'pos';
    public const TYPE_PRINTER = 'printer';

    protected $guarded = [];

    protected $hidden = ['device_token'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /** Thiết bị được coi là online nếu có heartbeat trong 2 phút gần nhất. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    public static function generatePairingCode(): string
    {
        return strtoupper(Str::random(6));
    }

    public static function generateDeviceToken(): string
    {
        return Str::random(64);
    }
}
