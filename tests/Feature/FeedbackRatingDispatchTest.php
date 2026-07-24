<?php

namespace Tests\Feature;

use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackRatingDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_feedback_allocates_stars_to_shift_staff(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);

        $kitchenRole = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

        // Kitchen Staff
        $kitchenUser = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $kitchenUser->assignRole($kitchenRole);
        $kitchenEmp = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $kitchenUser->id,
            'job_title'     => 'Nhân Viên Bếp',
            'rating_star'   => 5.0,
            'rating_count'  => 0,
        ]);

        // Service Staff
        $serviceUser = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $serviceUser->assignRole($cashierRole);
        $serviceEmp = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $serviceUser->id,
            'job_title'     => 'Thu Ngân',
            'rating_star'   => 5.0,
            'rating_count'  => 0,
        ]);

        $shift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Ca Chiều',
            'code'          => 'CA_CHIEU',
            'start_time'    => '00:00:00',
            'end_time'      => '23:59:59',
            'status'        => 'active',
        ]);

        $todayStr = now()->toDateString();

        // Assign both staff to today's shift
        ScheduleAssignment::create([
            'restaurant_id'  => $restaurant->id,
            'branch_id'      => $branch->id,
            'employee_id'    => $kitchenEmp->id,
            'shift_id'       => $shift->id,
            'scheduled_date' => $todayStr,
            'status'         => 'scheduled',
        ]);

        ScheduleAssignment::create([
            'restaurant_id'  => $restaurant->id,
            'branch_id'      => $branch->id,
            'employee_id'    => $serviceEmp->id,
            'shift_id'       => $shift->id,
            'scheduled_date' => $todayStr,
            'status'         => 'scheduled',
        ]);

        // Submit customer feedback with 4-star food rating and 5-star service rating
        $response = $this->postJson(route('feedback.store'), [
            'rating'            => 5,
            'items_rating'      => [1 => 4, 2 => 4], // Food rating 4 stars
            'staff_rating'      => [$serviceEmp->id => 5], // Direct staff rating 5 stars
            'is_anonymous'      => true,
            'restaurant_id'     => $restaurant->id,
        ]);

        $response->assertStatus(200);

        // Kitchen staff should receive 4 stars (items_rating)
        $kitchenEmp->refresh();
        $this->assertEquals(4.0, (float) $kitchenEmp->rating_star);
        $this->assertEquals(1, $kitchenEmp->rating_count);

        // Service staff should receive 5 stars (staff_rating)
        $serviceEmp->refresh();
        $this->assertEquals(5.0, (float) $serviceEmp->rating_star);
        $this->assertEquals(1, $serviceEmp->rating_count);
    }
}
