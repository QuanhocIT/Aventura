<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceMode extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'enabled',
        'message',
        'starts_at',
        'ends_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Global maintenance rows (restaurant_id = null) apply to every tenant. */
    public function shouldIncludeSystemShared(): bool
    {
        return true;
    }

    public static function activeFor(?int $restaurantId): ?self
    {
        $now = now();

        return static::query()
            ->where('enabled', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($query) use ($restaurantId): void {
                $query->whereNull('restaurant_id');

                if ($restaurantId !== null) {
                    $query->orWhere('restaurant_id', $restaurantId);
                }
            })
            ->orderByRaw('restaurant_id IS NOT NULL DESC')
            ->latest('updated_at')
            ->first();
    }
}
