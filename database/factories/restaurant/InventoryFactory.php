<?php

namespace Database\Factories\Restaurant;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'ingredient_id' => Ingredient::factory(),
            'quantity_on_hand' => fake()->randomFloat(3, 10, 1000),
            'theoretical_quantity' => fake()->randomFloat(3, 10, 1000),
            'last_counted_at' => now(),
            'last_cost' => fake()->randomFloat(2, 1, 500),
            'updated_by' => User::factory(),
        ];
    }
}
