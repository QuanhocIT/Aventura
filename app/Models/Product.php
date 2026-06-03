<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

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

    protected $guarded = [];

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

