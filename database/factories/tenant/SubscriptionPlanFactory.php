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
                'kitchen_display' => true,
                'qr_ordering' => true,
                'inventory_basic' => true,
                'hr_timekeeping' => true,
                'hr_full' => true,
                'advanced_analytics' => true,
                'realtime' => true,
                'fraud_detection' => true,
                'email_reports' => true,
                'ai_advisor' => true,
                'supplier_portal' => true,
                'ai_forecasting' => true,
                'api_access' => true,
                'max_areas' => null,
                'max_storage_mb' => 204800,
                'api_rate_limit' => 3000,
            ],
            'status' => 'active',
        ];
    }
}
