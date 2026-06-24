<?php

namespace Database\Factories\Restaurant;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'table_id' => RestaurantTable::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'cashier_user_id' => User::factory(),
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'channel' => fake()->randomElement(['dine_in', 'takeaway', 'delivery', 'qr']),
            'status' => fake()->randomElement(['pending', 'confirmed', 'preparing', 'completed']),
            'payment_status' => fake()->randomElement(['unpaid', 'partial', 'paid']),
            'subtotal' => fake()->randomFloat(2, 50000, 500000),
            'discount_amount' => fake()->randomFloat(2, 0, 50000),
            'service_charge' => 0,
            'tax_amount' => 0,
            'total_amount' => fake()->randomFloat(2, 50000, 500000),
            'note' => fake()->optional()->sentence(),
            'confirmed_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ];
    }
}
