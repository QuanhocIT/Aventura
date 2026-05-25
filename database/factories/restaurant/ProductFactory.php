<?php

namespace Database\Factories\Restaurant;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'category_id' => ProductCategory::factory(),
            'code' => strtoupper(fake()->unique()->lexify('PRD???')),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(3),
            'description' => fake()->sentence(),
            'image_url' => fake()->optional()->imageUrl(),
            'price' => fake()->randomFloat(2, 10000, 200000),
            'cost_price' => fake()->randomFloat(2, 5000, 100000),
            'preparation_time_minutes' => fake()->numberBetween(3, 20),
            'is_active' => true,
            'is_available' => true,
            'is_featured' => fake()->boolean(),
            'track_inventory' => fake()->boolean(),
        ];
    }
}
