<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantBranch>
 */
class RestaurantBranchFactory extends Factory
{
    protected $model = RestaurantBranch::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'code' => strtoupper(fake()->unique()->lexify('BR??')),
            'name' => 'Chi nhanh '.fake()->city(),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'manager_user_id' => User::factory(),
            'status' => 'active',
        ];
    }
}
