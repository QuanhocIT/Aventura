<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function ingredientOptions()
    {
        return $this->hasMany(IngredientSupplier::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(SupplierPriceHistory::class);
    }
}
