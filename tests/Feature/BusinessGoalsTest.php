<?php

namespace Tests\Feature;

use App\Models\BusinessGoal;
use App\Models\GoalAction;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature coverage cho module Mục tiêu kinh doanh (business-goals) — trước đây
 * chưa có test riêng. Bao gồm: hiển thị trang, tạo mục tiêu (kèm mốc), và xoá.
 */
class BusinessGoalsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->restaurant = Restaurant::factory()->create(['name' => 'Aventura Bistro']);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);
    }

    public function test_goals_page_renders_for_owner(): void
    {
        $this->actingAs($this->owner)->get('/business-goals')->assertOk();
    }

    public function test_owner_can_create_a_goal_with_milestones(): void
    {
        $payload = [
            'title' => 'Tăng doanh thu Q3',
            'description' => 'Mục tiêu quý',
            'metric' => 'revenue',
            'period' => 'quarterly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'target_value' => 500000000,
            'milestones' => [
                ['title' => 'Đạt 50%', 'threshold_percent' => 50],
                ['title' => 'Đạt 100%', 'threshold_percent' => 100],
            ],
        ];

        $response = $this->actingAs($this->owner)->post('/business-goals', $payload);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $goal = BusinessGoal::where('restaurant_id', $this->restaurant->id)
            ->where('title', 'Tăng doanh thu Q3')->first();

        $this->assertNotNull($goal, 'Mục tiêu chưa được tạo.');
        $this->assertSame('revenue', $goal->metric);
        $this->assertSame((int) $this->owner->id, (int) $goal->created_by);
        $this->assertDatabaseHas('goal_milestones', ['goal_id' => $goal->id, 'threshold_percent' => 50]);
        $this->assertDatabaseHas('goal_milestones', ['goal_id' => $goal->id, 'threshold_percent' => 100]);
    }

    public function test_create_goal_validates_end_after_start(): void
    {
        $response = $this->actingAs($this->owner)->post('/business-goals', [
            'title' => 'Sai ngày',
            'metric' => 'orders',
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->subDay()->toDateString(), // end trước start → lỗi
            'target_value' => 100,
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseMissing('business_goals', ['title' => 'Sai ngày']);
    }

    public function test_owner_can_delete_a_goal(): void
    {
        $goal = BusinessGoal::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Mục tiêu tạm',
            'metric' => 'orders',
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'target_value' => 1000,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->delete("/business-goals/{$goal->id}")->assertRedirect();
        $this->assertDatabaseMissing('business_goals', ['id' => $goal->id]);
    }

    public function test_goal_is_tenant_scoped(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignGoal = BusinessGoal::create([
            'restaurant_id' => $otherRestaurant->id,
            'title' => 'Của nhà hàng khác',
            'metric' => 'revenue',
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'target_value' => 1000,
            'created_by' => $this->owner->id,
        ]);

        // Yêu cầu bảo mật: KHÔNG được xoá mục tiêu của nhà hàng khác.
        $this->actingAs($this->owner)->delete("/business-goals/{$foreignGoal->id}");
        $this->assertDatabaseHas('business_goals', ['id' => $foreignGoal->id]);
    }

    public function test_owner_can_update_a_custom_goal_value(): void
    {
        $goal = BusinessGoal::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Tăng tỷ lệ khách quay lại',
            'metric' => 'custom',
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'target_value' => 100,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->patch("/business-goals/{$goal->id}/value", ['current_value' => 42])
            ->assertRedirect();

        $this->assertDatabaseHas('business_goals', [
            'id' => $goal->id,
            'current_value' => 42,
            'progress_percent' => 42,
        ]);
    }

    public function test_owner_cannot_toggle_a_foreign_goal_action(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $foreignGoal = BusinessGoal::create([
            'restaurant_id' => $otherRestaurant->id,
            'title' => 'Mục tiêu riêng tư',
            'metric' => 'custom',
            'period' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'target_value' => 100,
        ]);
        $action = GoalAction::create([
            'goal_id' => $foreignGoal->id,
            'title' => 'Hành động riêng tư',
        ]);

        $this->actingAs($this->owner)
            ->patch("/business-goals/actions/{$action->id}/toggle")
            ->assertForbidden();

        $this->assertDatabaseHas('goal_actions', [
            'id' => $action->id,
            'status' => 'pending',
        ]);
    }

    public function test_goal_is_not_expired_until_the_end_of_its_end_date(): void
    {
        $goal = BusinessGoal::make([
            'end_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->assertFalse($goal->isExpired());
    }
}
