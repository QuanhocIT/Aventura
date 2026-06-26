<?php

namespace Database\Factories\Hr;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleAssignment>
 */
class ScheduleAssignmentFactory extends Factory
{
    protected $model = ScheduleAssignment::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'branch_id' => RestaurantBranch::factory(),
            'employee_id' => Employee::factory(),
            'shift_id' => WorkShift::factory(),
            'scheduled_date' => now()->toDateString(),
            'check_in_at' => null,
            'check_out_at' => null,
            'status' => 'scheduled',
            'notes' => fake()->optional()->sentence(),
            'approved_by' => User::factory(),
        ];
    }
}
