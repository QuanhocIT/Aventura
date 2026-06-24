<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->randomElement(['Gram', 'Kilogram', 'Liter', 'Piece']),
            'symbol' => fake()->unique()->randomElement(['g', 'kg', 'l', 'pcs', 'cup']),
            'type' => fake()->randomElement(['mass', 'volume', 'count']),
        ];
    }
}
