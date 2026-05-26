<?php

namespace Database\Factories\Hr;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    protected $model = WorkShift::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'name' => 'Ca '.fake()->word(),
            'code' => strtoupper(fake()->unique()->lexify('SHIFT??')),
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_overnight' => false,
            'status' => 'active',
        ];
    }
}
