<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\WarehouseTaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseStaffSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_must_be_assigned_to_the_central_branch_to_read_their_tasks(): void
    {
        [$restaurant, $central, $business] = $this->warehouseFixture();
        $staff = $this->staff($restaurant, $business);

        $this->actingAs($staff)
            ->getJson(route('warehouse.my-tasks'))
            ->assertForbidden();

        $staff->update(['warehouse_branch_id' => $central->id]);

        $this->actingAs($staff)
            ->getJson(route('warehouse.my-tasks'))
            ->assertOk();
    }

    public function test_paused_staff_cannot_submit_an_incident(): void
    {
        [$restaurant, $central] = $this->warehouseFixture();
        $staff = $this->staff($restaurant, $central, ['warehouse_staff_status' => 'paused']);

        $this->actingAs($staff)
            ->postJson(route('warehouse.incidents.store'), [
                'incident_type' => 'other',
                'description' => 'Test sự cố tại khu nhận hàng.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('warehouse_task_assignments', 0);
    }

    public function test_task_completion_requires_start_and_is_idempotent_after_completion(): void
    {
        [$restaurant, $central] = $this->warehouseFixture();
        $staff = $this->staff($restaurant, $central);
        $task = WarehouseTaskAssignment::create([
            'restaurant_id' => $restaurant->id,
            'assigned_to' => $staff->id,
            'assigned_by' => $staff->id,
            'task_type' => 'counting',
            'status' => 'assigned',
            'priority' => 'normal',
        ]);

        $this->actingAs($staff)
            ->postJson(route('warehouse.tasks.complete', $task->id), [
                'idempotency_key' => 'complete-'.$task->id,
            ])
            ->assertStatus(422);

        $this->actingAs($staff)
            ->postJson(route('warehouse.tasks.start', $task->id))
            ->assertOk();

        $payload = ['idempotency_key' => 'complete-'.$task->id, 'result_notes' => 'Đã kiểm đủ.'];
        $this->actingAs($staff)
            ->postJson(route('warehouse.tasks.complete', $task->id), $payload)
            ->assertOk();

        $this->actingAs($staff)
            ->postJson(route('warehouse.tasks.complete', $task->id), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        $this->assertDatabaseCount('warehouse_task_assignments', 1);
        $this->assertDatabaseHas('warehouse_task_assignments', [
            'id' => $task->id,
            'status' => 'completed',
            'idempotency_key' => 'complete-'.$task->id,
        ]);
    }

    /** @return array{0: Restaurant, 1: RestaurantBranch, 2?: RestaurantBranch} */
    private function warehouseFixture(): array
    {
        $restaurant = Restaurant::factory()->create();
        $central = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
            'status' => 'active',
        ]);
        $business = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => false,
            'warehouse_type' => 'business',
            'status' => 'active',
        ]);

        return [$restaurant, $central, $business];
    }

    private function staff(Restaurant $restaurant, RestaurantBranch $branch, array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'warehouse_branch_id' => $branch->id,
            'warehouse_staff_status' => 'active',
        ], $overrides));
        $staff->assignRole('warehouse_staff');

        return $staff;
    }
}
