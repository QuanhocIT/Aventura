<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(SupplierCatalogItem::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(SupplierPriceHistory::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}

