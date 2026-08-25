<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryStaffDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_staff_is_redirected_from_dashboard_to_inventory_page(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $role = Role::firstOrCreate(['name' => 'inventory_staff', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('inventory.index'));
    }

    public function test_owner_is_not_redirected_to_inventory_page(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_central_warehouse_staff_is_redirected_to_staff_portal(): void
    {
        $restaurant = Restaurant::factory()->create();
        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $role = Role::firstOrCreate(['name' => 'warehouse_staff', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'warehouse_branch_id' => $centralBranch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('inventory.staff-portal'));
    }

    public function test_central_warehouse_manager_still_enters_coordination_workspace(): void
    {
        $restaurant = Restaurant::factory()->create();
        $role = Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('inventory.central-warehouse'));
    }

    public function test_central_warehouse_staff_can_open_their_staff_portal(): void
    {
        $restaurant = Restaurant::factory()->create();
        $centralBranch = RestaurantBranch::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_central_warehouse' => true,
            'warehouse_type' => 'central',
        ]);
        $role = Role::firstOrCreate(['name' => 'warehouse_staff', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $centralBranch->id,
            'warehouse_branch_id' => $centralBranch->id,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('inventory.staff-portal'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/WarehouseStaffPortal')
            ->has('myTasks')
            ->has('taskSummary')
            ->has('currentUser')
        );
    }
}
