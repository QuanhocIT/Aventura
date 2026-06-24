<?php

namespace Database\Factories\Restaurant;

use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_id' => Unit::factory(),
            'name' => fake()->words(2, true),
            'sku' => strtoupper(fake()->unique()->lexify('ING???')),
            'category_name' => fake()->word(),
            'description' => fake()->sentence(),
            'min_stock_level' => fake()->randomFloat(3, 0, 1000),
            'reorder_level' => fake()->randomFloat(3, 100, 2000),
            'average_cost' => fake()->randomFloat(2, 1, 500),
            'status' => 'active',
        ];
    }
}
