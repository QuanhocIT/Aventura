<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }
}
