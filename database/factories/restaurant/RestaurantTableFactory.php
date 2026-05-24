<?php

namespace Database\Factories\Restaurant;

use App\Models\Area;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'area_id' => Area::factory(),
            'name' => strtoupper(fake()->lexify('?')).fake()->numberBetween(1, 20),
            'qr_code' => fake()->uuid(),
            'capacity' => fake()->numberBetween(2, 8),
            'status' => 'available',
        ];
    }
}
