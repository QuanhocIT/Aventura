<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\UnitFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conversion_factor_to_base' => 'float',
        ];
    }

    protected static function newFactory(): Factory
    {
        return UnitFactory::new();
    }
}
