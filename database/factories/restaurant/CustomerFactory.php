<?php

namespace Database\Factories\Restaurant;

use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->safeEmail(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'date_of_birth' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
            'loyalty_points' => fake()->numberBetween(0, 1000),
            'last_order_at' => now(),
        ];
    }
}
