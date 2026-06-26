<?php

namespace Database\Factories\Restaurant;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 10000, 200000);

        return [
            'restaurant_id' => Restaurant::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'line_total' => $quantity * $unitPrice,
            'status' => fake()->randomElement(['pending', 'sent', 'preparing', 'served']),
            'notes' => fake()->optional()->sentence(),
            'sent_to_kitchen_at' => now(),
            'prepared_at' => now(),
            'served_at' => null,
        ];
    }
}
