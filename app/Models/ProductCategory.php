<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ProductCategory extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    protected static function booted(): void
    {
        static::saved(fn ($category) => Cache::forget("restaurant_{$category->restaurant_id}_categories"));
        static::deleted(fn ($category) => Cache::forget("restaurant_{$category->restaurant_id}_categories"));
    }

    protected static function newFactory(): Factory
    {
        return ProductCategoryFactory::new();
    }
}
