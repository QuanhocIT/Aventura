<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Laravel\Scout\Searchable;

use Database\Factories\Restaurant\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Product extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;
    use Searchable;

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
        return $this->morphMany(\App\Models\MediaAsset::class, 'attachable');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'product_image');
    }

    protected static function booted(): void
    {
        static::saved(fn ($product) => \Illuminate\Support\Facades\Cache::forget("restaurant_{$product->restaurant_id}_products"));
        static::deleted(fn ($product) => \Illuminate\Support\Facades\Cache::forget("restaurant_{$product->restaurant_id}_products"));
    }

    protected static function newFactory(): Factory
    {
        return ProductFactory::new();
    }
}

