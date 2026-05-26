<?php

namespace Database\Factories\Tenant;

use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        $name = fake()->company().' Restaurant';

        return [
            'plan_id' => SubscriptionPlan::factory(),
            'owner_user_id' => User::factory(),
            'code' => strtoupper(fake()->unique()->lexify('RST???')),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'tax_code' => fake()->optional()->numerify('##########'),
            'phone' => fake()->numerify('0#########'),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'logo_url' => fake()->optional()->imageUrl(),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'status' => 'active',
            'subscription_started_at' => now()->toDateString(),
            'subscription_ends_at' => now()->addMonth()->toDateString(),
            'trial_ends_at' => null,
        ];
    }
}
