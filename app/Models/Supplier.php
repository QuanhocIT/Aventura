<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Restaurant\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return SupplierFactory::new();
    }
}

