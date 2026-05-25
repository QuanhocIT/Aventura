<?php

namespace Database\Factories\Restaurant;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50000, 500000);

        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'order_id' => Order::factory(),
            'processed_by' => User::factory(),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'card', 'ewallet']),
            'status' => 'paid',
            'amount' => $amount,
            'cash_received' => $amount,
            'change_amount' => 0,
            'transaction_code' => fake()->uuid(),
            'paid_at' => now(),
            'meta' => ['source' => 'factory'],
        ];
    }
}
