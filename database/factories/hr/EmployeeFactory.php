<?php

namespace Database\Factories\Hr;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'user_id' => User::factory(),
            'employee_code' => strtoupper(fake()->unique()->lexify('EMP???')),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'citizen_id_number' => fake()->numerify('############'),
            'citizen_id_front_url' => fake()->optional()->imageUrl(),
            'citizen_id_back_url' => fake()->optional()->imageUrl(),
            'hire_date' => fake()->date(),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'seasonal']),
            'job_title' => fake()->jobTitle(),
            'base_salary' => fake()->numberBetween(6000000, 18000000),
            'status' => 'active',
        ];
    }
}
