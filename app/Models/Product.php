<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Services\MenuCatalogCacheService;
use Database\Factories\Restaurant\ProductFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'restaurant_id' => (int) $this->restaurant_id,
            'category_id' => (int) $this->category_id,
            'code' => $this->code,
            'name' => $this->name,
            'price' => (float) $this->price,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_available' => (bool) $this->is_available,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    protected function casts(): array
    {
        return [
            'paused_until' => 'datetime',
            'out_of_stock_until' => 'datetime',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'earn_points' => 'integer',
            'redeem_points' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'attachable');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(MediaAsset::class, 'attachable')
            ->where('collection', 'product_image');
    }

    public function priceTests(): HasMany
    {
        return $this->hasMany(MenuPriceTest::class);
    }

    public function activePriceTest()
    {
        return $this->hasOne(MenuPriceTest::class)->where('status', 'running');
    }

    public function scopeForTimeSlot($query, ?string $slot = null)
    {
        if (! $slot) {
            $hour = (int) now()->format('H');
            $slot = match (true) {
                $hour < 11 => 'morning',
                $hour < 14 => 'lunch',
                $hour < 17 => 'afternoon',
                default => 'dinner',
            };
        }

        return $query->where(fn ($q) => $q->whereNull('time_slot')->orWhere('time_slot', $slot));
    }

    public function scopeForSeason($query, ?string $season = null)
    {
        $season = $season ?? $this->getCurrentSeason();

        return $query->where(fn ($q) => $q->whereNull('season')->orWhere('season', $season));
    }

    private function getCurrentSeason(): string
    {
        $month = (int) now()->format('m');

        return match (true) {
            $month <= 3 => 'spring',
            $month <= 6 => 'summer',
            $month <= 9 => 'autumn',
            default => 'winter',
        };
    }

    protected static function booted(): void
    {
        static::saved(function ($product) {
            Cache::forget("restaurant_{$product->restaurant_id}_products");
            if ($product->restaurant_id) {
                app(MenuCatalogCacheService::class)->invalidate($product->restaurant_id);
            }
        });
        static::deleted(function ($product) {
            Cache::forget("restaurant_{$product->restaurant_id}_products");
            if ($product->restaurant_id) {
                app(MenuCatalogCacheService::class)->invalidate($product->restaurant_id);
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}
