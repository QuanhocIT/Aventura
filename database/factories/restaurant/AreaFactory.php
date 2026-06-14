<?php

namespace Database\Factories\Restaurant;

use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'name' => 'Khu '.fake()->word(),
            'code' => strtoupper(fake()->unique()->lexify('?')),
            'display_order' => fake()->numberBetween(1, 10),
            'status' => 'active',
        ];
    }
}
