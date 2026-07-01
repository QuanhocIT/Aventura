<?php

namespace Tests\Feature;

use App\Models\BusinessGoal;
use App\Models\GoalAction;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessGoalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_action_rejects_users_from_a_different_restaurant(): void
    {
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner->assignRole($ownerRole);

        $goal = BusinessGoal::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Tăng doanh thu',
            'metric' => 'revenue',
            'period' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'target_value' => 1000000,
            'created_by' => $owner->id,
        ]);

        $action = GoalAction::create([
            'goal_id' => $goal->id,
            'title' => 'Chạy chiến dịch khuyến mãi',
        ]);

        // goal_actions has no restaurant_id column of its own — this action is only
        // reachable through its parent goal, which does belong to a specific restaurant.
        $intruderOwner = User::factory()->create();
        $intruderRestaurant = Restaurant::factory()->create(['owner_user_id' => $intruderOwner->id]);
        $intruderOwner->update(['restaurant_id' => $intruderRestaurant->id]);
        $intruderOwner->assignRole($ownerRole);

        $response = $this->actingAs($intruderOwner)->patch(route('goals.actions.toggle', $action));
        $response->assertForbidden();
        $this->assertSame('pending', $action->fresh()->status);

        $response = $this->actingAs($owner)->patch(route('goals.actions.toggle', $action));
        $response->assertRedirect();
        $this->assertSame('done', $action->fresh()->status);
    }
}
