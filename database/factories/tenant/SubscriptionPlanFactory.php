<?php

namespace Database\Factories\Tenant;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'price' => fake()->numberBetween(0, 999000),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'max_branches' => fake()->optional()->numberBetween(1, 10),
            'max_tables' => fake()->optional()->numberBetween(10, 100),
            'max_users' => fake()->optional()->numberBetween(5, 100),
            'features' => [
                'analytics' => fake()->boolean(),
                'multi_branch' => fake()->boolean(),
                'ai_prediction' => fake()->boolean(),
            ],
            'status' => 'active',
        ];
    }
}
